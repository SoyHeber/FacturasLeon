<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->unique()
                ->constrained('productos')
                ->restrictOnDelete();

            $table->unsignedInteger('cantidad')->default(0);

            $table->unsignedInteger('stock_minimo')->default(0);

            $table->unsignedInteger('stock_maximo')->nullable();

            $table->string('ubicacion', 100)->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
