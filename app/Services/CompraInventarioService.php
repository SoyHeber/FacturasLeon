<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\TipoDocumento;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraInventarioService
{
    private const IMPORTES = [
        'importe_bruto', 'importe_descuento', 'importe_exento', 'importe_otros',
        'importe_neto', 'importe_iva', 'importe_total',
    ];

    public function __construct(
        private InventarioCompraService $inventarioCompraService,
        private CalculadoraDteService $calculadoraDte
    ) {
    }

    public function anularCompra(int $compraId, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($compraId, $usuarioId) {
            $compra = Compra::whereKey($compraId)->lockForUpdate()->firstOrFail();

            if (!$compra->estado) {
                throw ValidationException::withMessages(['compra' => 'Esta compra ya fue anulada y no puede reactivarse.']);
            }

            if (!$compra->inventario_aplicado) {
                throw ValidationException::withMessages([
                    'compra' => 'Esta compra es histórica y no tiene inventario aplicado automáticamente. Debe conciliarse antes de poder anularse.',
                ]);
            }

            $detalles = $compra->detalles()->orderBy('id')->lockForUpdate()->get();

            if ($detalles->isEmpty()) {
                throw ValidationException::withMessages(['compra' => 'La compra no tiene detalles para comprobar sus entradas originales.']);
            }

            $salidaExistente = MovimientoInventarioCompra::whereIn('detalle_compra_id', $detalles->modelKeys())
                ->where('tipo_movimiento', 'SALIDA')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($salidaExistente) {
                throw ValidationException::withMessages([
                    'compra' => 'Esta compra ya tiene una SALIDA asociada al detalle #' . $salidaExistente->detalle_compra_id . '. Revise su historial antes de anularla.',
                ]);
            }

            $entradas = MovimientoInventarioCompra::whereIn('detalle_compra_id', $detalles->modelKeys())
                ->where('tipo_movimiento', 'ENTRADA')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('detalle_compra_id');

            $reversiones = [];
            $cantidadesPorInventario = [];

            foreach ($detalles as $detalle) {
                $entradasDetalle = $entradas->get($detalle->id);

                if (!$entradasDetalle || $entradasDetalle->count() !== 1) {
                    throw ValidationException::withMessages([
                        'compra' => 'El detalle #' . $detalle->id . ' debe tener exactamente una ENTRADA original para poder anularse.',
                    ]);
                }

                $entrada = $entradasDetalle->first();

                if (!$entrada->estado || $entrada->produccion_id !== null
                    || (int) $entrada->inventario_compra_id !== (int) $detalle->inventario_compra_id) {
                    throw ValidationException::withMessages([
                        'compra' => 'La ENTRADA original del detalle #' . $detalle->id . ' debe estar activa, sin producción y vinculada al mismo inventario.',
                    ]);
                }

                $cantidad = $this->inventarioCompraService->normalizarCantidad($entrada->getRawOriginal('cantidad'), 'compra');
                $cantidadDetalle = $this->inventarioCompraService->normalizarCantidad($detalle->getRawOriginal('cantidad'), 'compra');

                if (!BigDecimal::of($cantidad)->isPositive() || !BigDecimal::of($cantidad)->isEqualTo($cantidadDetalle)) {
                    throw ValidationException::withMessages([
                        'compra' => 'La cantidad de la ENTRADA original del detalle #' . $detalle->id . ' debe ser positiva e idéntica a la cantidad del detalle.',
                    ]);
                }

                $inventarioId = (int) $entrada->inventario_compra_id;
                $cantidadesPorInventario[$inventarioId] = ($cantidadesPorInventario[$inventarioId] ?? BigDecimal::zero())->plus($cantidad);
                $reversiones[] = ['detalle' => $detalle, 'inventario_id' => $inventarioId, 'cantidad' => $cantidad];
            }

            return $this->inventarioCompraService->conInventariosBloqueados(
                array_keys($cantidadesPorInventario),
                function (Collection $inventarios) use ($compra, $usuarioId, $reversiones, $cantidadesPorInventario) {
                    // Comprueba la suma de cada inventario antes de crear cualquier salida.
                    foreach ($cantidadesPorInventario as $inventarioId => $cantidad) {
                        $inventario = $inventarios->get($inventarioId);
                        $saldo = $this->inventarioCompraService->normalizarCantidad($inventario->cantidad, 'compra');

                        if ($cantidad->isGreaterThan($saldo)) {
                            throw ValidationException::withMessages([
                                'compra' => 'El inventario #' . $inventarioId . ' (' . $inventario->nombre . ') no tiene stock suficiente para anular todas las líneas de esta compra.',
                            ]);
                        }
                    }

                    $fechaAnulacion = now();

                    foreach ($reversiones as $reversion) {
                        $detalle = $reversion['detalle'];

                        MovimientoInventarioCompra::create([
                            'inventario_compra_id' => $reversion['inventario_id'],
                            'detalle_compra_id' => $detalle->id,
                            'produccion_id' => null,
                            'tipo_movimiento' => 'SALIDA',
                            'cantidad' => $reversion['cantidad'],
                            'fecha_movimiento' => $fechaAnulacion,
                            'motivo' => 'Anulación de compra #' . $compra->id . ', línea ' . $detalle->numero_linea . '.',
                            'estado' => true,
                        ]);

                        $this->inventarioCompraService->salida($reversion['inventario_id'], $reversion['cantidad']);
                    }

                    $compra->estado = false;
                    $compra->fecha_anulacion = $fechaAnulacion;
                    $compra->anulado_por_id = $usuarioId;
                    $compra->save();

                    return $compra;
                }
            );
        }, 3);
    }

    public function registrarCompra(array $datos, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            if (empty($datos['detalles']) || !is_array($datos['detalles'])) {
                throw ValidationException::withMessages(['detalles' => 'La compra debe incluir al menos un detalle.']);
            }

            $detalles = array_values($datos['detalles']);

            foreach ($detalles as $index => &$detalle) {
                $campo = 'detalles.' . $index . '.cantidad';
                $detalle['cantidad'] = $this->inventarioCompraService->normalizarCantidad($detalle['cantidad'] ?? null, $campo);

                if (!BigDecimal::of($detalle['cantidad'])->isPositive()) {
                    throw ValidationException::withMessages([$campo => 'La cantidad debe ser mayor a cero.']);
                }
            }
            unset($detalle);

            // La calculadora conserva el cálculo monetario actual; la cantidad se persiste como texto exacto.
            $calculo = $this->calculadoraDte->calcularCompra($detalles);
            $tipoDocumento = TipoDocumento::whereKey($datos['tipo_documento_id'])->sharedLock()->first();

            if (!$tipoDocumento || $tipoDocumento->codigo !== 'FACT' || !$tipoDocumento->estado) {
                throw ValidationException::withMessages(['tipo_documento_id' => 'Solo se permite FACT activo para registrar una compra.']);
            }

            return $this->inventarioCompraService->conInventariosBloqueados(
                array_column($detalles, 'inventario_compra_id'),
                function (Collection $inventarios) use ($datos, $usuarioId, $tipoDocumento, $detalles, $calculo) {
                    foreach ($detalles as $index => $detalle) {
                        if (!$inventarios->get((int) $detalle['inventario_compra_id'])->estado) {
                            throw ValidationException::withMessages([
                                'detalles.' . $index . '.inventario_compra_id' => 'El inventario seleccionado está inactivo y no puede recibir una compra nueva.',
                            ]);
                        }
                    }

                    $compra = new Compra(array_merge([
                        'proveedor_id' => $datos['proveedor_id'],
                        'user_id' => $usuarioId,
                        'tipo_documento_id' => $tipoDocumento->id,
                        'serie' => strtoupper($datos['serie']),
                        'numero' => $datos['numero'],
                        'numero_autorizacion' => strtoupper($datos['numero_autorizacion']),
                        'fecha_emision' => $datos['fecha_emision'],
                        'fecha_certificacion' => $datos['fecha_certificacion'] ?? null,
                        'moneda' => strtoupper($datos['moneda']),
                        'observacion' => $datos['observacion'] ?? null,
                        'estado' => true,
                    ], Arr::only($calculo['totales'], self::IMPORTES)));
                    $compra->inventario_aplicado = false;
                    $compra->save();

                    $detallesCreados = [];

                    // Todos los detalles existen antes de registrar las entradas.
                    foreach ($calculo['detalles'] as $index => $detalleCalculado) {
                        $detalle = DetalleCompra::create(array_merge([
                            'compra_id' => $compra->id,
                            'numero_linea' => $index + 1,
                            'inventario_compra_id' => $detalles[$index]['inventario_compra_id'],
                            'cantidad' => $detalles[$index]['cantidad'],
                            'precio_unitario' => $detalleCalculado['precio_unitario'],
                            'porcentaje_descuento' => $detalleCalculado['porcentaje_descuento'],
                            'observacion' => $detalles[$index]['observacion'] ?? null,
                            'estado' => true,
                        ], Arr::only($detalleCalculado, self::IMPORTES)));

                        $detallesCreados[] = $detalle->refresh();
                    }

                    foreach ($detallesCreados as $detalle) {
                        MovimientoInventarioCompra::create([
                            'inventario_compra_id' => $detalle->inventario_compra_id,
                            'detalle_compra_id' => $detalle->id,
                            'produccion_id' => null,
                            'tipo_movimiento' => 'ENTRADA',
                            'cantidad' => $detalle->cantidad,
                            'fecha_movimiento' => $compra->created_at,
                            'motivo' => 'Ingreso por compra #' . $compra->id . ', línea ' . $detalle->numero_linea . '.',
                            'estado' => true,
                        ]);

                        $this->inventarioCompraService->entrada((int) $detalle->inventario_compra_id, $detalle->cantidad);
                    }

                    $compra->inventario_aplicado = true;
                    $compra->save();

                    return $compra;
                }
            );
        }, 3);
    }
}
