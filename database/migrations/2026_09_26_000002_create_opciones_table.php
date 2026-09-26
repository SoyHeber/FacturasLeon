<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('modulo_id')
                ->constrained('modulos')
                ->restrictOnDelete();

            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();

            // Prefijo del nombre de ruta, ej: marcas, metodos_pago
            $table->string('ruta', 100)->unique();

            $table->string('icono', 20)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('estado')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['modulo_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones');
    }
};
