<?php

namespace App\Services;

use App\Models\Inventario;
use Brick\Math\BigInteger;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class InventarioService
{
    public const MAXIMO_SALDO = '18446744073709551615';

    public const MINIMO_MOVIMIENTO = '-9223372036854775808';

    public const MAXIMO_MOVIMIENTO = '9223372036854775807';

    private ?Collection $inventariosBloqueados = null;

    public function conInventariosBloqueados(array $inventarioIds, Closure $operacion): mixed
    {
        $modelo = new Inventario;
        $this->exigirTransaccion($modelo);

        if ($this->inventariosBloqueados !== null) {
            throw new LogicException('Ya existe un lote de inventarios bloqueados en este servicio.');
        }

        $inventarioIds = array_values(array_unique(array_map('intval', $inventarioIds)));
        sort($inventarioIds, SORT_NUMERIC);
        $inventarios = $modelo->newCollection();

        foreach ($inventarioIds as $id) {
            $inventario = $modelo->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            $inventarios->put($id, $inventario);
        }

        $this->inventariosBloqueados = $inventarios;

        try {
            return $operacion($inventarios);
        } finally {
            $this->inventariosBloqueados = null;
        }
    }

    public function normalizarCantidad(mixed $cantidad, string $campo = 'cantidad'): string
    {
        if ((! is_string($cantidad) && ! is_int($cantidad)) || strlen((string) $cantidad) > 80
            || ! preg_match('/^[+-]?[0-9]+$/D', (string) $cantidad)) {
            throw ValidationException::withMessages([$campo => 'La cantidad debe ser un entero exacto, sin decimales.']);
        }

        $entero = BigInteger::of($cantidad);
        if ($entero->abs()->isGreaterThan(self::MAXIMO_SALDO)) {
            throw ValidationException::withMessages([$campo => 'La cantidad excede el límite permitido.']);
        }

        return (string) $entero;
    }

    public function normalizarCantidadMovimiento(mixed $cantidad): string
    {
        $cantidad = BigInteger::of($this->normalizarCantidad($cantidad));
        if ($cantidad->isLessThan(self::MINIMO_MOVIMIENTO) || $cantidad->isGreaterThan(self::MAXIMO_MOVIMIENTO)) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad excede el límite permitido para un movimiento.']);
        }

        return (string) $cantidad;
    }

    public function entrada(int $inventarioId, mixed $cantidad): Inventario
    {
        return $this->ajustar($inventarioId, (string) $this->cantidadPositiva($cantidad));
    }

    public function salida(int $inventarioId, mixed $cantidad): Inventario
    {
        return $this->ajustar($inventarioId, (string) $this->cantidadPositiva($cantidad)->negated());
    }

    public function ajustar(int $inventarioId, mixed $delta): Inventario
    {
        $inventario = $this->bloquearInventario($inventarioId);
        $delta = BigInteger::of($this->normalizarCantidad($delta));
        if ($delta->isZero()) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser distinta de cero.']);
        }

        $inventario->cantidad = $this->calcularSaldo($inventario, $delta);
        if (! $inventario->save()) {
            throw new RuntimeException('No se pudo guardar el saldo del inventario.');
        }

        return $inventario;
    }

    public function comprobarAjuste(int $inventarioId, mixed $delta): void
    {
        $inventario = $this->bloquearInventario($inventarioId);
        $this->calcularSaldo($inventario, BigInteger::of($this->normalizarCantidad($delta)));
    }

    private function calcularSaldo(Inventario $inventario, BigInteger $delta): string
    {
        $actual = BigInteger::of($this->normalizarCantidad($inventario->cantidad));
        $this->validarSaldo($actual);
        $saldo = $actual->plus($delta);
        $this->validarSaldo($saldo);

        return (string) $saldo;
    }

    private function cantidadPositiva(mixed $cantidad): BigInteger
    {
        $cantidad = BigInteger::of($this->normalizarCantidad($cantidad));
        if (! $cantidad->isPositive()) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor a cero.']);
        }

        return $cantidad;
    }

    private function bloquearInventario(int $inventarioId): Inventario
    {
        $modelo = new Inventario;
        $this->exigirTransaccion($modelo);

        if ($this->inventariosBloqueados !== null) {
            $inventario = $this->inventariosBloqueados->get($inventarioId);
            if (! $inventario) {
                throw new LogicException('El inventario solicitado no pertenece al lote bloqueado.');
            }

            return $inventario;
        }

        return $modelo->newQuery()->whereKey($inventarioId)->lockForUpdate()->firstOrFail();
    }

    private function exigirTransaccion(Inventario $modelo): void
    {
        if ($modelo->getConnection()->transactionLevel() < 1) {
            throw new LogicException('El stock debe modificarse dentro de una transacción existente.');
        }
    }

    private function validarSaldo(BigInteger $saldo): void
    {
        if ($saldo->isNegative()) {
            throw ValidationException::withMessages(['cantidad' => 'La operación dejaría el inventario con una cantidad negativa.']);
        }
        if ($saldo->isGreaterThan(self::MAXIMO_SALDO)) {
            throw ValidationException::withMessages(['cantidad' => 'El saldo excede el límite permitido.']);
        }
    }
}
