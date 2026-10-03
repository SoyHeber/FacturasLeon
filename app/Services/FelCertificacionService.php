<?php

namespace App\Services;

use App\Data\RespuestaAinnova;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FelCertificacionService
{
    public function __construct(
        private AinnovaFelClient $cliente,
        private FelRespuestaParserService $parser = new FelRespuestaParserService
    ) {}

    public function certificarVenta(int $ventaId, int $usuarioId): DocumentoFel
    {
        if (DB::connection()->transactionLevel() !== 0) {
            $this->rechazar('La certificación debe comenzar fuera de otra transacción.');
        }
        $this->cliente->validarConfiguracion();
        $contexto = $this->cliente->contextoEmision();
        $solicitud = DB::transaction(function () use ($ventaId, $usuarioId, $contexto) {
            $documento = DocumentoFel::where('venta_id', $ventaId)->lockForUpdate()->firstOrFail();
            $venta = $documento->venta()->firstOrFail();
            if ($venta->estado_venta !== 'CONFIRMADA' || $documento->estado_fel !== 'PENDIENTE') {
                $this->rechazar('Solo se puede certificar una Venta CONFIRMADA con FEL PENDIENTE.');
            }
            $xml = $documento->xml_solicitud;
            if (! is_string($xml) || trim($xml) === '' || ! is_string($documento->hash_xml)
                || ! hash_equals(hash('sha256', $xml), $documento->hash_xml)) {
                $this->rechazar('El XML congelado está vacío o su SHA-256 no coincide.');
            }
            if ($documento->p_tipo_doc !== 1 || $documento->p_tipo_respuesta !== 'D') {
                $this->rechazar('Solo se admite el documento FACT congelado con respuesta D.');
            }
            foreach ($contexto as $campo => $valor) {
                if ($documento->$campo !== null && $valor !== $documento->$campo) {
                    $this->rechazar('El contexto de emisión configurado no coincide con el documento FEL.');
                }
            }
            if ($documento->intentos()->where('resultado', 'EN_PROCESO')->exists()) {
                $this->rechazar('El documento ya tiene un intento de emisión sin finalizar.');
            }
            $intento = IntentoFel::create([
                'documento_fel_id' => $documento->id, 'usuario_id' => $usuarioId,
                'resultado' => 'EN_PROCESO', 'fecha_inicio' => now(),
            ]);
            if (! $intento->exists || ! $documento->forceFill(array_merge($contexto, ['estado_fel' => 'EN_PROCESO']))->save()) {
                throw new RuntimeException('No se pudo reservar el intento de certificación FEL.');
            }

            return ['documento_id' => $documento->id, 'intento_id' => $intento->id, 'xml' => $xml,
                'p_tipo_doc' => $documento->p_tipo_doc, 'p_tipo_respuesta' => $documento->p_tipo_respuesta,
                'nit_emisor' => $contexto['nit_emisor'], 'total_venta' => $venta->importe_total];
        }, 3);

        // La llamada ocurre después del COMMIT y no participa en los reintentos de transacción.
        try {
            $respuesta = $this->cliente->generarDocumento($solicitud['xml'], $solicitud['p_tipo_doc'], $solicitud['p_tipo_respuesta']);
        } catch (Throwable) {
            $respuesta = new RespuestaAinnova(RespuestaAinnova::TRANSPORTE, codigoTecnico: 'EXCEPCION_TRANSPORTE',
                mensajeTecnico: 'La llamada FEL se interrumpió y requiere conciliación.');
        }
        $texto = $this->cliente->protegerTexto($respuesta->texto);
        $rawOriginal = $respuesta->respuestaRaw ?? $respuesta->texto;
        $raw = $this->cliente->protegerTexto($rawOriginal);
        if ($texto !== $respuesta->texto || $raw !== $rawOriginal) {
            $resultado = $this->incierta('Ainnova devolvió datos sensibles; la respuesta se guardó con esos datos ocultos.');
        } elseif (in_array($respuesta->tipo, [RespuestaAinnova::RESPUESTA, RespuestaAinnova::TEXTUAL], true)) {
            $resultado = $this->parser->procesar($texto ?? $raw, $solicitud['nit_emisor'], $solicitud['total_venta']);
        } else {
            $resultado = $this->incierta('No existe respuesta concluyente de Ainnova: '.$respuesta->tipo.'.');
        }

        return DB::transaction(function () use ($solicitud, $resultado, $raw) {
            $documento = DocumentoFel::whereKey($solicitud['documento_id'])->lockForUpdate()->firstOrFail();
            $intento = IntentoFel::whereKey($solicitud['intento_id'])->where('documento_fel_id', $documento->id)->lockForUpdate()->firstOrFail();
            if ($documento->estado_fel !== 'EN_PROCESO' || $intento->resultado !== 'EN_PROCESO' || $intento->fecha_fin !== null
                || (int) $documento->intentos()->latest('id')->value('id') !== (int) $intento->id) {
                $this->rechazar('La respuesta no pertenece al intento FEL actualmente en proceso.');
            }
            if (! $documento->forceFill(array_merge($resultado['campos'], ['estado_fel' => $resultado['estado']]))->save()
                || ! $intento->update([
                    'resultado' => $resultado['estado'], 'fecha_fin' => now(), 'respuesta_raw' => $raw,
                    'codigo_error' => $resultado['codigo_error'], 'mensaje_error' => $this->cliente->protegerTexto($resultado['mensaje_error']),
                    'error_tecnico' => $this->cliente->protegerTexto($resultado['error_tecnico']),
                ])) {
                throw new RuntimeException('No se pudo guardar el resultado de certificación FEL.');
            }

            return $documento->refresh()->load('ultimoIntento');
        }, 3);
    }

    public function conciliarDesdeRespuestaExistente(int $documentoFelId, int $usuarioId): DocumentoFel
    {
        if (DB::connection()->transactionLevel() !== 0) {
            $this->rechazar('La conciliación debe comenzar fuera de otra transacción.');
        }
        $conciliacion = DB::transaction(function () use ($documentoFelId) {
            $documento = DocumentoFel::whereKey($documentoFelId)->lockForUpdate()->firstOrFail();
            if (! in_array($documento->estado_fel, ['INCIERTA', 'EN_PROCESO'], true)) {
                $this->rechazar('Solo se puede conciliar una respuesta FEL INCIERTA o EN_PROCESO recuperable.');
            }
            $venta = $documento->venta()->firstOrFail();
            if ($venta->estado_venta !== 'CONFIRMADA' || $documento->p_tipo_doc !== 1 || $documento->p_tipo_respuesta !== 'D') {
                $this->rechazar('La conciliación requiere una Venta CONFIRMADA y su documento FACT con respuesta D.');
            }
            if (! is_string($documento->xml_solicitud) || trim($documento->xml_solicitud) === ''
                || ! is_string($documento->hash_xml) || ! hash_equals(hash('sha256', $documento->xml_solicitud), $documento->hash_xml)) {
                $this->rechazar('El XML congelado está vacío o su SHA-256 no coincide.');
            }
            $intento = $documento->intentos()->whereNotNull('respuesta_raw')->where('respuesta_raw', '<>', '')->latest('id')->lockForUpdate()->first();
            if ($intento === null || trim($intento->respuesta_raw) === '') {
                $this->rechazar('No existe una respuesta guardada para conciliar localmente.');
            }
            $ultimoId = (int) $documento->intentos()->latest('id')->value('id');
            if (($documento->estado_fel === 'EN_PROCESO' && $ultimoId !== (int) $intento->id)
                || $documento->intentos()->where('id', '>', $intento->id)->where('resultado', 'EN_PROCESO')->whereNull('fecha_fin')->exists()) {
                $this->rechazar('Existe un intento posterior en proceso sin respuesta recuperable.');
            }
            $nitEmisor = config('fel.ainnova.nit_emisor');
            if (! is_string($nitEmisor) || trim($nitEmisor) === '') {
                $this->rechazar('Configure el NIT emisor para validar la respuesta guardada.');
            }
            $resultado = $this->parser->procesar($intento->respuesta_raw, $nitEmisor, $venta->importe_total);
            $certificada = $resultado['estado'] === 'CERTIFICADA';
            $estado = $certificada ? 'CERTIFICADA' : 'INCIERTA';
            if (! $documento->forceFill(array_merge($certificada ? $resultado['campos'] : [], ['estado_fel' => $estado]))->save()
                || ! $intento->update([
                    'resultado' => $estado, 'fecha_fin' => $intento->fecha_fin ?? now(),
                    // El diagnóstico original se conserva; la conciliación se audita por separado.
                    'error_tecnico' => $intento->error_tecnico ?? ($certificada ? null : 'La respuesta guardada no acredita una certificación válida.'),
                ])) {
                throw new RuntimeException('No se pudo guardar la conciliación local FEL.');
            }

            return ['documento' => $documento->refresh()->load('ultimoIntento'), 'intento_id' => $intento->id];
        }, 3);

        Log::info('Conciliación local de respuesta FEL.', [
            'documento_fel_id' => $documentoFelId, 'intento_id' => $conciliacion['intento_id'],
            'usuario_id' => $usuarioId, 'estado_fel' => $conciliacion['documento']->estado_fel,
        ]);

        return $conciliacion['documento'];
    }

    private function incierta(string $mensaje): array
    {
        return ['estado' => 'INCIERTA', 'campos' => [], 'codigo_error' => null, 'mensaje_error' => null, 'error_tecnico' => $mensaje];
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['fel' => $mensaje]);
    }
}
