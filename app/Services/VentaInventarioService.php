<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VentaInventarioService
{
    public function __construct(
        private InventarioService $inventarioService = new InventarioService,
        private CalculadoraVentaService $calculadora = new CalculadoraVentaService,
        private FelXmlBuilderService $xmlBuilder = new FelXmlBuilderService
    ) {}

    public function confirmarVenta(int $ventaId, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($ventaId, $usuarioId) {
            $venta = Venta::whereKey($ventaId)->lockForUpdate()->firstOrFail();
            $venta->exigirBorrador();
            $detalles = $venta->detalles()->lockForUpdate()->get();
            if ($detalles->isEmpty()) {
                $this->rechazar('Agregue al menos un detalle antes de confirmar.');
            }
            $documento = $venta->documentoFel()->lockForUpdate()->firstOrFail();
            if ($documento->estado_fel !== 'PENDIENTE' || $documento->xml_solicitud !== null || $documento->hash_xml !== null
                || $documento->p_tipo_doc !== 1 || $documento->p_tipo_respuesta !== 'D'
                || (int) $documento->tipo_documento_id !== (int) $venta->tipo_documento_id) {
                $this->rechazar('El documento FEL no corresponde a un borrador FACT pendiente sin XML.');
            }
            if (MovimientoInventario::whereIn('detalle_venta_id', $detalles->modelKeys())->orderBy('id')->lockForUpdate()->first()) {
                $this->rechazar('La venta ya tiene movimientos automáticos incompatibles con un borrador.');
            }
            $datos = [];
            foreach ($detalles as $detalle) {
                if ($detalle->bien_servicio !== 'B' || $detalle->unidad_medida !== 'UNI') {
                    $this->rechazar('Solo se pueden confirmar productos terminados de clase B y unidad UNI.');
                }
                $datos[] = [
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => (string) BigDecimal::of($detalle->precio_unitario)->stripTrailingZeros(),
                    'porcentaje_descuento' => $detalle->porcentaje_descuento,
                    'tratamiento_tributario' => $detalle->tratamiento_tributario,
                ];
            }
            $calculo = $this->calculadora->calcular($datos);
            $porProducto = Inventario::whereIn('producto_id', $detalles->pluck('producto_id')->unique())->get()->keyBy('producto_id');
            $totales = [];
            $inventarioPorDetalle = [];
            foreach ($detalles as $detalle) {
                $inventario = $porProducto->get($detalle->producto_id);
                if ($inventario === null) {
                    $this->rechazar('El producto '.$detalle->producto_codigo.' no tiene inventario.');
                }
                $id = (int) $inventario->id;
                $inventarioPorDetalle[$detalle->id] = $id;
                $totales[$id] = (string) BigInteger::of($totales[$id] ?? '0')->plus($detalle->cantidad);
            }

            return $this->inventarioService->conInventariosBloqueados(array_keys($totales), function ($inventarios) use ($venta, $usuarioId, $detalles, $documento, $calculo, $totales, $inventarioPorDetalle) {
                // Comprueba todos los saldos agrupados antes de crear una SALIDA.
                foreach ($totales as $id => $cantidad) {
                    $inventario = $inventarios->get($id);
                    if (! $inventario->estado) {
                        $this->rechazar('El inventario #'.$id.' está inactivo.');
                    }
                    $this->inventarioService->comprobarAjuste($id, (string) BigInteger::of($cantidad)->negated());
                }
                foreach ($detalles as $detalle) {
                    $idInventario = $inventarioPorDetalle[$detalle->id];
                    if ((int) $inventarios->get($idInventario)->producto_id !== (int) $detalle->producto_id) {
                        $this->rechazar('El inventario bloqueado no corresponde al producto del detalle.');
                    }
                }
                $fechaConfirmacion = now();
                foreach ($detalles as $indice => $detalle) {
                    $idInventario = $inventarioPorDetalle[$detalle->id];
                    if (! $detalle->fill($calculo['detalles'][$indice])->save()) {
                        throw new RuntimeException('No se pudieron guardar los cálculos definitivos del detalle.');
                    }
                    $salida = MovimientoInventario::create([
                        'inventario_id' => $idInventario, 'detalle_venta_id' => $detalle->id, 'produccion_id' => null,
                        'tipo_movimiento' => 'SALIDA', 'cantidad' => $detalle->cantidad, 'estado' => true,
                        'fecha_movimiento' => $fechaConfirmacion,
                        'motivo' => 'Confirmación de venta #'.$venta->id.', línea '.$detalle->numero_linea.'.',
                    ]);
                    if (! $salida->exists) {
                        throw new RuntimeException('No se pudo registrar la SALIDA de la venta.');
                    }
                    $this->inventarioService->salida($idInventario, $detalle->cantidad);
                }
                $venta->fill($calculo['totales'])->setRelation('detalles', $detalles);
                $xml = $this->xmlBuilder->generarFactura($venta, $documento);
                if (! $documento->forceFill([
                    'xml_solicitud' => $xml, 'hash_xml' => hash('sha256', $xml),
                    'p_tipo_doc' => 1, 'p_tipo_respuesta' => 'D', 'estado_fel' => 'PENDIENTE',
                    'fecha_hora_emision' => $fechaConfirmacion,
                ])->save()) {
                    throw new RuntimeException('No se pudo congelar el documento FEL.');
                }

                // Esta transición se realiza solo aquí, con stock y XML guardados y la venta bloqueada.
                $actualizadas = Venta::whereKey($venta->id)->where('estado_venta', 'BORRADOR')->update(array_merge(
                    $calculo['totales'], ['estado_venta' => 'CONFIRMADA', 'usuario_modificador_id' => $usuarioId, 'updated_at' => $fechaConfirmacion]
                ));
                if ($actualizadas !== 1) {
                    throw new RuntimeException('No se pudo finalizar la confirmación de la venta.');
                }

                return $venta->refresh()->load(['detalles', 'documentoFel']);
            });
        }, 3);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['venta' => $mensaje]);
    }
}
