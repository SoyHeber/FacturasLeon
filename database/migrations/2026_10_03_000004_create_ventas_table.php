<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->enum('estado_venta', ['BORRADOR', 'CONFIRMADA', 'ANULADA'])->default('BORRADOR');
            $table->date('fecha');
            $table->char('moneda', 3)->default('GTQ');
            $table->text('observacion')->nullable();
            $table->string('receptor_identificacion', 30);
            $table->string('receptor_tipo_identificacion_codigo', 30);
            $table->string('receptor_tipo_identificacion_nombre', 100);
            $table->string('receptor_nombre', 700);
            $table->text('receptor_direccion');
            $table->string('receptor_codigo_postal', 15)->nullable();
            $table->string('receptor_municipio', 100);
            $table->string('receptor_municipio_codigo', 10)->nullable();
            $table->string('receptor_departamento', 100);
            $table->string('receptor_departamento_codigo', 10)->nullable();
            $table->string('receptor_pais', 100);
            $table->string('receptor_pais_codigo', 10)->nullable();
            $table->string('receptor_correo', 150)->nullable();
            foreach (['bruto', 'descuento', 'exento', 'neto', 'iva', 'total'] as $importe) {
                $table->decimal('importe_'.$importe, 20, 2)->default(0);
            }
            $table->foreignId('usuario_creador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_modificador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['estado_venta', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
