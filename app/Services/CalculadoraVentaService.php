<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class CalculadoraVentaService
{
    public const TRATAMIENTOS = ['GRAVADO', 'EXENTO'];

    public const IMPORTES = ['importe_bruto', 'importe_descuento', 'importe_exento', 'importe_neto', 'importe_iva', 'importe_total'];

    private const MAXIMO_IMPORTE = '999999999999999999.99';

    public function calcularLinea(array $detalle): array
    {
        $valorCantidad = $detalle['cantidad'] ?? null;
        if ((! is_string($valorCantidad) && ! is_int($valorCantidad)) || strlen((string) $valorCantidad) > 19
            || ! preg_match('/^[0-9]+$/D', (string) $valorCantidad)) {
            $this->rechazar('cantidad', 'La cantidad debe ser un entero positivo.');
        }
        $cantidad = BigInteger::of($valorCantidad);
        if (! $cantidad->isPositive() || $cantidad->isGreaterThan('9223372036854775807')) {
            $this->rechazar('cantidad', 'La cantidad está fuera del rango permitido.');
        }
        $precio = $this->decimal($detalle['precio_unitario'] ?? null, 6, '99999999999999.999999', 'precio_unitario');
        $porcentaje = $this->decimal($detalle['porcentaje_descuento'] ?? '0', 4, '100', 'porcentaje_descuento');
        $tratamiento = $detalle['tratamiento_tributario'] ?? null;
        if (! in_array($tratamiento, self::TRATAMIENTOS, true)) {
            $this->rechazar('tratamiento_tributario', 'Seleccione GRAVADO o EXENTO.');
        }

        // El precio incluye IVA. Primero se redondean bruto y descuento a centavos.
        $bruto = $precio->multipliedBy($cantidad)->toScale(2, RoundingMode::HALF_UP);
        $descuento = $bruto->multipliedBy($porcentaje)->dividedBy('100', 2, RoundingMode::HALF_UP);
        $total = $bruto->minus($descuento);
        $neto = $tratamiento === 'GRAVADO' ? $total->dividedBy('1.12', 2, RoundingMode::HALF_UP) : BigDecimal::of('0.00');
        $iva = $tratamiento === 'GRAVADO' ? $total->minus($neto) : BigDecimal::of('0.00');
        $exento = $tratamiento === 'EXENTO' ? $total : BigDecimal::of('0.00');

        $resultado = [
            'cantidad' => (string) $cantidad,
            'precio_unitario' => (string) $precio->toScale(10),
            'porcentaje_descuento' => (string) $porcentaje->toScale(4),
            'tratamiento_tributario' => $tratamiento,
            'tasa_iva' => $tratamiento === 'GRAVADO' ? '12.0000' : '0.0000',
        ];
        foreach (['bruto' => $bruto, 'descuento' => $descuento, 'exento' => $exento, 'neto' => $neto, 'iva' => $iva, 'total' => $total] as $nombre => $importe) {
            $resultado['importe_'.$nombre] = $this->importe($importe);
        }

        return $resultado;
    }

    public function calcular(array $detalles): array
    {
        if ($detalles === []) {
            $this->rechazar('detalles', 'Agregue al menos un producto.');
        }
        $lineas = [];
        $totales = array_fill_keys(self::IMPORTES, '0.00');
        foreach (array_values($detalles) as $indice => $detalle) {
            try {
                $linea = $this->calcularLinea($detalle);
            } catch (ValidationException $exception) {
                $errores = [];
                foreach ($exception->errors() as $campo => $mensajes) {
                    $errores['detalles.'.$indice.'.'.$campo] = $mensajes;
                }
                throw ValidationException::withMessages($errores);
            }
            $lineas[] = $linea;
            foreach (self::IMPORTES as $campo) {
                $totales[$campo] = $this->importe(BigDecimal::of($totales[$campo])->plus($linea[$campo]));
            }
        }

        return ['detalles' => $lineas, 'totales' => $totales];
    }

    private function decimal(mixed $valor, int $escala, string $maximo, string $campo): BigDecimal
    {
        if ((! is_string($valor) && ! is_int($valor)) || strlen((string) $valor) > 40
            || ! preg_match('/^[0-9]+(?:\.[0-9]{1,'.$escala.'})?$/D', (string) $valor)) {
            $this->rechazar($campo, 'Use un decimal no negativo con un máximo de '.$escala.' decimales.');
        }
        $decimal = BigDecimal::of($valor);
        if ($decimal->isGreaterThan($maximo)) {
            $this->rechazar($campo, 'El valor excede el rango permitido.');
        }

        return $decimal;
    }

    private function importe(BigDecimal $importe): string
    {
        if ($importe->isNegative() || $importe->isGreaterThan(self::MAXIMO_IMPORTE)) {
            $this->rechazar('detalles', 'Los importes exceden la precisión monetaria permitida.');
        }

        return (string) $importe->toScale(2);
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
