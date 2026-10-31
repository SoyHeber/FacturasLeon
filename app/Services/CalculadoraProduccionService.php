<?php

namespace App\Services;

use App\Models\MaterialProducto;
use App\Models\Produccion;
use App\Models\Producto;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;

class CalculadoraProduccionService
{
    private const MAXIMO_DECIMAL = '9999999999.9999999999';

    public function normalizarCantidadProduccion(mixed $cantidad): string
    {
        if ((! is_string($cantidad) && ! is_int($cantidad))
            || ! preg_match('/^[0-9]{1,10}$/D', (string) $cantidad)
            || ! BigDecimal::of($cantidad)->isPositive()
            || BigDecimal::of($cantidad)->isGreaterThan('4294967295')) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser un entero positivo de hasta 4294967295 unidades.',
            ]);
        }

        return (string) BigDecimal::of($cantidad)->toScale(0);
    }

    public function normalizarCantidadRequerida(mixed $cantidad): string
    {
        if ((! is_string($cantidad) && ! is_int($cantidad)) || strlen((string) $cantidad) > 80
            || ! preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/D', (string) $cantidad)) {
            throw ValidationException::withMessages([
                'cantidad_requerida' => 'La cantidad por unidad debe ser un decimal exacto enviado como texto o entero.',
            ]);
        }

        try {
            $decimal = BigDecimal::of($cantidad)->toScale(10);
        } catch (MathException $exception) {
            throw ValidationException::withMessages([
                'cantidad_requerida' => 'La cantidad por unidad debe tener hasta 10 decimales, sin redondeo.',
            ]);
        }

        if (! $decimal->isPositive() || $decimal->isGreaterThan(self::MAXIMO_DECIMAL)) {
            throw ValidationException::withMessages([
                'cantidad_requerida' => 'La cantidad por unidad debe ser positiva y caber en DECIMAL(20,10).',
            ]);
        }

        return (string) $decimal;
    }

    /**
     * Calcula el consumo de una producción con la receta vigente, sin mover existencias.
     * Consulta el estado actual del producto para rechazar una receta inactiva aunque
     * el llamador conserve una relación cargada antes del cambio. Multiplica cantidades
     * por unidad con hasta 10 decimales exactos y rechaza vacíos o desbordamientos.
     * La confirmación utiliza este cálculo bajo el bloqueo del producto; las anulaciones
     * conservan sus consumos históricos y no dependen de la disponibilidad de la receta.
     */
    public function calcular(Produccion $produccion): array
    {
        $cantidad = $this->normalizarCantidadProduccion($produccion->getAttributes()['cantidad'] ?? null);
        $producto = Producto::findOrFail($produccion->producto_id);
        if (! $producto->receta_activa) {
            throw ValidationException::withMessages(['receta' => 'La receta del producto está inactiva. Actívela antes de utilizarla en producción.']);
        }
        $materiales = MaterialProducto::with('inventarioCompra')
            ->where('producto_id', $produccion->producto_id)
            ->where('estado', true)
            ->orderBy('inventario_compra_id')
            ->get();

        if ($materiales->isEmpty()) {
            throw ValidationException::withMessages([
                'receta' => 'El producto no tiene una receta con materiales activos.',
            ]);
        }

        $estimacion = [];
        foreach ($materiales as $material) {
            if (! $material->inventarioCompra) {
                throw ValidationException::withMessages([
                    'receta' => 'El inventario del material de receta #'.$material->id.' ya no existe.',
                ]);
            }

            // Se valida el valor original para no ocultar errores mediante el cast.
            $porUnidad = $this->normalizarCantidadRequerida($material->getRawOriginal('cantidad_requerida'));
            $total = BigDecimal::of($porUnidad)->multipliedBy($cantidad)->toScale(10);
            if ($total->isGreaterThan(self::MAXIMO_DECIMAL)) {
                throw ValidationException::withMessages([
                    'receta' => 'La cantidad total estimada de '.$material->inventarioCompra->nombre.' excede DECIMAL(20,10).',
                ]);
            }

            $estimacion[] = [
                'material_producto_id' => $material->id,
                'inventario_compra_id' => $material->inventario_compra_id,
                'material' => $material->inventarioCompra->nombre,
                'unidad' => $material->inventarioCompra->unidad_medida,
                'cantidad_por_unidad' => $porUnidad,
                'cantidad_total' => (string) $total,
            ];
        }

        return $estimacion;
    }
}
