<?php

namespace App\Services;

use App\Data\RespuestaAinnova;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SoapFault;
use SoapParam;
use Throwable;

class AinnovaFelClient
{
    public const NAMESPACE = 'http://dbguatefac/Guatefac.wsdl';

    public const SOAP_ACTION = 'http://dbguatefac/Guatefac.wsdl/generaDocumento';

    private array $configuracion;

    public function __construct(?array $configuracion = null)
    {
        $this->configuracion = $configuracion ?? config('fel.ainnova', []);
    }

    public function validarConfiguracion(): void
    {
        if (! extension_loaded('soap')) {
            $this->rechazar('Se necesita la extensión PHP SOAP para certificar FEL.');
        }
        foreach (['endpoint', 'basic_usuario', 'basic_password', 'ws_usuario', 'ws_password', 'nit_emisor', 'establecimiento', 'id_maquina'] as $campo) {
            if (! is_string($this->configuracion[$campo] ?? null) || trim($this->configuracion[$campo]) === '') {
                $this->rechazar('Complete la configuración FEL/Ainnova antes de certificar.');
            }
        }
        $url = parse_url($this->configuracion['endpoint']);
        if (! is_array($url) || ! in_array($url['scheme'] ?? '', ['http', 'https'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
            $this->rechazar('El endpoint FEL debe ser una URL HTTP/HTTPS sin credenciales embebidas.');
        }
        foreach (['timeout', 'connect_timeout'] as $campo) {
            if (filter_var($this->configuracion[$campo] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 600]]) === false) {
                $this->rechazar('Los tiempos de espera FEL deben ser enteros entre 1 y 600 segundos.');
            }
        }
        if (filter_var($this->configuracion['verificar_ssl'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            $this->rechazar('La opción de verificación SSL FEL debe ser booleana.');
        }
        if (strlen($this->configuracion['nit_emisor']) > 30 || strlen($this->configuracion['establecimiento']) > 20 || strlen($this->configuracion['id_maquina']) > 100) {
            $this->rechazar('El contexto del emisor excede los tamaños admitidos.');
        }
    }

    public function contextoEmision(): array
    {
        return [
            'nit_emisor' => $this->configuracion['nit_emisor'],
            'codigo_establecimiento' => $this->configuracion['establecimiento'],
            'id_maquina' => $this->configuracion['id_maquina'],
        ];
    }

    public function generarDocumento(string $xml, int $tipoDocumento, string $tipoRespuesta): RespuestaAinnova
    {
        $inicio = hrtime(true);
        $cliente = null;
        $handlerInstalado = false;
        $timeoutAnterior = ini_get('default_socket_timeout');
        try {
            $this->validarConfiguracion();
            if ($tipoDocumento !== 1 || $tipoRespuesta !== 'D') {
                $this->rechazar('Este cliente admite únicamente FACT con respuesta D.');
            }
            // Las advertencias nativas de SOAP pueden incluir valores del request.
            set_error_handler(function (int $nivel, string $mensaje): never {
                $mensaje = strtolower($mensaje);
                throw new RuntimeException(str_contains($mensaje, 'timeout') || str_contains($mensaje, 'timed out') ? 'SOAP TIMEOUT' : 'SOAP TRANSPORTE');
            }, E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE);
            $handlerInstalado = true;
            $verificar = filter_var($this->configuracion['verificar_ssl'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $contexto = stream_context_create([
                'ssl' => ['verify_peer' => $verificar, 'verify_peer_name' => $verificar, 'allow_self_signed' => ! $verificar],
                'http' => ['follow_location' => 0, 'max_redirects' => 0],
            ]);
            $cliente = $this->crearSoapClient([
                'location' => $this->configuracion['endpoint'], 'uri' => self::NAMESPACE,
                'style' => SOAP_RPC, 'use' => SOAP_LITERAL, 'soap_version' => SOAP_1_1,
                'authentication' => SOAP_AUTHENTICATION_BASIC,
                'login' => $this->configuracion['basic_usuario'], 'password' => $this->configuracion['basic_password'],
                'connection_timeout' => (int) $this->configuracion['connect_timeout'],
                'stream_context' => $contexto, 'encoding' => 'UTF-8', 'exceptions' => true, 'trace' => false, 'keep_alive' => false,
            ]);
            // SoapClient utiliza default_socket_timeout para la espera de respuesta.
            ini_set('default_socket_timeout', (string) $this->configuracion['timeout']);
            $resultado = $cliente->__soapCall('generaDocumento', [
                new SoapParam($this->configuracion['ws_usuario'], 'pUsuario'),
                new SoapParam($this->configuracion['ws_password'], 'pPassword'),
                new SoapParam($this->configuracion['nit_emisor'], 'pNitEmisor'),
                new SoapParam($this->configuracion['establecimiento'], 'pEstablecimiento'),
                new SoapParam($tipoDocumento, 'pTipoDoc'),
                new SoapParam($this->configuracion['id_maquina'], 'pIdMaquina'),
                new SoapParam($tipoRespuesta, 'pTipoRespuesta'), new SoapParam($xml, 'pXml'),
            ], ['soapaction' => self::SOAP_ACTION]);
            $texto = $this->extraerTexto($resultado);
            $inicioTexto = $texto === null ? '' : ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $texto));

            return $this->protegerRespuesta(new RespuestaAinnova(
                $texto !== null && ! str_starts_with($inicioTexto, '<') ? RespuestaAinnova::TEXTUAL : RespuestaAinnova::RESPUESTA,
                $texto, $cliente->respuestaRaw() ?? $texto, duracionMs: $this->duracion($inicio)
            ));
        } catch (ValidationException) {
            return new RespuestaAinnova(RespuestaAinnova::CONFIGURACION, mensajeTecnico: 'Configuración FEL inválida.', duracionMs: $this->duracion($inicio));
        } catch (Throwable $exception) {
            $mensaje = strtolower($exception->getMessage());
            $tipo = match (true) {
                str_contains($mensaje, 'timed out'), str_contains($mensaje, 'timeout') => RespuestaAinnova::TIMEOUT,
                $exception instanceof SoapFault && strtoupper((string) $exception->faultcode) === 'HTTP' => RespuestaAinnova::HTTP,
                $exception instanceof SoapFault => RespuestaAinnova::SOAP_FAULT,
                default => RespuestaAinnova::TRANSPORTE,
            };

            return $this->protegerRespuesta(new RespuestaAinnova($tipo, respuestaRaw: $cliente?->respuestaRaw(), codigoTecnico: $tipo,
                mensajeTecnico: 'La llamada FEL no obtuvo una respuesta concluyente ('.$tipo.').', duracionMs: $this->duracion($inicio)));
        } finally {
            if ($handlerInstalado) {
                restore_error_handler();
            }
            if ($timeoutAnterior !== false) {
                ini_set('default_socket_timeout', $timeoutAnterior);
            }
        }
    }

    public function protegerTexto(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }
        $secretos = [];
        foreach (['basic_usuario', 'basic_password', 'ws_usuario', 'ws_password'] as $campo) {
            $valor = $this->configuracion[$campo] ?? '';
            if (is_string($valor) && $valor !== '') {
                $codificado = $valor;
                for ($nivel = 0; $nivel < 4; $nivel++) {
                    $secretos[] = $codificado;
                    $codificado = htmlspecialchars($codificado, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                array_push($secretos, rawurlencode($valor), rawurlencode(rawurlencode($valor)));
            }
        }
        $basic = ($this->configuracion['basic_usuario'] ?? '').':'.($this->configuracion['basic_password'] ?? '');
        if ($basic !== ':') {
            $secretos[] = base64_encode($basic);
        }
        usort($secretos, fn ($a, $b) => strlen($b) <=> strlen($a));
        $texto = str_replace(array_unique($secretos), '[REDACTADO]', $texto);
        $texto = preg_replace('/(\b(?:Proxy-)?Authorization\s*:\s*Basic\s+)[^\s<]+/i', '$1[REDACTADO]', $texto);

        return preg_replace('/(<(?:[\w.-]+:)?(?:pPassword|pUsuario|password|basic_password|basic_usuario)\b[^>]*>)[\s\S]*?(<\/(?:[\w.-]+:)?(?:pPassword|pUsuario|password|basic_password|basic_usuario)\s*>)/i', '$1[REDACTADO]$2', $texto);
    }

    protected function crearSoapClient(array $opciones): AinnovaSoapClient
    {
        return new AinnovaSoapClient(null, $opciones);
    }

    private function protegerRespuesta(RespuestaAinnova $respuesta): RespuestaAinnova
    {
        $texto = $this->protegerTexto($respuesta->texto);
        $raw = $this->protegerTexto($respuesta->respuestaRaw);
        // Solo se altera la respuesta si devuelve secretos; ese caso requiere conciliación.
        if ($texto !== $respuesta->texto || $raw !== $respuesta->respuestaRaw) {
            return new RespuestaAinnova(RespuestaAinnova::TRANSPORTE, $texto, $raw, 'RESPUESTA_CON_SECRETOS',
                'La respuesta contiene datos sensibles que se ocultaron.', $respuesta->duracionMs);
        }

        return $respuesta;
    }

    private function extraerTexto(mixed $resultado): ?string
    {
        for ($nivel = 0; $nivel < 5; $nivel++) {
            if (is_string($resultado)) {
                return $resultado;
            }
            $datos = is_object($resultado) ? get_object_vars($resultado) : $resultado;
            if (! is_array($datos)) {
                return null;
            }
            $encontrado = false;
            foreach (['return', 'generaDocumentoReturn', 'generaDocumentoResult', 'Resultado'] as $clave) {
                if (array_key_exists($clave, $datos)) {
                    $resultado = $datos[$clave];
                    $encontrado = true;
                    break;
                }
            }
            if (! $encontrado) {
                return null;
            }
        }

        return null;
    }

    private function duracion(int $inicio): int
    {
        return intdiv(hrtime(true) - $inicio, 1000000);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['fel' => $mensaje]);
    }
}
