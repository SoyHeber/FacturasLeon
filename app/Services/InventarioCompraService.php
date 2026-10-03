<?php

namespace App\Services;

use App\Models\InventarioCompra;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventarioCompraService
{
    private ?Collection $inventariosBloqueados = null;

    public function conInventariosBloqueados(array $inventarioIds, Closure $operacion): mixed
    {
        $modelo = new InventarioCompra;
        $this->exigirTransaccion($modelo);

        if ($this->inventariosBloqueados !== null) {
            throw new LogicException('Ya existe un lote de inventarios bloqueados en este servicio.');
        }

        $inventarioIds = array_values(array_unique(array_map('intval', $inventarioIds)));
        sort($inventarioIds, SORT_NUMERIC);
        $inventarios = $modelo->newCollection();

        foreach ($inventarioIds as $inventarioId) {
            $inventario = $modelo->newQuery()->whereKey($inventarioId)->lockForUpdate()->first();

            if (!$inventario) {
                throw ValidationException::withMessages([
                    'detalles' => 'El inventario de compra #' . $inventarioId . ' ya no existe.',
                ]);
            }

            $inventarios->put($inventarioId, $inventario);
        }

        $this->inventariosBloqueados = $inventarios;

        try {
            return $operacion($inventarios);
        } finally {
            // Un reintento debe volver a leer los saldos y adquirir los bloqueos.
            $this->inventariosBloqueados = null;
        }
    }

    public function normalizarCantidad(mixed $cantidad, string $campo = 'cantidad'): string
    {
        if ((!is_string($cantidad) && !is_int($cantidad)) || strlen((string) $cantidad) > 80) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad debe enviarse como texto decimal o entero, sin utilizar float.',
            ]);
        }

        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d{1,2})?$/D', trim((string) $cantidad))) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad debe ser un decimal válido dentro del rango DECIMAL(20,10).',
            ]);
        }

        try {
            $decimal = BigDecimal::of(trim((string) $cantidad))->toScale(10);
        } catch (MathException $exception) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad debe ser un decimal exacto con hasta 10 decimales.',
            ]);
        }

        if ($decimal->abs()->isGreaterThan('9999999999.9999999999')) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad excede el rango permitido por DECIMAL(20,10).',
            ]);
        }

        return (string) $decimal;
    }

    public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
    {
        $cantidad = $this->cantidadPositiva($cantidad);

        return $this->ajustar($inventarioId, (string) $cantidad);
    }

    public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
    {
        $cantidad = $this->cantidadPositiva($cantidad);

        return $this->ajustar($inventarioId, (string) $cantidad->negated());
    }

    // Conserva los ajustes manuales y las reversiones existentes.
    public function ajustar(int $inventarioId, mixed $delta): InventarioCompra
    {
        $delta = BigDecimal::of($this->normalizarCantidad($delta));

        if ($delta->isZero()) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser distinta de cero.']);
        }

        $inventario = $this->bloquearInventario($inventarioId);
        $saldo = $this->calcularSaldo($inventario, $delta);

        return $this->guardarSaldo($inventario, $saldo);
    }

    public function comprobarAjuste(int $inventarioId, mixed $delta): void
    {
        $delta = BigDecimal::of($this->normalizarCantidad($delta));
        $this->calcularSaldo($this->bloquearInventario($inventarioId), $delta);
    }

    private function cantidadPositiva(mixed $cantidad): BigDecimal
    {
        $cantidad = BigDecimal::of($this->normalizarCantidad($cantidad));

        if (!$cantidad->isPositive()) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor a cero.']);
        }

        return $cantidad;
    }

    private function bloquearInventario(int $inventarioId): InventarioCompra
    {
        $modelo = new InventarioCompra;
        $this->exigirTransaccion($modelo);

        if ($this->inventariosBloqueados !== null) {
            $inventario = $this->inventariosBloqueados->get($inventarioId);

            if (!$inventario) {
                throw new LogicException('El inventario solicitado no pertenece al lote bloqueado.');
            }

            return $inventario;
        }

        return $modelo->newQuery()->whereKey($inventarioId)->lockForUpdate()->firstOrFail();
    }

    private function exigirTransaccion(InventarioCompra $modelo): void
    {
        if ($modelo->getConnection()->transactionLevel() < 1) {
            throw new LogicException('El stock debe modificarse dentro de una transacción existente.');
        }
    }

    private function calcularSaldo(InventarioCompra $inventario, BigDecimal $delta): string
    {
        $actual = $this->normalizarCantidad($inventario->cantidad);
        $this->validarSaldo($actual);
        $saldo = (string) BigDecimal::of($actual)->plus($delta);
        $this->validarSaldo($saldo);

        return $this->normalizarCantidad($saldo);
    }

    private function validarSaldo(string $saldo): void
    {
        if (BigDecimal::of($saldo)->isNegative()) {
            throw ValidationException::withMessages([
                'cantidad' => 'La operación dejaría el inventario con una cantidad negativa.',
            ]);
        }
    }

    private function guardarSaldo(InventarioCompra $inventario, string $saldo): InventarioCompra
    {
        $inventario->cantidad = $saldo;
        $inventario->save();

        return $inventario;
    }
}
