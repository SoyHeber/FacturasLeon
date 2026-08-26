<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Identificación DTE
             |--------------------------------------------------------------------------
             */

            $table->string('tipo_dte', 10);

            $table->string('serie', 20);

            $table->unsignedBigInteger('numero');

            $table->string('numero_autorizacion', 36)
                ->unique();

            $table->dateTime('fecha_emision');

            $table->dateTime('fecha_certificacion')
                ->nullable();

            $table->string('moneda', 3)
                ->default('GTQ');

            /*
             |--------------------------------------------------------------------------
             | Importes
             |--------------------------------------------------------------------------
             */

            $table->decimal('importe_bruto', 14, 2)
                ->default(0);

            $table->decimal('importe_descuento', 14, 2)
                ->default(0);

            $table->decimal('importe_exento', 14, 2)
                ->default(0);

            $table->decimal('importe_otros', 14, 2)
                ->default(0);

            $table->decimal('importe_neto', 14, 2)
                ->default(0);

            $table->decimal('importe_iva', 14, 2)
                ->default(0);

            $table->decimal('importe_total', 14, 2)
                ->default(0);

            $table->text('observacion')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Índices
             |--------------------------------------------------------------------------
             */

            $table->index('tipo_dte');
            $table->index('serie');
            $table->index('numero');
            $table->index('fecha_emision');
            $table->index('estado');

            $table->unique([
                'proveedor_id',
                'serie',
                'numero',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
