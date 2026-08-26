<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sociedades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->unique()
                ->constrained('clientes')
                ->cascadeOnDelete();

            $table->string('nombre', 200);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sociedades');
    }
};
