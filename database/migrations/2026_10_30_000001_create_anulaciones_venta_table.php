<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anulaciones_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->unique()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('documento_fel_id')->unique()->constrained('documentos_fel')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipo', ['LOCAL', 'FEL']);
            $table->enum('estado', ['EN_PROCESO', 'RECHAZADA', 'INCIERTA', 'CONFIRMADA', 'APLICADA']);
            $table->date('fecha_anulacion');
            $table->string('motivo', 200);
            $table->json('solicitud');
            $table->longText('confirmacion_raw')->nullable();
            $table->char('confirmacion_hash', 64)->nullable();
            $table->enum('origen_confirmacion', ['LOCAL', 'RESPUESTA', 'MANUAL'])->nullable();
            $table->foreignId('confirmado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('referencia_soporte', 200)->nullable();
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->dateTime('fecha_aplicacion')->nullable();
            $table->foreignId('aplicado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
            $table->index('estado');
        });

        Schema::create('intentos_anulacion_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anulacion_venta_id')->constrained('anulaciones_venta')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->json('solicitud');
            $table->enum('resultado', ['EN_PROCESO', 'CONFIRMADA', 'RECHAZADA', 'INCIERTA']);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->longText('respuesta_raw')->nullable();
            $table->text('mensaje')->nullable();
            $table->timestamps();
            $table->index(['anulacion_venta_id', 'resultado']);
        });
    }

    public function down(): void
    {
        // El historial fiscal y sus reintegros no pueden descartarse con un rollback.
        if (\Illuminate\Support\Facades\DB::table('anulaciones_venta')->exists()) {
            throw new RuntimeException('No se puede retirar el historial: existen anulaciones de venta.');
        }
        Schema::dropIfExists('intentos_anulacion_venta');
        Schema::dropIfExists('anulaciones_venta');
    }
};
