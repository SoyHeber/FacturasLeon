<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->unique()
                ->constrained('clientes')
                ->cascadeOnDelete();

            $table->string('nombre1', 100);
            $table->string('nombre2', 100)->nullable();
            $table->string('nombre3', 100)->nullable();

            $table->string('apellido1', 100);
            $table->string('apellido2', 100)->nullable();
            $table->string('apellido_casada', 100)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
