<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles_opciones_acciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rol_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('opcion_id')
                ->constrained('opciones')
                ->cascadeOnDelete();

            $table->foreignId('accion_id')
                ->constrained('acciones')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['rol_id', 'opcion_id', 'accion_id'], 'roles_opciones_acciones_unique');
            $table->index('opcion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_opciones_acciones');
    }
};
