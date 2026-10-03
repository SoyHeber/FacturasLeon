<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_fel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_fel_id')->constrained('documentos_fel')->restrictOnDelete();
            $table->enum('resultado', ['EN_PROCESO', 'CERTIFICADA', 'ERROR', 'INCIERTA'])->default('EN_PROCESO');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->longText('respuesta_raw')->nullable();
            $table->string('codigo_error', 100)->nullable();
            $table->text('mensaje_error')->nullable();
            $table->text('error_tecnico')->nullable();
            $table->timestamps();
            $table->index(['documento_fel_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_fel');
    }
};
