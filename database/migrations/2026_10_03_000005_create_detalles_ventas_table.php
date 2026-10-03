<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('numero_linea');
            $table->string('producto_codigo', 50);
            $table->string('producto_nombre', 80);
            $table->string('descripcion', 255);
            $table->string('unidad_medida', 10)->default('UNI');
            $table->enum('bien_servicio', ['B', 'S'])->default('B');
            $table->unsignedBigInteger('cantidad');
            $table->decimal('precio_unitario', 24, 10);
            $table->decimal('porcentaje_descuento', 7, 4)->default(0);
            $table->enum('tratamiento_tributario', ['GRAVADO', 'EXENTO']);
            $table->decimal('tasa_iva', 7, 4);
            foreach (['bruto', 'descuento', 'exento', 'neto', 'iva', 'total'] as $importe) {
                $table->decimal('importe_'.$importe, 20, 2);
            }
            $table->timestamps();
            $table->unique(['venta_id', 'numero_linea']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_ventas');
    }
};
