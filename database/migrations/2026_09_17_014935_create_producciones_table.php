<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedInteger('cantidad');

            $table->dateTime('fecha_produccion');

            $table->text('observacion')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->index('producto_id');
            $table->index('user_id');
            $table->index('fecha_produccion');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producciones');
    }
};
