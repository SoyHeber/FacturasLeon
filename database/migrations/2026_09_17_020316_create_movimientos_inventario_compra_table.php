<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventario_compra_id')
                ->constrained('inventarios_compra')
                ->restrictOnDelete();

            $table->foreignId('detalle_compra_id')
                ->nullable()
                ->constrained('detalles_compra')
                ->restrictOnDelete();

            $table->foreignId('produccion_id')
                ->nullable()
                ->constrained('producciones')
                ->restrictOnDelete();

            $table->enum('tipo_movimiento', [
                'ENTRADA',
                'SALIDA',
                'AJUSTE',
            ]);

            $table->decimal('cantidad', 14, 4);

            $table->dateTime('fecha_movimiento');

            $table->string('motivo', 150)
                ->nullable();

            $table->text('observacion')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->index('tipo_movimiento');
            $table->index('fecha_movimiento');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario_compra');
    }
};
