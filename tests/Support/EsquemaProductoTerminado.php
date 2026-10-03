<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EsquemaProductoTerminado
{
    public static function crear(): void
    {
        Model::clearBootedModels();
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_03_000003_agregar_producto_terminado_aplicado_a_producciones.php')->up();
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->restrictOnDelete();
            // SQLite necesita texto para conservar todo el rango BIGINT UNSIGNED.
            $table->string('cantidad')->default('0');
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->unsignedInteger('stock_maximo')->nullable();
            $table->string('ubicacion', 100)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_08_26_040638_create_movimientos_inventario_table.php')->up();
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreignId('produccion_id')->nullable()->constrained('producciones')->restrictOnDelete();
            $table->foreignId('detalle_venta_id')->nullable();
            $table->index('produccion_id');
            $table->unique(['produccion_id', 'tipo_movimiento'], 'movimientos_inventario_produccion_tipo_unique');
        });
    }
}
