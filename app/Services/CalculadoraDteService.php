<?php

namespace App\Services;

class CalculadoraDteService
{
    private const TASA_IVA = 0.12;

    /*
    |--------------------------------------------------------------------------
    | CALCULAR UNA LÍNEA
    |--------------------------------------------------------------------------
    |
    | El usuario proporciona:
    |
    | - cantidad
    | - precio_unitario
    | - porcentaje_descuento
    | - importe_exento
    | - importe_otros
    |
    | El servidor calcula:
    |
    | - importe_bruto
    | - importe_descuento
    | - importe_neto
    | - importe_iva
    | - importe_total
    |
    */

    public function calcularLinea(array $detalle): array
    {
        $cantidad = (float) ($detalle['cantidad'] ?? 0);

        $precioUnitario = (float) (
            $detalle['precio_unitario'] ?? 0
        );

        $porcentajeDescuento = (float) (
            $detalle['porcentaje_descuento'] ?? 0
        );

        $importeExento = $this->redondear(
            (float) ($detalle['importe_exento'] ?? 0)
        );

        $importeOtros = $this->redondear(
            (float) ($detalle['importe_otros'] ?? 0)
        );


        /*
        |--------------------------------------------------------------------------
        | 1. IMPORTE BRUTO
        |--------------------------------------------------------------------------
        |
        | BRUTO = CANTIDAD × PRECIO
        |
        */

        $importeBruto = $this->redondear(
            $cantidad * $precioUnitario
        );


        /*
        |--------------------------------------------------------------------------
        | 2. IMPORTE DESCUENTO
        |--------------------------------------------------------------------------
        |
        | DESCUENTO = BRUTO × (% DESCUENTO / 100)
        |
        */

        $importeDescuento = $this->redondear(
            $importeBruto *
            ($porcentajeDescuento / 100)
        );


        /*
        |--------------------------------------------------------------------------
        | 3. BASE AFECTA A IVA
        |--------------------------------------------------------------------------
        |
        | BASE =
        | BRUTO
        | - DESCUENTO
        | - EXENTO
        | - OTROS
        |
        */

        $baseAfecta = $this->redondear(
            $importeBruto
            - $importeDescuento
            - $importeExento
            - $importeOtros
        );


        /*
        |--------------------------------------------------------------------------
        | Evitar bases negativas
        |--------------------------------------------------------------------------
        */

        if ($baseAfecta < 0) {
            $baseAfecta = 0;
        }


        /*
        |--------------------------------------------------------------------------
        | 4. IMPORTE NETO
        |--------------------------------------------------------------------------
        |
        | NETO = BASE / 1.12
        |
        */

        $importeNeto = $this->redondear(
            $baseAfecta /
            (1 + self::TASA_IVA)
        );


        /*
        |--------------------------------------------------------------------------
        | 5. IVA
        |--------------------------------------------------------------------------
        |
        | IVA = BASE - NETO
        |
        */

        $importeIva = $this->redondear(
            $baseAfecta -
            $importeNeto
        );


        /*
        |--------------------------------------------------------------------------
        | 6. TOTAL
        |--------------------------------------------------------------------------
        |
        | TOTAL =
        | NETO
        | + IVA
        | + EXENTO
        | + OTROS
        |
        */

        $importeTotal = $this->redondear(
            $importeNeto
            + $importeIva
            + $importeExento
            + $importeOtros
        );


        return [
            'cantidad' => $cantidad,

            'precio_unitario' =>
                $precioUnitario,

            'porcentaje_descuento' =>
                $porcentajeDescuento,

            'importe_bruto' =>
                $importeBruto,

            'importe_descuento' =>
                $importeDescuento,

            'importe_exento' =>
                $importeExento,

            'importe_otros' =>
                $importeOtros,

            'importe_neto' =>
                $importeNeto,

            'importe_iva' =>
                $importeIva,

            'importe_total' =>
                $importeTotal,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR TOTALES DE TODA LA COMPRA
    |--------------------------------------------------------------------------
    */

    public function calcularTotales(array $detalles): array
    {
        $totales = [
            'importe_bruto' => 0,
            'importe_descuento' => 0,
            'importe_exento' => 0,
            'importe_otros' => 0,
            'importe_neto' => 0,
            'importe_iva' => 0,
            'importe_total' => 0,
        ];


        foreach ($detalles as $detalle) {

            $totales['importe_bruto'] +=
                (float) $detalle['importe_bruto'];

            $totales['importe_descuento'] +=
                (float) $detalle['importe_descuento'];

            $totales['importe_exento'] +=
                (float) $detalle['importe_exento'];

            $totales['importe_otros'] +=
                (float) $detalle['importe_otros'];

            $totales['importe_neto'] +=
                (float) $detalle['importe_neto'];

            $totales['importe_iva'] +=
                (float) $detalle['importe_iva'];

            $totales['importe_total'] +=
                (float) $detalle['importe_total'];
        }


        foreach ($totales as $campo => $valor) {

            $totales[$campo] =
                $this->redondear($valor);
        }


        return $totales;
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR COMPRA COMPLETA
    |--------------------------------------------------------------------------
    |
    | Este método será el que utilizaremos principalmente desde
    | CompraController.
    |
    */

    public function calcularCompra(array $detalles): array
    {
        $detallesCalculados = [];


        foreach ($detalles as $detalle) {

            $calculo =
                $this->calcularLinea(
                    $detalle
                );


            /*
            |--------------------------------------------------------------------------
            | Mantener información que no forma parte del cálculo
            |--------------------------------------------------------------------------
            */

            $detallesCalculados[] = array_merge(
                $detalle,
                $calculo
            );
        }


        return [
            'detalles' =>
                $detallesCalculados,

            'totales' =>
                $this->calcularTotales(
                    $detallesCalculados
                ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | REDONDEO
    |--------------------------------------------------------------------------
    |
    | Los importes monetarios utilizados por el flujo actual de Ainnova
    | quedan expresados a dos decimales.
    |
    */

    private function redondear(
        float $valor,
        int $decimales = 2
    ): float {
        return round(
            $valor,
            $decimales,
            PHP_ROUND_HALF_UP
        );
    }
}