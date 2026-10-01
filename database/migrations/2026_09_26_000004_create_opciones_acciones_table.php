<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones_acciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opcion_id')
                ->constrained('opciones')
                ->cascadeOnDelete();

            $table->foreignId('accion_id')
                ->constrained('acciones')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['opcion_id', 'accion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones_acciones');
    }
};
