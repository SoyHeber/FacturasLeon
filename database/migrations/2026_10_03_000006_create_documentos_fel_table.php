<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_fel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->unique()->constrained('ventas')->restrictOnDelete();
            $table->uuid('referencia')->unique();
            $table->enum('estado_fel', ['PENDIENTE', 'EN_PROCESO', 'CERTIFICADA', 'ERROR', 'INCIERTA', 'ANULADA'])->default('PENDIENTE');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->unsignedSmallInteger('p_tipo_doc')->default(1);
            $table->char('p_tipo_respuesta', 1)->default('D');
            $table->longText('xml_solicitud')->nullable();
            $table->char('hash_xml', 64)->nullable();
            $table->longText('xml_certificado')->nullable();
            $table->string('fel_uuid', 36)->nullable();
            $table->string('fel_uuid_normalized', 36)->nullable()->unique();
            $table->string('fel_serie', 50)->nullable();
            $table->string('fel_numero', 50)->nullable();
            $table->dateTime('fecha_certificacion')->nullable();
            $table->string('nit_certificador', 30)->nullable();
            $table->string('nombre_certificador', 200)->nullable();
            $table->enum('ambiente', ['PRUEBAS', 'PRODUCCION'])->nullable();
            $table->string('nit_emisor', 30)->nullable();
            $table->string('codigo_establecimiento', 20)->nullable();
            $table->string('id_maquina', 100)->nullable();
            $table->string('nombre_emisor', 200)->nullable();
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('afiliacion_iva', 10)->nullable();
            $table->text('direccion_emisor')->nullable();
            $table->string('codigo_postal_emisor', 15)->nullable();
            $table->string('municipio_emisor', 100)->nullable();
            $table->string('departamento_emisor', 100)->nullable();
            $table->string('pais_emisor', 10)->nullable();
            $table->json('frases')->nullable();
            $table->dateTime('fecha_hora_emision')->nullable();
            $table->timestamps();
            $table->index('estado_fel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fel');
    }
};
