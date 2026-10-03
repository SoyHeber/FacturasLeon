<?php

namespace App\Services;

use App\Models\DocumentoFel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AinnovaFelConsultaClient
{
    private array $configuracion;

    public function __construct(?array $configuracion = null)
    {
        $this->configuracion = $configuracion ?? config('fel.ainnova', []);
    }

    public function consultar(int $documentoFelId, string $tipo): string
    {
        if (! in_array($tipo, ['PDF', 'XML'], true)) {
            $this->rechazar('Solo se puede consultar PDF o XML certificado.');
        }
        $datos = DB::transaction(function () use ($documentoFelId) {
            $documento = DocumentoFel::whereKey($documentoFelId)->lockForUpdate()->firstOrFail();
            if ($documento->estado_fel !== 'CERTIFICADA' || ! $documento->fel_serie || ! $documento->fel_numero) {
                $this->rechazar('La consulta PDF/XML requiere un documento FEL CERTIFICADA con serie y número.');
            }

            return ['serie' => $documento->fel_serie, 'numero' => $documento->fel_numero];
        }, 3);
        foreach (['endpoint_rest', 'basic_usuario', 'basic_password', 'ws_usuario', 'ws_password', 'nit_emisor'] as $campo) {
            if (! is_string($this->configuracion[$campo] ?? null) || trim($this->configuracion[$campo]) === '') {
                $this->rechazar('Complete la configuración de consulta REST FEL/Ainnova.');
            }
        }
        if (! $this->urlSegura($this->configuracion['endpoint_rest'])) {
            $this->rechazar('El endpoint REST FEL debe ser una URL HTTPS sin credenciales embebidas.');
        }
        $timeout = $this->tiempoEspera('timeout', 60);
        $conexion = $this->tiempoEspera('connect_timeout', 10);
        try {
            $respuesta = Http::withBasicAuth($this->configuracion['basic_usuario'], $this->configuracion['basic_password'])
                ->withHeaders(['p_usuario' => $this->configuracion['ws_usuario'], 'p_clave' => $this->configuracion['ws_password'],
                    'p_emisor' => $this->configuracion['nit_emisor'], 'p_serie' => $datos['serie'],
                    'p_numero' => $datos['numero'], 'p_tipo' => $tipo])
                ->timeout($timeout)->connectTimeout($conexion)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->bodyFormat('body')->send('POST', $this->configuracion['endpoint_rest']);
        } catch (Throwable $excepcion) {
            $this->registrarDiagnostico($documentoFelId, $tipo, 'EXCEPCION', excepcion: $excepcion);
            $this->rechazar('No se pudo consultar la representación FEL. La factura continúa CERTIFICADA.');
        }
        if (! $respuesta->successful()) {
            $this->registrarDiagnostico($documentoFelId, $tipo, 'HTTP_NO_EXITOSO', $respuesta);
            $this->rechazar('Ainnova no pudo entregar la representación FEL. La factura continúa CERTIFICADA.');
        }
        $texto = trim($respuesta->body());
        $protector = new AinnovaFelClient($this->configuracion);
        if (str_starts_with(strtoupper($texto), 'ERROR:')) {
            $this->registrarDiagnostico($documentoFelId, $tipo, 'ERROR_FUNCIONAL', $respuesta);
            $this->rechazar(mb_substr($protector->protegerTexto($texto), 0, 500));
        }
        // La redacción protege el diagnóstico; no decide si la URL recibida es válida.
        if ($this->urlSegura($texto)) {
            $this->registrarDiagnostico($documentoFelId, $tipo, 'EXITO', $respuesta);

            return $texto;
        }
        if (strlen($texto) <= 8192) {
            $json = json_decode($texto, true);
            $url = is_string($json) ? $json : (is_array($json) ? ($json['url'] ?? null) : $texto);
            if (is_string($url) && $this->urlSegura($url)) {
                $this->registrarDiagnostico($documentoFelId, $tipo, 'EXITO', $respuesta);

                return $url;
            }
        }
        $this->registrarDiagnostico($documentoFelId, $tipo, 'URL_INVALIDA', $respuesta);
        $this->rechazar('Ainnova no devolvió una URL segura de PDF/XML. La factura continúa CERTIFICADA.');
    }

    private function registrarDiagnostico(int $documentoFelId, string $tipo, string $causa,
        ?\Illuminate\Http\Client\Response $respuesta = null, ?Throwable $excepcion = null): void
    {
        $protector = new AinnovaFelClient($this->configuracion);
        $contexto = [
            'documento_fel_id' => $documentoFelId,
            'tipo' => $tipo,
            'causa' => $causa,
            'http_status' => $respuesta?->status(),
            'content_type' => $protector->protegerTexto($respuesta?->header('Content-Type')),
            'body' => $protector->protegerTexto($respuesta !== null ? trim($respuesta->body()) : null),
            'tipo_excepcion' => $excepcion !== null ? get_class($excepcion) : null,
            'excepcion_tecnica' => $protector->protegerTexto($excepcion?->getMessage()),
        ];
        if ($causa === 'EXITO') {
            Log::info('Consulta REST FEL/Ainnova.', $contexto);
        } else {
            Log::warning('Consulta REST FEL/Ainnova.', $contexto);
        }
    }

    private function tiempoEspera(string $campo, int $defecto): int
    {
        $valor = filter_var($this->configuracion[$campo] ?? $defecto, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 600]]);
        if ($valor === false) {
            $this->rechazar('Los tiempos de espera REST deben estar entre 1 y 600 segundos.');
        }

        return $valor;
    }

    private function urlSegura(string $url): bool
    {
        $partes = parse_url($url);

        return ! preg_match('/[\x00-\x20\x7f]/', $url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($partes)
            && strtolower($partes['scheme'] ?? '') === 'https' && ! empty($partes['host'])
            && ! isset($partes['user']) && ! isset($partes['pass']);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['fel' => $mensaje]);
    }
}
