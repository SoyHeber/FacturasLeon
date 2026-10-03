<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producciones', function (Blueprint $table) {
            $table->boolean('producto_terminado_aplicado')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('producciones')->where('producto_terminado_aplicado', '<>', 0)->exists()) {
            throw new RuntimeException('No se puede retirar el indicador: existen producciones con producto terminado aplicado.');
        }
        Schema::table('producciones', function (Blueprint $table) {
            $table->dropColumn('producto_terminado_aplicado');
        });
    }
};
