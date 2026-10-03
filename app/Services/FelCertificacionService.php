<?php

namespace App\Services;

use App\Data\RespuestaAinnova;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use Illuminate\Support\Facades\Cache;
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
        $documentoId = DocumentoFel::where('venta_id', $ventaId)->firstOrFail()->id;

        return $this->conDocumentoExclusivo($documentoId, fn () => $this->certificarInicial($ventaId, $usuarioId));
    }

    private function certificarInicial(int $ventaId, int $usuarioId): DocumentoFel
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

        return $this->enviarSolicitud($solicitud);
    }

    private function enviarSolicitud(array $solicitud): DocumentoFel
    {
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

    public function reintentarCertificacion(int $documentoFelId, int $usuarioId, ?int $ultimoIntentoId = null): DocumentoFel
    {
        return $this->conDocumentoExclusivo($documentoFelId, function () use ($documentoFelId, $usuarioId, $ultimoIntentoId) {
            $solicitud = DB::transaction(function () use ($documentoFelId, $usuarioId, $ultimoIntentoId) {
                $documento = DocumentoFel::whereKey($documentoFelId)->lockForUpdate()->firstOrFail();
                if ($documento->estado_fel !== 'INCIERTA') {
                    $this->rechazar('Solo se puede reintentar una certificación INCIERTA.');
                }
                $ultimoId = (int) $documento->intentos()->latest('id')->value('id');
                if ($ultimoIntentoId !== null && $ultimoIntentoId !== $ultimoId) {
                    $this->rechazar('El historial FEL cambió. Actualice la Venta antes de reintentar.');
                }
                $this->exigirSinIntentoActivo($documento);
                $venta = $this->validarDocumentoCongelado($documento);
                $nit = $this->nitEmisor($documento);
                $recuperada = $this->certificacionGuardada($documento, $nit, $venta->importe_total);
                if ($recuperada !== null) {
                    $this->guardarCertificacion($documento, $recuperada['resultado']);

                    return ['documento' => $documento->refresh()->load('ultimoIntento'), 'intento_id' => $recuperada['intento_id']];
                }
                $this->cliente->validarConfiguracion();
                foreach ($this->cliente->contextoEmision() as $campo => $valor) {
                    if ($documento->$campo !== null && $valor !== $documento->$campo) {
                        $this->rechazar('El contexto de emisión configurado no coincide con el documento FEL.');
                    }
                }
                $intento = IntentoFel::create(['documento_fel_id' => $documento->id, 'usuario_id' => $usuarioId,
                    'resultado' => 'EN_PROCESO', 'fecha_inicio' => now()]);
                if (! $intento->exists || ! $documento->forceFill(['estado_fel' => 'EN_PROCESO'])->save()) {
                    throw new RuntimeException('No se pudo reservar el reintento FEL.');
                }

                return ['documento_id' => $documento->id, 'intento_id' => $intento->id, 'xml' => $documento->xml_solicitud,
                    'p_tipo_doc' => $documento->p_tipo_doc, 'p_tipo_respuesta' => $documento->p_tipo_respuesta,
                    'nit_emisor' => $nit, 'total_venta' => $venta->importe_total];
            }, 3);
            if (isset($solicitud['documento'])) {
                $this->auditarConciliacion($solicitud['documento'], $solicitud['intento_id'], $usuarioId);

                return $solicitud['documento'];
            }

            return $this->enviarSolicitud($solicitud);
        });
    }

    public function recuperarCertificacion(int $documentoFelId, int $usuarioId): DocumentoFel
    {
        return $this->conDocumentoExclusivo($documentoFelId, function () use ($documentoFelId, $usuarioId) {
            $recuperacion = DB::transaction(function () use ($documentoFelId) {
                $documento = DocumentoFel::whereKey($documentoFelId)->lockForUpdate()->firstOrFail();
                $ultimo = $documento->intentos()->latest('id')->lockForUpdate()->first();
                if ($documento->estado_fel !== 'EN_PROCESO' || ! $ultimo || ! $documento->intentoExpirado($ultimo)) {
                    $this->rechazar('Solo se puede recuperar un intento EN_PROCESO abierto y expirado.');
                }
                if ($documento->intentos()->whereKeyNot($ultimo->id)->where('resultado', 'EN_PROCESO')->whereNull('fecha_fin')->exists()) {
                    $this->rechazar('Existe otro intento FEL activo. Revise el historial antes de recuperar.');
                }
                $venta = $this->validarDocumentoCongelado($documento);
                $recuperada = $this->certificacionGuardada($documento, $this->nitEmisor($documento), $venta->importe_total);
                if ($recuperada !== null) {
                    $this->guardarCertificacion($documento, $recuperada['resultado']);
                } elseif (! $documento->forceFill(['estado_fel' => 'INCIERTA'])->save()) {
                    throw new RuntimeException('No se pudo recuperar el documento FEL.');
                }
                // Se cierra únicamente el intento abandonado; los intentos anteriores conservan su historia.
                if (! $ultimo->update(['resultado' => $documento->estado_fel, 'fecha_fin' => now(),
                    'error_tecnico' => $ultimo->error_tecnico ?? 'Intento expirado recuperado manualmente sin nuevo envío.'])) {
                    throw new RuntimeException('No se pudo cerrar el intento FEL abandonado.');
                }

                return ['documento' => $documento->refresh()->load('ultimoIntento'), 'intento_id' => $ultimo->id];
            }, 3);
            $this->auditarConciliacion($recuperacion['documento'], $recuperacion['intento_id'], $usuarioId);

            return $recuperacion['documento'];
        });
    }

    public function conciliarDesdeRespuestaExistente(int $documentoFelId, int $usuarioId): DocumentoFel
    {
        return $this->conDocumentoExclusivo($documentoFelId, function () use ($documentoFelId, $usuarioId) {
            $conciliacion = DB::transaction(function () use ($documentoFelId) {
                $documento = DocumentoFel::whereKey($documentoFelId)->lockForUpdate()->firstOrFail();
                if ($documento->estado_fel !== 'INCIERTA') {
                    $this->rechazar('Solo se puede conciliar una respuesta INCIERTA. Para EN_PROCESO expirado use Recuperar certificación.');
                }
                $this->exigirSinIntentoActivo($documento);
                $venta = $this->validarDocumentoCongelado($documento);
                $nit = $this->nitEmisor($documento);
                $ultimoConRespuesta = $documento->intentos()->whereNotNull('respuesta_raw')->where('respuesta_raw', '<>', '')->latest('id')->lockForUpdate()->first();
                if (! $ultimoConRespuesta || trim($ultimoConRespuesta->respuesta_raw) === '') {
                    $this->rechazar('No existe una respuesta guardada para conciliar localmente.');
                }
                $recuperada = $this->certificacionGuardada($documento, $nit, $venta->importe_total);
                if ($recuperada !== null) {
                    $this->guardarCertificacion($documento, $recuperada['resultado']);
                }

                return ['documento' => $documento->refresh()->load('ultimoIntento'),
                    'intento_id' => $recuperada['intento_id'] ?? $ultimoConRespuesta->id];
            }, 3);
            $this->auditarConciliacion($conciliacion['documento'], $conciliacion['intento_id'], $usuarioId);

            return $conciliacion['documento'];
        });
    }

    private function conDocumentoExclusivo(int $documentoFelId, \Closure $operacion): DocumentoFel
    {
        if (DB::connection()->transactionLevel() !== 0) {
            $this->rechazar('La operación FEL debe comenzar fuera de otra transacción.');
        }
        // El bloqueo cubre también la llamada remota para impedir recuperación o envío simultáneos.
        $bloqueo = Cache::lock('fel-documento-'.$documentoFelId, DocumentoFel::minutosIntentoExpirado() * 60);
        if (! $bloqueo->get()) {
            $this->rechazar('Ya hay una operación de certificación activa para este documento.');
        }
        try {
            return $operacion();
        } finally {
            $bloqueo->release();
        }
    }

    private function validarDocumentoCongelado(DocumentoFel $documento): \App\Models\Venta
    {
        $venta = $documento->venta()->firstOrFail();
        if ($venta->estado_venta !== 'CONFIRMADA' || $documento->p_tipo_doc !== 1 || $documento->p_tipo_respuesta !== 'D') {
            $this->rechazar('La operación requiere una Venta CONFIRMADA y su documento FACT con respuesta D.');
        }
        if (! is_string($documento->xml_solicitud) || trim($documento->xml_solicitud) === '' || ! is_string($documento->hash_xml)
            || ! hash_equals(hash('sha256', $documento->xml_solicitud), $documento->hash_xml)) {
            $this->rechazar('El XML congelado está vacío o su SHA-256 no coincide.');
        }

        return $venta;
    }

    private function exigirSinIntentoActivo(DocumentoFel $documento): void
    {
        if ($documento->intentos()->where('resultado', 'EN_PROCESO')->whereNull('fecha_fin')->lockForUpdate()->first()) {
            $this->rechazar('El documento ya tiene un intento de emisión sin finalizar.');
        }
    }

    private function nitEmisor(DocumentoFel $documento): string
    {
        $nit = config('fel.ainnova.nit_emisor');
        if (! is_string($nit) || trim($nit) === '') {
            $this->rechazar('Configure el NIT emisor para validar la respuesta guardada.');
        }
        $normalizar = fn (string $valor) => strtoupper(preg_replace('/[\s-]+/', '', $valor));
        if ($documento->nit_emisor !== null && $normalizar($documento->nit_emisor) !== $normalizar($nit)) {
            $this->rechazar('El NIT configurado no coincide con el emisor del documento FEL.');
        }

        return $nit;
    }

    private function certificacionGuardada(DocumentoFel $documento, string $nit, string $total): ?array
    {
        $intentos = $documento->intentos()->whereNotNull('respuesta_raw')->latest('id')->lockForUpdate()->get();
        foreach ($intentos as $intento) {
            if (trim($intento->respuesta_raw) === '') {
                continue;
            }
            $resultado = $this->parser->procesar($intento->respuesta_raw, $nit, $total);
            if ($resultado['estado'] === 'CERTIFICADA') {
                return ['resultado' => $resultado, 'intento_id' => $intento->id];
            }
        }

        return null;
    }

    private function guardarCertificacion(DocumentoFel $documento, array $resultado): void
    {
        if (! $documento->forceFill(array_merge($resultado['campos'], ['estado_fel' => 'CERTIFICADA']))->save()) {
            throw new RuntimeException('No se pudo guardar la conciliación local FEL.');
        }
    }

    private function auditarConciliacion(DocumentoFel $documento, int $intentoId, int $usuarioId): void
    {
        Log::info('Conciliación local de respuesta FEL.', ['documento_fel_id' => $documento->id,
            'intento_id' => $intentoId, 'usuario_id' => $usuarioId, 'estado_fel' => $documento->estado_fel]);
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
