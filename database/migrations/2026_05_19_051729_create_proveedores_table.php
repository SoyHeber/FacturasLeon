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
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->foreignId('tipo_identificacion_id')->constrained('tipos_identificacion')->restrictOnDelete();
            $table->string('numero_identificacion', 30)->unique();
            $table->foreignId('direccion_id')->constrained('direcciones')->restrictOnDelete();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
