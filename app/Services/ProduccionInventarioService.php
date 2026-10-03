<?php

namespace App\Services;

use App\Models\ConsumoProduccion;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Models\Producto;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProduccionInventarioService
{
    public function __construct(
        private InventarioCompraService $inventarioCompraService,
        private CalculadoraProduccionService $calculadora
    ) {}

    public function anularProduccion(int $produccionId, int $usuarioId): Produccion
    {
        return DB::transaction(function () use ($produccionId, $usuarioId) {
            $produccion = Produccion::whereKey($produccionId)->lockForUpdate()->firstOrFail();
            if (! in_array($produccion->estado_produccion, [Produccion::BORRADOR, Produccion::CONFIRMADA], true)) {
                throw ValidationException::withMessages([
                    'produccion' => 'Solo se puede cancelar un BORRADOR o anular una CONFIRMADA. Las LEGADA son históricas y las ANULADA no pueden reactivarse.',
                ]);
            }
            if ($produccion->fecha_anulacion !== null || $produccion->anulado_por_id !== null) {
                throw ValidationException::withMessages(['produccion' => 'La producción ya tiene auditoría de anulación. Revise su historial.']);
            }

            $consumos = $produccion->consumos()->orderBy('id')->lockForUpdate()->get();
            $movimientos = MovimientoInventarioCompra::where(function ($query) use ($produccion, $consumos) {
                $query->where('produccion_id', $produccion->id)
                    ->orWhereIn('consumo_produccion_id', $consumos->modelKeys());
            })->orderBy('id')->lockForUpdate()->get();

            if ($produccion->estado_produccion === Produccion::BORRADOR) {
                if ($produccion->inventario_aplicado || $produccion->fecha_confirmacion !== null || $produccion->confirmado_por_id !== null
                    || $consumos->isNotEmpty() || $movimientos->contains(fn ($movimiento) => $movimiento->consumo_produccion_id !== null || $movimiento->detalle_compra_id !== null)) {
                    throw ValidationException::withMessages(['produccion' => 'El BORRADOR tiene inventario aplicado, consumos, movimientos automáticos o auditoría incompatible. No se puede cancelar.']);
                }

                $produccion->forceFill(['estado_produccion' => Produccion::ANULADA, 'fecha_anulacion' => now(), 'anulado_por_id' => $usuarioId])->save();

                return $produccion;
            }

            if (! $produccion->inventario_aplicado || $produccion->fecha_confirmacion === null || $produccion->confirmado_por_id === null || $consumos->isEmpty()) {
                throw ValidationException::withMessages(['produccion' => 'La CONFIRMADA no tiene inventario aplicado, auditoría o consumos completos para validar su devolución.']);
            }

            $idsConsumo = $consumos->modelKeys();
            if ($movimientos->contains(fn ($movimiento) => in_array((int) $movimiento->consumo_produccion_id, $idsConsumo, true) && $movimiento->tipo_movimiento === 'ENTRADA')) {
                throw ValidationException::withMessages(['produccion' => 'Ya existe una ENTRADA automática para uno de los consumos. No se puede devolver inventario dos veces.']);
            }
            if ($movimientos->contains(fn ($movimiento) => $movimiento->detalle_compra_id !== null
                || ($movimiento->consumo_produccion_id !== null && (! in_array((int) $movimiento->consumo_produccion_id, $idsConsumo, true) || $movimiento->tipo_movimiento !== 'SALIDA')))) {
                throw ValidationException::withMessages(['produccion' => 'Existen movimientos automáticos incompatibles con los consumos de la producción.']);
            }

            $salidas = $movimientos->where('tipo_movimiento', 'SALIDA')->groupBy('consumo_produccion_id');
            $devoluciones = [];
            $totales = [];
            foreach ($consumos as $consumo) {
                $originales = $salidas->get($consumo->id);
                if (! $originales || $originales->count() !== 1) {
                    throw ValidationException::withMessages(['produccion' => 'El consumo #'.$consumo->id.' debe tener exactamente una SALIDA automática original.']);
                }
                $salida = $originales->first();
                if (! $salida->estado || (int) $salida->consumo_produccion_id !== (int) $consumo->id
                    || (int) $salida->produccion_id !== (int) $produccion->id
                    || (int) $salida->inventario_compra_id !== (int) $consumo->inventario_compra_id || $salida->detalle_compra_id !== null) {
                    throw ValidationException::withMessages(['produccion' => 'La SALIDA original del consumo #'.$consumo->id.' debe estar activa y conservar sus vínculos de producción, consumo e inventario, sin detalle de Compra.']);
                }
                $cantidad = $this->inventarioCompraService->normalizarCantidad($salida->getRawOriginal('cantidad'), 'produccion');
                $cantidadConsumo = $this->inventarioCompraService->normalizarCantidad($consumo->getRawOriginal('cantidad_consumida'), 'produccion');
                if (! BigDecimal::of($cantidad)->isPositive() || ! BigDecimal::of($cantidad)->isEqualTo($cantidadConsumo)) {
                    throw ValidationException::withMessages(['produccion' => 'La cantidad de la SALIDA del consumo #'.$consumo->id.' debe ser positiva e idéntica al consumo histórico.']);
                }
                $id = (int) $salida->inventario_compra_id;
                $totales[$id] = (string) BigDecimal::of($totales[$id] ?? '0')->plus($cantidad);
                $devoluciones[] = ['consumo_id' => $consumo->id, 'inventario_id' => $id, 'cantidad' => $cantidad];
            }

            return $this->inventarioCompraService->conInventariosBloqueados(array_keys($totales), function () use ($produccion, $usuarioId, $totales, $devoluciones) {
                // Comprueba todas las sumas y saldos antes de registrar devoluciones.
                foreach ($totales as $id => $cantidad) {
                    $cantidad = $this->inventarioCompraService->normalizarCantidad($cantidad, 'produccion');
                    $this->inventarioCompraService->comprobarAjuste($id, $cantidad);
                }
                $fechaAnulacion = now();
                foreach ($devoluciones as $devolucion) {
                    MovimientoInventarioCompra::create([
                        'produccion_id' => $produccion->id,
                        'consumo_produccion_id' => $devolucion['consumo_id'],
                        'inventario_compra_id' => $devolucion['inventario_id'],
                        'detalle_compra_id' => null,
                        'tipo_movimiento' => 'ENTRADA',
                        'cantidad' => $devolucion['cantidad'],
                        'fecha_movimiento' => $fechaAnulacion,
                        'motivo' => 'Anulación de producción #'.$produccion->id.', devolución del consumo #'.$devolucion['consumo_id'].'.',
                        'estado' => true,
                    ]);
                    $this->inventarioCompraService->entrada($devolucion['inventario_id'], $devolucion['cantidad']);
                }
                $produccion->forceFill(['estado_produccion' => Produccion::ANULADA, 'fecha_anulacion' => $fechaAnulacion, 'anulado_por_id' => $usuarioId])->save();

                return $produccion;
            });
        }, 3);
    }

    public function confirmarProduccion(int $produccionId, int $usuarioId): Produccion
    {
        return DB::transaction(function () use ($produccionId, $usuarioId) {
            $produccion = Produccion::whereKey($produccionId)->lockForUpdate()->firstOrFail();

            if ($produccion->estado_produccion !== Produccion::BORRADOR || $produccion->inventario_aplicado || ! $produccion->estado) {
                throw ValidationException::withMessages([
                    'produccion' => 'Solo se puede confirmar una producción BORRADOR activa sin inventario aplicado.',
                ]);
            }
            foreach (['fecha_confirmacion', 'confirmado_por_id', 'fecha_anulacion', 'anulado_por_id'] as $campo) {
                if ($produccion->$campo !== null) {
                    throw ValidationException::withMessages([
                        'produccion' => 'La producción tiene auditoría previa incompatible con un BORRADOR.',
                    ]);
                }
            }

            Producto::whereKey($produccion->producto_id)->lockForUpdate()->firstOrFail();

            if (ConsumoProduccion::where('produccion_id', $produccion->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages([
                    'produccion' => 'La producción ya tiene consumos registrados. Revise su historial antes de continuar.',
                ]);
            }
            if (MovimientoInventarioCompra::where('produccion_id', $produccion->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages([
                    'produccion' => 'La producción ya tiene movimientos de inventario previos incompatibles con la confirmación.',
                ]);
            }

            // La receta se lee después de bloquear el producto en cada intento.
            $calculo = $this->calculadora->calcular($produccion);
            $totales = [];
            foreach ($calculo as $linea) {
                $id = (int) $linea['inventario_compra_id'];
                $totales[$id] = (string) BigDecimal::of($totales[$id] ?? '0')->plus($linea['cantidad_total']);
            }

            return $this->inventarioCompraService->conInventariosBloqueados(array_keys($totales), function ($inventarios) use ($produccion, $usuarioId, $calculo, $totales) {
                // Ningún consumo ni SALIDA se guarda hasta comprobar todos los saldos.
                foreach ($totales as $id => $cantidad) {
                    $inventario = $inventarios->get($id);
                    if (! $inventario->estado) {
                        throw ValidationException::withMessages([
                            'inventario' => 'El inventario '.$inventario->nombre.' está inactivo.',
                        ]);
                    }
                    $this->inventarioCompraService->comprobarAjuste($id, (string) BigDecimal::of($cantidad)->negated());
                }

                $fechaConfirmacion = now();
                foreach ($calculo as $linea) {
                    $inventario = $inventarios->get((int) $linea['inventario_compra_id']);
                    $consumo = ConsumoProduccion::create([
                        'produccion_id' => $produccion->id,
                        'inventario_compra_id' => $inventario->id,
                        'material_producto_id' => $linea['material_producto_id'],
                        'nombre_material' => $inventario->nombre,
                        'unidad_medida' => $inventario->unidad_medida,
                        'cantidad_requerida' => $linea['cantidad_por_unidad'],
                        'cantidad_producida' => $produccion->cantidad,
                        'cantidad_consumida' => $linea['cantidad_total'],
                    ]);

                    MovimientoInventarioCompra::create([
                        'produccion_id' => $produccion->id,
                        'consumo_produccion_id' => $consumo->id,
                        'inventario_compra_id' => $inventario->id,
                        'detalle_compra_id' => null,
                        'tipo_movimiento' => 'SALIDA',
                        'cantidad' => $linea['cantidad_total'],
                        'estado' => true,
                        'fecha_movimiento' => $fechaConfirmacion,
                        'motivo' => 'Consumo por producción #'.$produccion->id.', consumo #'.$consumo->id.'.',
                    ]);
                    $this->inventarioCompraService->salida((int) $inventario->id, $linea['cantidad_total']);
                }

                $produccion->forceFill([
                    'estado_produccion' => Produccion::CONFIRMADA,
                    'inventario_aplicado' => true,
                    'fecha_confirmacion' => $fechaConfirmacion,
                    'confirmado_por_id' => $usuarioId,
                ])->save();

                return $produccion;
            });
        }, 3);
    }
}
