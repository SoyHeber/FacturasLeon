<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiales_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('inventario_compra_id')
                ->constrained('inventarios_compra')
                ->restrictOnDelete();

            $table->decimal('cantidad_requerida', 14, 4);

            $table->string('observacion', 255)
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'producto_id',
                'inventario_compra_id',
            ]);

            $table->index('producto_id');
            $table->index('inventario_compra_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiales_producto');
    }
};
