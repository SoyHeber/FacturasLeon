<?php

namespace Tests\Support;

use App\Services\VentaInventarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class VentasInventarioTestCase extends VentasTestCase
{
    protected VentaInventarioService $confirmacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('producciones', fn (Blueprint $table) => $table->id());

        // Equivalentes SQLite de las restricciones MySQL; no se abre una conexión real.
        DB::statement("ALTER TABLE movimientos_inventario ADD COLUMN produccion_id INTEGER NULL REFERENCES producciones(id) ON DELETE RESTRICT
            CHECK (produccion_id IS NULL OR (tipo_movimiento IN ('ENTRADA', 'SALIDA') AND cantidad > 0 AND estado = 1))");
        DB::statement("ALTER TABLE movimientos_inventario ADD COLUMN detalle_venta_id INTEGER NULL REFERENCES detalles_ventas(id) ON DELETE RESTRICT
            CHECK (detalle_venta_id IS NULL OR (produccion_id IS NULL AND tipo_movimiento IN ('ENTRADA', 'SALIDA') AND cantidad > 0 AND estado = 1))");
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->index('produccion_id');
            $table->unique(['produccion_id', 'tipo_movimiento'], 'movimientos_inventario_produccion_tipo_unique');
            $table->index('detalle_venta_id');
            $table->unique(['detalle_venta_id', 'tipo_movimiento'], 'movimientos_inventario_venta_tipo_unique');
        });
        $this->confirmacion = new VentaInventarioService;
    }

    protected function snapshot(): array
    {
        $snapshot = [];
        foreach (['ventas', 'detalles_ventas', 'documentos_fel', 'intentos_fel', 'inventarios', 'movimientos_inventario'] as $tabla) {
            $snapshot[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
