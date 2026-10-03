<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__ . '/2026_10_02_000006_verificar_integridad_compras_e_inventario.php')->up();

        foreach (Schema::getIndexes('movimientos_inventario_compra') as $indice) {
            if ($indice['name'] === 'movimientos_compra_detalle_tipo_unique') {
                if (!$indice['unique'] || $indice['columns'] !== ['detalle_compra_id', 'tipo_movimiento']) {
                    throw new RuntimeException('El índice movimientos_compra_detalle_tipo_unique tiene una definición inesperada.');
                }

                return;
            }
        }

        Schema::table('movimientos_inventario_compra', function (Blueprint $table) {
            $table->unique(['detalle_compra_id', 'tipo_movimiento'], 'movimientos_compra_detalle_tipo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario_compra', function (Blueprint $table) {
            $table->dropUnique('movimientos_compra_detalle_tipo_unique');
        });
    }
};
