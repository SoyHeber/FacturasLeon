<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumos_produccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produccion_id')->constrained('producciones')->restrictOnDelete();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->foreignId('material_producto_id')->constrained('materiales_producto')->restrictOnDelete();

            // Copia de la receta y del material al aplicar la producción.
            $table->string('nombre_material', 100);
            $table->string('unidad_medida', 30);
            $table->decimal('cantidad_requerida', 20, 10);
            $table->unsignedInteger('cantidad_producida');
            $table->decimal('cantidad_consumida', 20, 10);
            $table->timestamps();

            $table->unique(['produccion_id', 'inventario_compra_id'], 'consumos_produccion_inventario_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('consumos_produccion')->exists()) {
            throw new RuntimeException('No se pueden retirar los consumos de producción: ya contienen información histórica.');
        }

        Schema::dropIfExists('consumos_produccion');
    }
};
