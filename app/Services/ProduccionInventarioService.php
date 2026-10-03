<?php

namespace App\Services;

use App\Models\ConsumoProduccion;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Models\Producto;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProduccionInventarioService
{
    public function __construct(
        private InventarioCompraService $inventarioCompraService,
        private CalculadoraProduccionService $calculadora,
        private InventarioService $inventarioService = new InventarioService
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

            Producto::whereKey($produccion->producto_id)->lockForUpdate()->firstOrFail();

            $consumos = $produccion->consumos()->orderBy('id')->lockForUpdate()->get();
            $movimientos = MovimientoInventarioCompra::where(function ($query) use ($produccion, $consumos) {
                $query->where('produccion_id', $produccion->id)
                    ->orWhereIn('consumo_produccion_id', $consumos->modelKeys());
            })->orderBy('id')->lockForUpdate()->get();

            if ($produccion->estado_produccion === Produccion::BORRADOR) {
                if ($produccion->inventario_aplicado || $produccion->producto_terminado_aplicado
                    || $produccion->movimientosInventario()->lockForUpdate()->first()
                    || $produccion->fecha_confirmacion !== null || $produccion->confirmado_por_id !== null
                    || $consumos->isNotEmpty() || $movimientos->contains(fn ($movimiento) => $movimiento->consumo_produccion_id !== null || $movimiento->detalle_compra_id !== null)) {
                    throw ValidationException::withMessages(['produccion' => 'El BORRADOR tiene inventario aplicado, consumos, movimientos automáticos o auditoría incompatible. No se puede cancelar.']);
                }

                if (! $produccion->forceFill(['estado_produccion' => Produccion::ANULADA, 'fecha_anulacion' => now(), 'anulado_por_id' => $usuarioId])->save()) {
                    throw new RuntimeException('No se pudo cancelar la producción.');
                }

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

            $entradaTerminada = $produccion->producto_terminado_aplicado ? $this->entradaTerminadaOriginal($produccion) : null;
            $devolverMateriasPrimas = function () use ($produccion, $usuarioId, $totales, $devoluciones, $entradaTerminada) {
                return $this->inventarioCompraService->conInventariosBloqueados(array_keys($totales), function () use ($produccion, $usuarioId, $totales, $devoluciones, $entradaTerminada) {
                    // Comprueba todas las sumas y saldos antes de registrar devoluciones.
                    foreach ($totales as $id => $cantidad) {
                        $cantidad = $this->inventarioCompraService->normalizarCantidad($cantidad, 'produccion');
                        $this->inventarioCompraService->comprobarAjuste($id, $cantidad);
                    }
                    $fechaAnulacion = now();
                    if ($entradaTerminada !== null) {
                        $cantidadTerminada = $this->inventarioService->normalizarCantidadMovimiento($entradaTerminada->getRawOriginal('cantidad'));
                        $salidaTerminada = MovimientoInventario::create([
                            'produccion_id' => $produccion->id,
                            'inventario_id' => $entradaTerminada->inventario_id,
                            'tipo_movimiento' => 'SALIDA',
                            'cantidad' => $cantidadTerminada,
                            'fecha_movimiento' => $fechaAnulacion,
                            'motivo' => 'Anulación de producción #'.$produccion->id.', retiro de producto terminado.',
                            'estado' => true,
                        ]);
                        if (! $salidaTerminada->exists) {
                            throw new RuntimeException('No se pudo registrar la SALIDA de producto terminado.');
                        }
                        $this->inventarioService->salida((int) $entradaTerminada->inventario_id, $cantidadTerminada);
                    }
                    foreach ($devoluciones as $devolucion) {
                        $entradaMateriaPrima = MovimientoInventarioCompra::create([
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
                        if (! $entradaMateriaPrima->exists) {
                            throw new RuntimeException('No se pudo registrar la devolución de materia prima.');
                        }
                        if ($this->inventarioCompraService->entrada($devolucion['inventario_id'], $devolucion['cantidad'])->isDirty('cantidad')) {
                            throw new RuntimeException('No se pudo guardar el saldo de materia prima.');
                        }
                    }
                    if (! $produccion->forceFill(['estado_produccion' => Produccion::ANULADA, 'fecha_anulacion' => $fechaAnulacion, 'anulado_por_id' => $usuarioId])->save()) {
                        throw new RuntimeException('No se pudo finalizar la anulación.');
                    }

                    return $produccion;
                });
            };

            if ($entradaTerminada === null) {
                return $devolverMateriasPrimas();
            }

            return $this->inventarioService->conInventariosBloqueados([(int) $entradaTerminada->inventario_id], function ($inventarios) use ($produccion, $entradaTerminada, $devolverMateriasPrimas) {
                $inventario = $inventarios->get((int) $entradaTerminada->inventario_id);
                if ((int) $inventario->producto_id !== (int) $produccion->producto_id) {
                    throw ValidationException::withMessages(['produccion' => 'La ENTRADA original no corresponde al inventario del producto producido.']);
                }
                $cantidad = $this->inventarioService->normalizarCantidadMovimiento($entradaTerminada->getRawOriginal('cantidad'));
                $this->inventarioService->comprobarAjuste((int) $inventario->id, (string) BigInteger::of($cantidad)->negated());

                return $devolverMateriasPrimas();
            });
        }, 3);
    }

    public function confirmarProduccion(int $produccionId, int $usuarioId): Produccion
    {
        return DB::transaction(function () use ($produccionId, $usuarioId) {
            $produccion = Produccion::whereKey($produccionId)->lockForUpdate()->firstOrFail();

            if ($produccion->estado_produccion !== Produccion::BORRADOR || $produccion->inventario_aplicado
                || $produccion->producto_terminado_aplicado !== false || ! $produccion->estado) {
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

            $cantidadTerminada = $this->cantidadTerminada($produccion);
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
            if ($produccion->movimientosInventario()->orderBy('id')->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['produccion' => 'La producción ya tiene movimientos de producto terminado incompatibles con la confirmación.']);
            }

            // La receta se lee después de bloquear el producto en cada intento.
            $calculo = $this->calculadora->calcular($produccion);
            $totales = [];
            foreach ($calculo as $linea) {
                $id = (int) $linea['inventario_compra_id'];
                $totales[$id] = (string) BigDecimal::of($totales[$id] ?? '0')->plus($linea['cantidad_total']);
            }

            // El Producto permanece bloqueado hasta terminar; dos confirmaciones no crean dos inventarios.
            $inventarioTerminado = Inventario::where('producto_id', $produccion->producto_id)->first();
            if ($inventarioTerminado === null) {
                $inventarioTerminado = Inventario::create(['producto_id' => $produccion->producto_id, 'estado' => true]);
            }

            return $this->inventarioService->conInventariosBloqueados([(int) $inventarioTerminado->id], function ($inventariosTerminados) use ($produccion, $usuarioId, $calculo, $totales, $cantidadTerminada, $inventarioTerminado) {
                $inventarioTerminado = $inventariosTerminados->get((int) $inventarioTerminado->id);
                if ((int) $inventarioTerminado->producto_id !== (int) $produccion->producto_id) {
                    throw ValidationException::withMessages(['inventario' => 'El inventario no corresponde al producto de la producción.']);
                }
                if (! $inventarioTerminado->estado) {
                    throw ValidationException::withMessages(['inventario' => 'El inventario del producto terminado está inactivo.']);
                }
                $this->inventarioService->comprobarAjuste((int) $inventarioTerminado->id, $cantidadTerminada);

                return $this->inventarioCompraService->conInventariosBloqueados(array_keys($totales), function ($inventarios) use ($produccion, $usuarioId, $calculo, $totales, $cantidadTerminada, $inventarioTerminado) {
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
                        if (! $consumo->exists) {
                            throw new RuntimeException('No se pudo registrar el consumo de materia prima.');
                        }

                        $salidaMateriaPrima = MovimientoInventarioCompra::create([
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
                        if (! $salidaMateriaPrima->exists) {
                            throw new RuntimeException('No se pudo registrar la SALIDA de materia prima.');
                        }
                        if ($this->inventarioCompraService->salida((int) $inventario->id, $linea['cantidad_total'])->isDirty('cantidad')) {
                            throw new RuntimeException('No se pudo guardar el saldo de materia prima.');
                        }
                    }

                    $entradaTerminada = MovimientoInventario::create([
                        'produccion_id' => $produccion->id,
                        'inventario_id' => $inventarioTerminado->id,
                        'tipo_movimiento' => 'ENTRADA',
                        'cantidad' => $cantidadTerminada,
                        'estado' => true,
                        'fecha_movimiento' => $fechaConfirmacion,
                        'motivo' => 'Confirmación de producción #'.$produccion->id.', ingreso de producto terminado.',
                    ]);
                    if (! $entradaTerminada->exists) {
                        throw new RuntimeException('No se pudo registrar la ENTRADA de producto terminado.');
                    }
                    $this->inventarioService->entrada((int) $inventarioTerminado->id, $cantidadTerminada);

                    if (! $produccion->forceFill([
                        'estado_produccion' => Produccion::CONFIRMADA,
                        'inventario_aplicado' => true,
                        'producto_terminado_aplicado' => true,
                        'fecha_confirmacion' => $fechaConfirmacion,
                        'confirmado_por_id' => $usuarioId,
                    ])->save()) {
                        throw new RuntimeException('No se pudo finalizar la confirmación.');
                    }

                    return $produccion;
                });
            });
        }, 3);
    }

    private function cantidadTerminada(Produccion $produccion): string
    {
        $cantidad = $this->inventarioService->normalizarCantidadMovimiento($produccion->getRawOriginal('cantidad'));
        if (! BigInteger::of($cantidad)->isPositive()) {
            throw ValidationException::withMessages(['produccion' => 'La cantidad producida debe ser un entero positivo.']);
        }

        return $cantidad;
    }

    private function entradaTerminadaOriginal(Produccion $produccion): MovimientoInventario
    {
        $movimientos = $produccion->movimientosInventario()->orderBy('id')->lockForUpdate()->get();
        if ($movimientos->contains('tipo_movimiento', 'SALIDA')) {
            throw ValidationException::withMessages(['produccion' => 'Ya existe una SALIDA compensatoria de producto terminado.']);
        }
        $entradas = $movimientos->where('tipo_movimiento', 'ENTRADA');
        if ($entradas->count() !== 1 || $movimientos->count() !== 1) {
            throw ValidationException::withMessages(['produccion' => 'La producción debe conservar exactamente una ENTRADA automática original de producto terminado.']);
        }
        $entrada = $entradas->first();
        $cantidad = $this->inventarioService->normalizarCantidadMovimiento($entrada->getRawOriginal('cantidad'));
        if (! $entrada->estado || (int) $entrada->produccion_id !== (int) $produccion->id
            || ! BigInteger::of($cantidad)->isPositive() || ! BigInteger::of($cantidad)->isEqualTo($this->cantidadTerminada($produccion))) {
            throw ValidationException::withMessages(['produccion' => 'La ENTRADA original debe estar activa y conservar la cantidad y el vínculo de la producción.']);
        }

        return $entrada;
    }
}
