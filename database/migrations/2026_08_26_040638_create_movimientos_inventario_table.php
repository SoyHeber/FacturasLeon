<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventario_id')
                ->constrained('inventarios')
                ->restrictOnDelete();

            $table->enum('tipo_movimiento', [
                'ENTRADA',
                'SALIDA',
                'AJUSTE',
            ]);

            // Debe ser signed porque AJUSTE puede ser negativo.
            $table->integer('cantidad');

            $table->dateTime('fecha_movimiento');

            $table->string('motivo', 150)->nullable();

            $table->text('observacion')->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

            $table->index('tipo_movimiento');
            $table->index('fecha_movimiento');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
