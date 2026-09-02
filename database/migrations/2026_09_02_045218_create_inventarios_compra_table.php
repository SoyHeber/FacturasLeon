<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios_compra', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100);

            $table->string('descripcion', 255)
                ->nullable();

            $table->string('unidad_medida', 30);

            $table->decimal('cantidad', 14, 4)
                ->default(0);

            $table->decimal('stock_minimo', 14, 4)
                ->default(0);

            $table->decimal('stock_maximo', 14, 4)
                ->nullable();

            $table->string('ubicacion', 100)
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->index('nombre');
            $table->index('unidad_medida');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_compra');
    }
};
