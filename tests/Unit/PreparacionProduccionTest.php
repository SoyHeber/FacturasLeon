<?php

namespace Tests\Unit;

use App\Http\Controllers\ProduccionController;
use App\Models\ConsumoProduccion;
use App\Models\MaterialProducto;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use Illuminate\Container\Container;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\Support\CompraInventarioTestCase;

class PreparacionProduccionTest extends CompraInventarioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
        });
        DB::table('productos')->insert(['id' => 3, 'nombre' => 'Anillo']);
        Schema::drop('producciones');
        (require $this->rutaMigracion('2026_09_17_014935_create_producciones_table'))->up();
        (require $this->rutaMigracion('2026_09_17_013808_create_materiales_producto_table'))->up();

        // Esquema final de auditoría para probar los modelos sin acceder a MySQL.
        Schema::table('producciones', function (Blueprint $table) {
            $table->enum('estado_produccion', ['LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA'])->default('BORRADOR');
            $table->boolean('inventario_aplicado')->default(false);
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->foreignId('confirmado_por_id')->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->foreignId('anulado_por_id')->nullable();
        });
        $this->migracion('000011_create_consumos_produccion_table')->up();

        DB::table('producciones')->insert(array_replace($this->datosProduccion(), ['id' => 101, 'estado_produccion' => 'LEGADA']));
        DB::table('movimientos_inventario_compra')->insert($this->datosMovimiento(['produccion_id' => 101]));
        Schema::table('movimientos_inventario_compra', function (Blueprint $table) {
            $table->dropUnique(['detalle_compra_id', 'tipo_movimiento']);
        });
        $this->migracion('000009_proteger_movimientos_por_detalle_y_tipo')->up();

        $routes = new RouteCollection;
        $routes->add((new Route('GET', '/producciones', fn () => null))->name('producciones.index'));
        Container::getInstance()->make('redirect')->getUrlGenerator()->setRoutes($routes);
    }

    public function test_migracion_conserva_movimientos_stock_y_no_genera_consumos_retroactivos(): void
    {
        $produccion = (array) DB::table('producciones')->first();
        $movimiento = (array) DB::table('movimientos_inventario_compra')->first();
        $this->migracion('000012_agregar_consumo_a_movimientos_inventario_compra')->up();

        $this->assertSame($produccion, (array) DB::table('producciones')->first());
        $this->assertSame($movimiento + ['consumo_produccion_id' => null], (array) DB::table('movimientos_inventario_compra')->first());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());
        $this->assertSame(0, ConsumoProduccion::count());
        $this->assertSame('LEGADA', Produccion::findOrFail(101)->estado_produccion);
    }

    public function test_auditoria_mysql_clasifica_existentes_y_cambia_solo_el_default_para_nuevas(): void
    {
        $ddl = [];
        $conexion = Mockery::mock(MySqlConnection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getQueryGrammar')->andReturn(new \Illuminate\Database\Query\Grammars\MySqlGrammar);
        DB::shouldReceive('connection')->andReturn($conexion);
        DB::shouldReceive('statement')->andReturnUsing(function ($sql) use (&$ddl) {
            $ddl[] = $sql;

            return true;
        });

        $this->migracion('000010_agregar_estado_y_auditoria_a_producciones')->up();

        $this->assertCount(2, $ddl);
        $this->assertStringContainsString("ENUM('LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA') NOT NULL DEFAULT 'LEGADA'", $ddl[0]);
        $this->assertStringContainsString('inventario_aplicado BOOLEAN NOT NULL DEFAULT FALSE', $ddl[0]);
        foreach (['fecha_confirmacion DATETIME NULL', 'confirmado_por_id BIGINT UNSIGNED NULL', 'fecha_anulacion DATETIME NULL', 'anulado_por_id BIGINT UNSIGNED NULL'] as $columna) {
            $this->assertStringContainsString($columna, $ddl[0]);
        }
        $this->assertSame(2, substr_count($ddl[0], 'REFERENCES `users` (id) ON DELETE RESTRICT'));
        $this->assertSame("ALTER TABLE `producciones` ALTER COLUMN estado_produccion SET DEFAULT 'BORRADOR'", $ddl[1]);
    }

    public function test_nueva_produccion_es_borrador_sin_aplicar_stock_aunque_se_envien_campos_de_control(): void
    {
        $datos = $this->datosProduccion();
        $datos['estado_produccion'] = 'CONFIRMADA';
        $datos['inventario_aplicado'] = true;
        $datos['confirmado_por_id'] = 17;
        $datos['fecha_confirmacion'] = '2026-10-02 12:30:00';
        (new ProduccionController)->store(Request::create('/producciones', 'POST', $datos));

        $nueva = Produccion::latest('id')->firstOrFail();
        $this->assertSame('BORRADOR', $nueva->estado_produccion);
        $this->assertFalse($nueva->inventario_aplicado);
        $this->assertNull($nueva->fecha_confirmacion);
        $this->assertNull($nueva->confirmado_por_id);
        $this->assertNull($nueva->fecha_anulacion);
        $this->assertNull($nueva->anulado_por_id);
        $this->assertSame(0, ConsumoProduccion::count());
        $this->assertSame(1, MovimientoInventarioCompra::count());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());

        DB::table('producciones')->insert($this->datosProduccion());
        $this->assertSame('BORRADOR', Produccion::latest('id')->firstOrFail()->estado_produccion);
    }

    public function test_legada_es_historica_y_no_puede_editarse_ni_activarse(): void
    {
        $antes = (array) DB::table('producciones')->where('id', 101)->first();
        $controller = new ProduccionController;
        foreach (['edit', 'update', 'cambiarEstado'] as $metodo) {
            $produccion = Produccion::findOrFail(101);
            $operacion = fn () => $metodo === 'update'
                ? $controller->update(Request::create('/producciones/101', 'PUT', $this->datosProduccion()), $produccion)
                : $controller->$metodo($produccion);
            $this->rechaza($operacion, ValidationException::class);
        }
        $this->assertFalse(Produccion::findOrFail(101)->esEditable());
        $this->assertSame($antes, (array) DB::table('producciones')->where('id', 101)->first());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());
    }

    public function test_automatizacion_excluye_legadas_confirmadas_anuladas_inactivas_y_aplicadas(): void
    {
        foreach (['LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA'] as $estado) {
            DB::table('producciones')->insert($this->datosProduccion() + ['estado_produccion' => $estado]);
        }
        DB::table('producciones')->insert(array_replace($this->datosProduccion(), ['estado' => false]));
        DB::table('producciones')->insert($this->datosProduccion() + ['inventario_aplicado' => true]);

        $this->assertSame(['BORRADOR'], Produccion::paraAutomatizacion()->pluck('estado_produccion')->all());
        $this->assertSame(0, ConsumoProduccion::count());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());
    }

    public function test_snapshot_conserva_receta_material_y_decimales_exactos(): void
    {
        $material = MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '0.1234567890', 'estado' => true]);
        $consumo = ConsumoProduccion::create($this->datosConsumo($material->id));
        $material->update(['cantidad_requerida' => '0.9876543210', 'estado' => false]);
        DB::table('inventarios_compra')->where('id', 7)->update(['nombre' => 'Nombre cambiado']);

        $consumo->refresh();
        $this->assertSame('Material A', $consumo->nombre_material);
        $this->assertSame('UNIDAD', $consumo->unidad_medida);
        $this->assertSame('0.1234567890', $consumo->cantidad_requerida);
        $this->assertSame('0.2469135780', $consumo->cantidad_consumida);
        $this->assertSame(2, $consumo->cantidad_producida);
        $this->assertSame('0.9876543210', $material->cantidad_requerida);
        $this->assertSame(102, $consumo->produccion->id);
        $this->assertSame(7, $consumo->inventarioCompra->id);
        $this->assertSame($material->id, $consumo->materialProducto->id);
        $this->assertSame('0.1234567890', (new MaterialProducto)->forceFill(['cantidad_requerida' => '0.1234567890'])->cantidad_requerida);
        $this->assertSame('9999999999.1234567890', (new ConsumoProduccion)->forceFill(['cantidad_consumida' => '9999999999.1234567890'])->cantidad_consumida);
    }

    public function test_consumos_son_unicos_por_produccion_e_inventario_y_fk_restringe_borrados(): void
    {
        $material = MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '1', 'estado' => true]);
        $datos = $this->datosConsumo($material->id);
        DB::table('consumos_produccion')->insert($datos);
        $this->rechaza(fn () => DB::table('consumos_produccion')->insert($datos), QueryException::class);
        $this->rechaza(fn () => DB::table('producciones')->where('id', 102)->delete(), QueryException::class);
        $this->rechaza(fn () => DB::table('materiales_producto')->where('id', $material->id)->delete(), QueryException::class);
        $this->rechaza(fn () => DB::table('consumos_produccion')->insert(array_replace($datos, ['inventario_compra_id' => 999])), QueryException::class);
    }

    public function test_unique_de_consumo_y_tipo_coexiste_con_unique_de_compra_y_movimientos_manuales(): void
    {
        $this->migracion('000012_agregar_consumo_a_movimientos_inventario_compra')->up();
        $material = MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '1', 'estado' => true]);
        $consumo = ConsumoProduccion::create($this->datosConsumo($material->id));
        $datos = $this->datosMovimiento(['produccion_id' => 102, 'consumo_produccion_id' => $consumo->id]);
        $salida = MovimientoInventarioCompra::create($datos);
        $this->rechaza(fn () => MovimientoInventarioCompra::create($datos), QueryException::class);
        MovimientoInventarioCompra::create(array_replace($datos, ['tipo_movimiento' => 'ENTRADA']));
        DB::table('movimientos_inventario_compra')->insert($this->datosMovimiento());
        DB::table('movimientos_inventario_compra')->insert($this->datosMovimiento());

        $indices = collect(Schema::getIndexes('movimientos_inventario_compra'))->keyBy('name');
        $this->assertTrue($indices['movimientos_compra_detalle_tipo_unique']['unique']);
        $this->assertSame(['detalle_compra_id', 'tipo_movimiento'], $indices['movimientos_compra_detalle_tipo_unique']['columns']);
        $this->assertSame(['consumo_produccion_id', 'tipo_movimiento'], $indices['movimientos_consumo_tipo_unique']['columns']);
        $this->assertSame($consumo->id, $salida->consumoProduccion->id);
        $this->assertSame(102, $salida->produccion->id);
        $this->assertSame(2, $consumo->movimientosInventarioCompra()->count());
        $this->assertSame(1, Produccion::findOrFail(102)->consumos()->count());
    }

    public function test_rollback_rechaza_retirar_consumos_o_vinculos_utilizados(): void
    {
        $this->migracion('000012_agregar_consumo_a_movimientos_inventario_compra')->up();
        $material = MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '1', 'estado' => true]);
        $consumo = ConsumoProduccion::create($this->datosConsumo($material->id));
        MovimientoInventarioCompra::create($this->datosMovimiento(['produccion_id' => 102, 'consumo_produccion_id' => $consumo->id]));
        $this->rechaza(fn () => $this->migracion('000012_agregar_consumo_a_movimientos_inventario_compra')->down(), RuntimeException::class);
        $this->rechaza(fn () => $this->migracion('000011_create_consumos_produccion_table')->down(), RuntimeException::class);
        $this->assertSame(1, ConsumoProduccion::count());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());
    }

    public function test_ddl_mysql_de_consumos_conserva_precision_y_vinculo_restrict_sin_tocar_compra(): void
    {
        $conexion = new MySqlConnection(fn () => throw new RuntimeException('La prueba no debe abrir conexiones reales.'), 'prueba_aislada');
        Schema::swap($conexion->getSchemaBuilder());
        try {
            $consultas = $conexion->pretend(function () {
                $this->migracion('000011_create_consumos_produccion_table')->up();
                $this->migracion('000012_agregar_consumo_a_movimientos_inventario_compra')->up();
            });
        } finally {
            Schema::swap($this->database->schema());
        }
        $sql = implode("\n", array_column($consultas, 'query'));
        $this->assertStringContainsString('`cantidad_requerida` decimal(20, 10) not null', $sql);
        $this->assertStringContainsString('`cantidad_consumida` decimal(20, 10) not null', $sql);
        $this->assertStringContainsString('`consumo_produccion_id` bigint unsigned null', $sql);
        $this->assertStringContainsString('foreign key (`consumo_produccion_id`) references `consumos_produccion` (`id`) on delete restrict', $sql);
        $this->assertStringContainsString('unique `movimientos_consumo_tipo_unique`(`consumo_produccion_id`, `tipo_movimiento`)', $sql);
        $this->assertStringNotContainsString('drop', strtolower($sql));
        $this->assertStringNotContainsString('detalle_compra_id', $sql);
        $this->assertStringNotContainsString('add `produccion_id`', $sql);
    }

    public function test_rollback_de_auditoria_no_retira_estados_o_auditoria_utilizada(): void
    {
        $ddl = [];
        $conexion = Mockery::mock(MySqlConnection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getQueryGrammar')->andReturn(new \Illuminate\Database\Query\Grammars\MySqlGrammar);
        DB::shouldReceive('connection')->andReturn($conexion);
        DB::shouldReceive('table')->andReturnUsing(fn ($tabla) => $this->database->table($tabla));
        DB::shouldReceive('statement')->andReturnUsing(function ($sql) use (&$ddl) {
            $ddl[] = $sql;

            return true;
        });

        foreach (['estado_produccion' => 'BORRADOR', 'inventario_aplicado' => true,
            'fecha_confirmacion' => '2026-10-02 12:30:00', 'confirmado_por_id' => 17,
            'fecha_anulacion' => '2026-10-02 12:30:00', 'anulado_por_id' => 17] as $campo => $valor) {
            $antes = (array) $this->database->table('producciones')->where('id', 101)->first();
            $this->database->table('producciones')->where('id', 101)->update([$campo => $valor]);
            $this->rechaza(fn () => $this->migracion('000010_agregar_estado_y_auditoria_a_producciones')->down(), RuntimeException::class);
            $this->database->table('producciones')->where('id', 101)->update($antes);
        }
        $this->assertSame([], $ddl);
        $this->migracion('000010_agregar_estado_y_auditoria_a_producciones')->down();
        $this->assertCount(1, $ddl);
        $this->assertStringContainsString('DROP FOREIGN KEY producciones_confirmado_por_id_foreign', $ddl[0]);
    }

    private function datosProduccion(): array
    {
        return ['producto_id' => 3, 'user_id' => 17, 'cantidad' => 2, 'fecha_produccion' => '2026-10-02 12:00:00', 'estado' => true];
    }

    private function datosConsumo(int $materialId): array
    {
        if (! DB::table('producciones')->where('id', 102)->exists()) {
            DB::table('producciones')->insert($this->datosProduccion() + ['id' => 102]);
        }

        return ['produccion_id' => 102, 'inventario_compra_id' => 7, 'material_producto_id' => $materialId,
            'nombre_material' => 'Material A', 'unidad_medida' => 'UNIDAD', 'cantidad_requerida' => '0.1234567890',
            'cantidad_producida' => 2, 'cantidad_consumida' => '0.2469135780'];
    }

    private function datosMovimiento(array $cambios = []): array
    {
        return array_replace(['inventario_compra_id' => 7, 'tipo_movimiento' => 'SALIDA', 'cantidad' => '0.2469135780',
            'fecha_movimiento' => '2026-10-02 12:00:00', 'estado' => true], $cambios);
    }

    private function migracion(string $nombre): object
    {
        return require $this->rutaMigracion('2026_10_02_'.$nombre);
    }

    private function rutaMigracion(string $nombre): string
    {
        return dirname(__DIR__, 2).'/database/migrations/'.$nombre.'.php';
    }
}
