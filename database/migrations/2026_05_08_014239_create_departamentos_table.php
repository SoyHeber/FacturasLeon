<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pais_id')->constrained('paises')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo', 10)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['pais_id', 'nombre']);
            $table->unique(['pais_id', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};