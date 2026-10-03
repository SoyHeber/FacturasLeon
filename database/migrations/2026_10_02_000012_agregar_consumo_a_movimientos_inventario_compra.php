<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario_compra', function (Blueprint $table) {
            $table->foreignId('consumo_produccion_id')->nullable()->constrained('consumos_produccion')->restrictOnDelete();
            $table->unique(['consumo_produccion_id', 'tipo_movimiento'], 'movimientos_consumo_tipo_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('movimientos_inventario_compra')->whereNotNull('consumo_produccion_id')->exists()) {
            throw new RuntimeException('No se puede retirar el vínculo a consumos: ya contiene información histórica.');
        }

        Schema::table('movimientos_inventario_compra', function (Blueprint $table) {
            $table->dropForeign(['consumo_produccion_id']);
            $table->dropUnique('movimientos_consumo_tipo_unique');
            $table->dropColumn('consumo_produccion_id');
        });
    }
};
