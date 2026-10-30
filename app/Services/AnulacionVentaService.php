<?php

namespace App\Services;

use App\Data\RespuestaAinnova;
use App\Models\AnulacionVenta;
use App\Models\DocumentoFel;
use App\Models\IntentoAnulacionVenta;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AnulacionVentaService
{
    public function __construct(
        private AinnovaFelClient $cliente,
        private FelAnulacionRespuestaParserService $parser = new FelAnulacionRespuestaParserService,
        private InventarioService $stock = new InventarioService,
        private FelRespuestaParserService $parserCertificacion = new FelRespuestaParserService
    ) {}

    /**
     * Cancela una venta sin emisión comprobada o solicita anulaDocumento para una certificada.
     * Recibe fecha Y-m-d, motivo y el último intento que vio el usuario para rechazar formularios
     * antiguos. La fecha debe ser igual o posterior a la del documento (fecha de la venta),
     * dentro del mismo mes y año y sin admitir fechas futuras. Se valida antes de reservar
     * el envío en una transacción; la llamada a Ainnova ocurre después del commit.
     * Un timeout conserva el inventario; solo una confirmación de identidad válida habilita
     * el reintegro. La respuesta confirmada se guarda antes de intentar aplicarlo localmente.
     */
    public function anular(int $ventaId, int $usuarioId, array $datos): AnulacionVenta
    {
        $this->validarDatos($datos);

        return $this->conExclusion($ventaId, function () use ($ventaId, $usuarioId, $datos) {
            $reserva = DB::transaction(function () use ($ventaId, $usuarioId, $datos) {
                [$venta, $documento] = $this->bloquearVenta($ventaId);
                $anulacion = AnulacionVenta::where('venta_id', $ventaId)->lockForUpdate()->first();
                $this->exigirFormularioActual($anulacion, (int) $datos['ultimo_intento_anulacion_id']);
                if ($venta->estado_venta === 'ANULADA' || ($anulacion && $anulacion->estado !== 'RECHAZADA')) {
                    $this->rechazar('La venta ya está anulada o tiene una anulación pendiente. Revise su historial.');
                }
                if ($datos['fecha_anulacion'] < $venta->fecha->format('Y-m-d')) {
                    $this->rechazar('La fecha de anulación no puede ser anterior a la fecha del documento.');
                }
                if (substr($datos['fecha_anulacion'], 0, 7) !== $venta->fecha->format('Y-m')) {
                    $this->rechazar('La fecha de anulación debe estar dentro del mismo mes y año del documento ('.$venta->fecha->format('m/Y').').');
                }
                $remota = $documento->estado_fel === 'CERTIFICADA';
                if ($remota) {
                    $this->exigirCertificada($venta, $documento);
                    $this->cliente->validarConfiguracion();
                    $nitConfigurado = $this->cliente->contextoEmision()['nit_emisor'];
                    if ($this->nit($nitConfigurado) !== $this->nit($documento->nit_emisor)) {
                        $this->rechazar('El emisor configurado no coincide con la factura original.');
                    }
                } else {
                    $this->exigirSinEmision($venta, $documento);
                }
                $this->comprobarReintegro($venta);
                $solicitud = [
                    'nit_emisor' => $documento->nit_emisor ?? '', 'nit_comprador' => $venta->receptor_identificacion,
                    'serie' => $documento->fel_serie ?? '', 'numero' => $documento->fel_numero ?? '',
                    'fecha' => str_replace('-', '', $datos['fecha_anulacion']), 'motivo' => trim($datos['motivo']),
                ];
                $anulacion ??= new AnulacionVenta(['venta_id' => $ventaId, 'documento_fel_id' => $documento->id, 'usuario_id' => $usuarioId]);
                $anulacion->fill([
                    'tipo' => $remota ? 'FEL' : 'LOCAL', 'estado' => $remota ? 'EN_PROCESO' : 'CONFIRMADA',
                    'fecha_anulacion' => $datos['fecha_anulacion'], 'motivo' => trim($datos['motivo']),
                    'solicitud' => $solicitud, 'ultimo_error' => null,
                ]);
                if (! $remota) {
                    $anulacion->fill(['origen_confirmacion' => 'LOCAL', 'confirmado_por_id' => $usuarioId, 'fecha_confirmacion' => now()]);
                }
                $this->guardar($anulacion);
                if (! $remota) {
                    return ['local' => $this->aplicarDentroTransaccion($anulacion, $venta, $documento, $usuarioId)];
                }
                $intento = new IntentoAnulacionVenta([
                    'anulacion_venta_id' => $anulacion->id, 'usuario_id' => $usuarioId,
                    'solicitud' => $solicitud, 'resultado' => 'EN_PROCESO', 'fecha_inicio' => now(),
                ]);
                $this->guardar($intento);

                return ['anulacion_id' => $anulacion->id, 'intento_id' => $intento->id, 'solicitud' => $solicitud];
            }, 3);
            if (isset($reserva['local'])) {
                return $reserva['local'];
            }
            $s = $reserva['solicitud'];
            try {
                $respuesta = $this->cliente->anularDocumento($s['serie'], $s['numero'], $s['nit_comprador'], $s['fecha'], $s['motivo']);
            } catch (Throwable) {
                $respuesta = new RespuestaAinnova(RespuestaAinnova::TRANSPORTE, mensajeTecnico: 'La llamada de anulación se interrumpió.');
            }
            $anulacion = $this->registrarRespuesta($reserva, $respuesta, $usuarioId);

            return $anulacion->estado === 'CONFIRMADA' ? $this->aplicarConRecuperacion($anulacion, $usuarioId) : $anulacion;
        });
    }

    /**
     * Aplica un acuse positivo ya guardado; nunca envía otra solicitud a Ainnova.
     * Permite recuperar un fallo local posterior a la confirmación externa. La entrada,
     * el estado de venta y la marca de aplicación se confirman o revierten juntos.
     */
    public function aplicarConfirmada(int $ventaId, int $usuarioId): AnulacionVenta
    {
        return $this->conExclusion($ventaId, function () use ($ventaId, $usuarioId) {
            $anulacion = AnulacionVenta::where('venta_id', $ventaId)->firstOrFail();
            if ($anulacion->estado === 'APLICADA') {
                return $anulacion;
            }
            if ($anulacion->estado !== 'CONFIRMADA') {
                $this->rechazar('La anulación no tiene una confirmación válida pendiente de aplicar.');
            }

            return $this->aplicarConRecuperacion($anulacion, $usuarioId);
        });
    }

    /**
     * Registra el XML obtenido del certificador después de una comunicación incierta.
     * El operador debe confirmar su procedencia y aportar una referencia de soporte.
     * El parser comprueba identidad y resultado, no la autenticidad criptográfica de este
     * acuse sin firma. La responsabilidad y la evidencia quedan auditadas. No consulta
     * servicios, no acepta el error 94 como acuse y no reabre un envío todavía activo.
     */
    public function conciliarManual(int $ventaId, int $usuarioId, array $datos): AnulacionVenta
    {
        return $this->conExclusion($ventaId, function () use ($ventaId, $usuarioId, $datos) {
            $anulacion = DB::transaction(function () use ($ventaId, $usuarioId, $datos) {
                [$venta, $documento] = $this->bloquearVenta($ventaId);
                $anulacion = AnulacionVenta::where('venta_id', $ventaId)->lockForUpdate()->firstOrFail();
                $this->exigirFormularioActual($anulacion, (int) ($datos['ultimo_intento_anulacion_id'] ?? -1));
                $intento = $anulacion->intentos()->latest('id')->lockForUpdate()->first();
                $anulacion->setRelation('ultimoIntento', $intento);
                if (! $anulacion->puedeConciliar() || ! $intento || $venta->estado_venta !== 'CONFIRMADA') {
                    $this->rechazar('La anulación no admite conciliación manual o todavía tiene un envío activo.');
                }
                $this->exigirCertificada($venta, $documento);
                if (! filter_var($datos['confirmacion_ainnova'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || mb_strlen(trim($datos['referencia_soporte'] ?? '')) < 5 || mb_strlen($datos['referencia_soporte']) > 200) {
                    $this->rechazar('Confirme la procedencia del acuse e indique una referencia de soporte de 5 a 200 caracteres.');
                }
                $raw = $datos['respuesta_ainnova'] ?? '';
                if (! is_string($raw) || strlen($raw) > FelAnulacionRespuestaParserService::MAXIMO_BYTES
                    || $this->cliente->protegerTexto($raw) !== $raw
                    || $this->parser->procesar($raw, $intento->solicitud)['estado'] !== 'CONFIRMADA') {
                    $this->rechazar('La evidencia no contiene un acuse ANULADO válido para esta factura o incluye credenciales.');
                }
                if ($intento->resultado === 'EN_PROCESO') {
                    $intento->fill(['resultado' => 'INCIERTA', 'fecha_fin' => now(), 'mensaje' => 'Envío expirado cerrado durante la conciliación manual.']);
                    $this->guardar($intento);
                }
                $anulacion->fill([
                    'estado' => 'CONFIRMADA', 'confirmacion_raw' => $raw, 'confirmacion_hash' => hash('sha256', $raw),
                    'origen_confirmacion' => 'MANUAL', 'confirmado_por_id' => $usuarioId,
                    'referencia_soporte' => trim($datos['referencia_soporte']), 'fecha_confirmacion' => now(), 'ultimo_error' => null,
                ]);
                $this->guardar($anulacion);

                return $anulacion;
            }, 3);

            return $this->aplicarConRecuperacion($anulacion, $usuarioId);
        });
    }

    /**
     * Cierra el intento conservando su respuesta sin credenciales. Este commit ocurre antes
     * del reintegro: si falla la aplicación local, el acuse y su huella permanecen disponibles.
     * Una respuesta dudosa nunca cambia la venta ni el saldo de inventario.
     */
    private function registrarRespuesta(array $reserva, RespuestaAinnova $respuesta, int $usuarioId): AnulacionVenta
    {
        $raw = $respuesta->respuestaRaw ?? $respuesta->texto;
        $seguro = $this->cliente->protegerTexto($raw);
        if ($raw !== $seguro) {
            $resultado = ['estado' => 'INCIERTA', 'mensaje' => 'La respuesta incluyó credenciales que fueron ocultadas.'];
        } elseif (in_array($respuesta->tipo, [RespuestaAinnova::RESPUESTA, RespuestaAinnova::TEXTUAL], true)) {
            $resultado = $this->parser->procesar($seguro, $reserva['solicitud']);
        } else {
            $resultado = ['estado' => $respuesta->tipo === RespuestaAinnova::CONFIGURACION ? 'RECHAZADA' : 'INCIERTA',
                'mensaje' => 'La anulación no obtuvo confirmación: '.$respuesta->tipo.'.'];
        }

        return DB::transaction(function () use ($reserva, $seguro, $resultado, $usuarioId) {
            $anulacion = AnulacionVenta::whereKey($reserva['anulacion_id'])->lockForUpdate()->firstOrFail();
            $intento = $anulacion->intentos()->whereKey($reserva['intento_id'])->lockForUpdate()->firstOrFail();
            if ($anulacion->estado !== 'EN_PROCESO' || $intento->resultado !== 'EN_PROCESO' || $intento->fecha_fin !== null) {
                $this->rechazar('El intento de anulación ya fue cerrado. Revise el historial.');
            }
            $intento->fill(['resultado' => $resultado['estado'], 'respuesta_raw' => $seguro,
                'mensaje' => $resultado['mensaje'], 'fecha_fin' => now()]);
            $this->guardar($intento);
            $anulacion->fill(['estado' => $resultado['estado'], 'ultimo_error' => $resultado['estado'] === 'CONFIRMADA' ? null : $resultado['mensaje']]);
            if ($resultado['estado'] === 'CONFIRMADA') {
                $anulacion->fill(['confirmacion_raw' => $seguro, 'confirmacion_hash' => hash('sha256', $seguro),
                    'origen_confirmacion' => 'RESPUESTA', 'confirmado_por_id' => $usuarioId, 'fecha_confirmacion' => now()]);
            }
            $this->guardar($anulacion);

            return $anulacion;
        }, 3);
    }

    private function aplicarConRecuperacion(AnulacionVenta $anulacion, int $usuarioId): AnulacionVenta
    {
        try {
            return DB::transaction(function () use ($anulacion, $usuarioId) {
                [$venta, $documento] = $this->bloquearVenta($anulacion->venta_id);
                $actual = AnulacionVenta::whereKey($anulacion->id)->lockForUpdate()->firstOrFail();

                return $this->aplicarDentroTransaccion($actual, $venta, $documento, $usuarioId);
            }, 3);
        } catch (Throwable) {
            // El acuse ya está confirmado fuera de esta transacción y no se vuelve a enviar.
            $actual = $anulacion->fresh();
            $actual->ultimo_error = 'La confirmación está guardada; no se pudo completar el reintegro. Revise integridad y vuelva a aplicar.';
            $this->guardar($actual);

            return $actual;
        }
    }

    /**
     * Revalida el acuse guardado y aplica la cancelación con venta y documento bloqueados.
     * Bloquea inventarios en orden, comprueba los saldos agrupados y crea una ENTRADA por
     * salida histórica. La venta, el estado FEL y la marca de aplicación se guardan en la
     * misma transacción que el stock. La restricción por detalle y tipo evita reintegros dobles.
     */
    private function aplicarDentroTransaccion(AnulacionVenta $anulacion, Venta $venta, DocumentoFel $documento, int $usuarioId): AnulacionVenta
    {
        if ($anulacion->estado === 'APLICADA' && $venta->estado_venta === 'ANULADA') {
            return $anulacion;
        }
        if ($anulacion->estado !== 'CONFIRMADA' || $venta->estado_venta === 'ANULADA' || $anulacion->fecha_aplicacion !== null) {
            $this->rechazar('El estado de la venta o del reintegro no permite aplicar la anulación.');
        }
        if ($anulacion->tipo === 'FEL') {
            $this->exigirCertificada($venta, $documento);
            $s = $anulacion->solicitud;
            if (! is_string($anulacion->confirmacion_raw) || ! is_string($anulacion->confirmacion_hash)
                || ! hash_equals(hash('sha256', $anulacion->confirmacion_raw), $anulacion->confirmacion_hash)
                || $this->parser->procesar($anulacion->confirmacion_raw, $s)['estado'] !== 'CONFIRMADA'
                || $s['serie'] !== $documento->fel_serie || $s['numero'] !== $documento->fel_numero
                || $this->nit($s['nit_emisor']) !== $this->nit($documento->nit_emisor)
                || $this->nit($s['nit_comprador']) !== $this->nit($venta->receptor_identificacion)) {
                $this->rechazar('El acuse guardado no acredita la anulación de la factura original.');
            }
        } else {
            $this->exigirSinEmision($venta, $documento);
        }
        [$salidas, $totales] = $this->movimientosParaReintegro($venta);
        $finalizar = function () use ($anulacion, $venta, $documento, $usuarioId, $salidas, $totales) {
            foreach ($totales as $id => $cantidad) {
                $this->stock->comprobarAjuste($id, $cantidad);
            }
            foreach ($salidas as $salida) {
                $entrada = new MovimientoInventario([
                    'inventario_id' => $salida->inventario_id, 'detalle_venta_id' => $salida->detalle_venta_id,
                    'produccion_id' => null, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => $salida->cantidad,
                    'fecha_movimiento' => now(), 'motivo' => 'Anulación de venta #'.$venta->id.', solicitud #'.$anulacion->id.'.', 'estado' => true,
                ]);
                $this->guardar($entrada);
                $this->stock->entrada((int) $salida->inventario_id, $salida->cantidad);
            }
            if ($anulacion->tipo === 'FEL') {
                $documento->forceFill(['estado_fel' => 'ANULADA']);
                $this->guardar($documento);
            }
            // En cancelaciones locales se conserva el estado previo del documento no emitido.
            $cantidad = Venta::whereKey($venta->id)->where('estado_venta', $venta->estado_venta)->update([
                'estado_venta' => 'ANULADA', 'usuario_modificador_id' => $usuarioId, 'updated_at' => now(),
            ]);
            if ($cantidad !== 1) {
                throw new RuntimeException('No se pudo finalizar la venta anulada.');
            }
            $anulacion->fill(['estado' => 'APLICADA', 'fecha_aplicacion' => now(), 'aplicado_por_id' => $usuarioId, 'ultimo_error' => null]);
            $this->guardar($anulacion);

            return $anulacion->refresh();
        };

        return $totales ? $this->stock->conInventariosBloqueados(array_keys($totales), $finalizar) : $finalizar();
    }

    /**
     * Reconstruye el reintegro desde las salidas originales, no desde cantidades del navegador.
     * Agrupa inventarios antes de comprobar límites y exige una salida por línea sin entradas
     * previas. Se reutiliza el inventario histórico aunque esté inactivo, sin reactivarlo.
     */
    private function movimientosParaReintegro(Venta $venta): array
    {
        $detalles = $venta->detalles()->orderBy('id')->lockForUpdate()->get();
        $movimientos = MovimientoInventario::whereIn('detalle_venta_id', $detalles->modelKeys())->orderBy('id')->lockForUpdate()->get();
        if ($venta->estado_venta === 'BORRADOR') {
            if ($movimientos->isNotEmpty()) {
                $this->rechazar('El borrador tiene movimientos incompatibles; no puede cancelarse automáticamente.');
            }

            return [[], []];
        }
        if ($detalles->isEmpty() || $movimientos->count() !== $detalles->count()) {
            $this->rechazar('Las salidas originales están incompletas o ya existe un reintegro.');
        }
        $salidas = [];
        $totales = [];
        foreach ($detalles as $detalle) {
            $porLinea = $movimientos->where('detalle_venta_id', $detalle->id);
            $salida = $porLinea->count() === 1 ? $porLinea->first() : null;
            if (! $salida || $salida->tipo_movimiento !== 'SALIDA' || ! $salida->estado || $salida->produccion_id !== null
                || ! BigInteger::of($salida->cantidad)->isPositive()
                || ! BigInteger::of($salida->cantidad)->isEqualTo($detalle->cantidad)
                || (int) $salida->inventario()->value('producto_id') !== (int) $detalle->producto_id) {
                $this->rechazar('La salida histórica no corresponde a la línea de venta. No se alteró el inventario.');
            }
            $id = (int) $salida->inventario_id;
            $totales[$id] = (string) BigInteger::of($totales[$id] ?? '0')->plus($salida->cantidad);
            $salidas[] = $salida;
        }

        return [$salidas, $totales];
    }

    private function comprobarReintegro(Venta $venta): void
    {
        [, $totales] = $this->movimientosParaReintegro($venta);
        if ($totales) {
            $this->stock->conInventariosBloqueados(array_keys($totales), function () use ($totales) {
                foreach ($totales as $id => $cantidad) {
                    $this->stock->comprobarAjuste($id, $cantidad);
                }
            });
        }
    }

    private function exigirCertificada(Venta $venta, DocumentoFel $documento): void
    {
        if ($venta->estado_venta !== 'CONFIRMADA' || $documento->estado_fel !== 'CERTIFICADA'
            || ! $documento->fel_uuid || ! $documento->xml_certificado || ! $documento->fel_serie
            || ! $documento->fel_numero || ! $documento->nit_emisor
            || (int) $documento->tipo_documento_id !== (int) $venta->tipo_documento_id
            || $documento->intentos()->where('resultado', 'EN_PROCESO')->whereNull('fecha_fin')->exists()) {
            $this->rechazar('Se requiere una venta confirmada con certificación completa y sin envíos activos.');
        }
        if (! is_string($documento->xml_solicitud) || trim($documento->xml_solicitud) === ''
            || ! is_string($documento->hash_xml) || ! hash_equals(hash('sha256', $documento->xml_solicitud), $documento->hash_xml)) {
            $this->rechazar('El XML congelado de la factura no conserva su integridad. Revise el documento original.');
        }
        $certificacion = $this->parserCertificacion->procesar($documento->xml_certificado, $documento->nit_emisor, $venta->importe_total);
        if ($certificacion['estado'] !== 'CERTIFICADA'
            || strtoupper($certificacion['campos']['fel_uuid']) !== strtoupper($documento->fel_uuid)
            || $certificacion['campos']['fel_serie'] !== $documento->fel_serie
            || $certificacion['campos']['fel_numero'] !== $documento->fel_numero) {
            $this->rechazar('La identidad fiscal guardada no coincide con el XML certificado original.');
        }
    }

    /**
     * Acredita que no existe emisión antes de cancelar localmente. Un ERROR actual no basta:
     * todos los intentos deben estar cerrados y acreditar rechazo definitivo. Un timeout
     * histórico, una reserva abierta o un XML certificado impiden asumir que no hubo factura.
     */
    private function exigirSinEmision(Venta $venta, DocumentoFel $documento): void
    {
        if (! in_array($venta->estado_venta, ['BORRADOR', 'CONFIRMADA'], true)
            || ! in_array($documento->estado_fel, ['PENDIENTE', 'ERROR'], true)
            || $documento->fel_uuid !== null || $documento->xml_certificado !== null) {
            $this->rechazar('Primero debe resolverse si existe una factura certificada. No se permite la cancelación local.');
        }
        foreach (['fel_uuid_normalized', 'fel_serie', 'fel_numero', 'fecha_certificacion', 'nit_certificador', 'nombre_certificador'] as $campo) {
            if ($documento->$campo !== null) {
                $this->rechazar('El documento conserva datos de certificación que requieren revisión antes de cancelarlo.');
            }
        }
        $intentos = $documento->intentos()->lockForUpdate()->get();
        if ($documento->estado_fel === 'PENDIENTE' && $intentos->isNotEmpty()) {
            $this->rechazar('El documento pendiente tiene intentos de emisión que requieren revisión.');
        }
        if ($documento->estado_fel === 'ERROR') {
            if ($intentos->isEmpty() || ! $documento->nit_emisor) {
                $this->rechazar('No existe evidencia suficiente del rechazo definitivo.');
            }
            foreach ($intentos as $intento) {
                if ($intento->resultado !== 'ERROR' || $intento->fecha_fin === null
                    || $this->parserCertificacion->procesar($intento->respuesta_raw, $documento->nit_emisor, $venta->importe_total)['estado'] !== 'ERROR') {
                    $this->rechazar('El historial contiene una emisión incierta o no acredita rechazo definitivo.');
                }
            }
        }
        if ($venta->estado_venta === 'BORRADOR' && ($documento->estado_fel !== 'PENDIENTE' || $documento->xml_solicitud !== null || $documento->hash_xml !== null)) {
            $this->rechazar('El borrador tiene una solicitud FEL incompatible con la cancelación local.');
        }
    }

    /**
     * Comparte el bloqueo de certificación para excluir emisión, recuperación y anulación
     * simultáneas. Los estados persistidos impiden reenviar aunque el lock de caché expire.
     * Se exige comenzar fuera de otra transacción para que el envío ocurra tras el commit real.
     */
    private function conExclusion(int $ventaId, \Closure $operacion): AnulacionVenta
    {
        if (DB::connection()->transactionLevel() !== 0) {
            $this->rechazar('La anulación debe comenzar fuera de otra transacción.');
        }
        $documentoId = DocumentoFel::where('venta_id', $ventaId)->firstOrFail()->id;
        $bloqueo = Cache::lock('fel-documento-'.$documentoId, DocumentoFel::minutosIntentoExpirado() * 60);
        if (! $bloqueo->get()) {
            $this->rechazar('Ya hay una operación FEL activa para esta venta.');
        }
        try {
            return $operacion();
        } finally {
            $bloqueo->release();
        }
    }

    private function bloquearVenta(int $ventaId): array
    {
        $venta = Venta::whereKey($ventaId)->lockForUpdate()->firstOrFail();

        return [$venta, $venta->documentoFel()->lockForUpdate()->firstOrFail()];
    }

    private function exigirFormularioActual(?AnulacionVenta $anulacion, int $ultimoId): void
    {
        if ($ultimoId !== (int) ($anulacion?->intentos()->max('id') ?? 0)) {
            $this->rechazar('El historial de anulación cambió. Actualice la venta antes de continuar.');
        }
    }

    private function validarDatos(array $datos): void
    {
        $fecha = is_string($datos['fecha_anulacion'] ?? null) ? $datos['fecha_anulacion'] : '';
        $objeto = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $fecha) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
        if (! $objeto || $objeto->format('Y-m-d') !== $fecha || $fecha > now('America/Guatemala')->toDateString()
            || ! is_string($datos['motivo'] ?? null) || trim($datos['motivo']) === '' || mb_strlen($datos['motivo']) > 200
            || ! mb_check_encoding($datos['motivo'], 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $datos['motivo'])
            || (! is_int($datos['ultimo_intento_anulacion_id'] ?? null) && ! is_string($datos['ultimo_intento_anulacion_id'] ?? null))
            || ! preg_match('/^[0-9]+$/D', (string) $datos['ultimo_intento_anulacion_id'])) {
            $this->rechazar('Indique fecha válida no futura, motivo de hasta 200 caracteres y el último intento de anulación.');
        }
    }

    private function guardar(Model $modelo): void
    {
        if (! $modelo->save()) {
            throw new RuntimeException('No se pudo guardar un registro de la anulación.');
        }
    }

    private function nit(string $nit): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $nit));
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['anulacion' => $mensaje]);
    }
}
