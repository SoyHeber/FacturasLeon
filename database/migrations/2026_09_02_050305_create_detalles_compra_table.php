<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('compra_id')
                ->constrained('compras')
                ->restrictOnDelete();

            $table->foreignId('inventario_compra_id')
                ->constrained('inventarios_compra')
                ->restrictOnDelete();

            $table->decimal('cantidad', 14, 4);

            $table->decimal('precio_unitario', 14, 6);

            $table->decimal('porcentaje_descuento', 7, 4)
                ->default(0);

            $table->decimal('importe_bruto', 14, 2)
                ->default(0);

            $table->decimal('importe_descuento', 14, 2)
                ->default(0);

            $table->decimal('importe_exento', 14, 2)
                ->default(0);

            $table->decimal('importe_otros', 14, 2)
                ->default(0);

            $table->decimal('importe_neto', 14, 2)
                ->default(0);

            $table->decimal('importe_iva', 14, 2)
                ->default(0);

            $table->decimal('importe_total', 14, 2)
                ->default(0);

            $table->text('observacion')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->index('compra_id');
            $table->index('inventario_compra_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_compra');
    }
};
