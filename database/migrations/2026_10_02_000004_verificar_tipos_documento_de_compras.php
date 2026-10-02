<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('compras')
            ->leftJoin('tipos_documento', 'tipos_documento.id', '=', 'compras.tipo_documento_id')
            ->select('compras.id', 'compras.tipo_documento_id', 'compras.tipo_dte', 'tipos_documento.codigo')
            ->chunkById(500, function ($compras) {
                foreach ($compras as $compra) {
                    if ($compra->tipo_documento_id === null) {
                        throw new RuntimeException(
                            'No se puede finalizar la transición: la compra #' . $compra->id
                            . ' tiene tipo_documento_id NULL. Complete la migración de datos primero.'
                        );
                    }

                    if ($compra->codigo === null) {
                        throw new RuntimeException(
                            'No se puede finalizar la transición: la compra #' . $compra->id
                            . ' apunta a un tipo de documento inexistente.'
                        );
                    }

                    if ($compra->codigo !== 'FACT') {
                        throw new RuntimeException(
                            'No se puede finalizar la transición: la compra #' . $compra->id
                            . ' está relacionada con un código diferente de FACT: ' . var_export($compra->codigo, true) . '.'
                        );
                    }

                    if ($compra->tipo_dte !== $compra->codigo) {
                        throw new RuntimeException(
                            'No se puede finalizar la transición: el tipo_dte de la compra #' . $compra->id
                            . ' no coincide con el catálogo. Revise los datos antes de eliminar la columna.'
                        );
                    }
                }
            }, 'compras.id', 'id');
    }

    public function down(): void
    {
        // Esta migración solo verifica datos.
    }
};
