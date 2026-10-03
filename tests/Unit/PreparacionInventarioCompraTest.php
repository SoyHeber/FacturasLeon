<?php

namespace Tests\Unit;

use App\Http\Controllers\InventarioCompraController;
use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Services\InventarioCompraService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class PreparacionInventarioCompraTest extends TestCase
{
    private Manager $database;
    private InventarioCompraService $stock;
    private array $ddl = [];
    private array $indices = [];
    private bool $marcaActiva = false;
    private bool $fallarIndices = false;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $this->database = new Manager($container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->database->setEventDispatcher(new Dispatcher($container));
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $container->instance('db', $this->database->getDatabaseManager());
        $container->bind('db.schema', fn () => $this->database->schema());
        $validator = new Factory(new Translator(new ArrayLoader, 'es'), $container);
        $validator->setPresenceVerifier(new \Illuminate\Validation\DatabasePresenceVerifier($this->database->getDatabaseManager()));
        $container->instance('validator', $validator);
        Request::macro('validate', fn (array $rules) => $validator->make($this->all(), $rules)->validate());
        $routes = new RouteCollection;
        foreach (['inventarios_compra', 'movimientos_inventario_compra'] as $nombre) {
            $routes->add((new \Illuminate\Routing\Route('GET', '/' . $nombre, fn () => null))->name($nombre . '.index'));
        }
        $redirect = new Redirector(new UrlGenerator($routes, Request::create('http://localhost')));
        $redirect->setSession(new Store('prueba', new ArraySessionHandler(120)));
        $container->instance('redirect', $redirect);
        $this->stock = new InventarioCompraService;

        // SQLite no conserva DECIMAL como MySQL: los saldos se prueban como texto exacto.
        Schema::create('inventarios_compra', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Material');
            $table->string('descripcion')->nullable();
            $table->string('unidad_medida')->default('UNIDAD');
            $table->string('cantidad')->default('0.0000000000');
            $table->string('stock_minimo')->default('0.0000000000');
            $table->string('stock_maximo')->nullable();
            $table->string('ubicacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proveedor_id')->default(1);
            $table->string('serie')->default('A');
            $table->unsignedBigInteger('numero')->default(1);
            $table->string('numero_autorizacion')->default('autorizacion');
            $table->boolean('estado')->default(true);
            $table->boolean('inventario_aplicado')->default(false);
            $table->dateTime('fecha_anulacion')->nullable();
            $table->unsignedBigInteger('anulado_por_id')->nullable();
            $table->string('importe_total')->default('100.00');
            $table->timestamps();
        });
        Schema::create('detalles_compra', fn (Blueprint $table) => $table->id());
        Schema::create('producciones', fn (Blueprint $table) => $table->id());
        Schema::create('movimientos_inventario_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventario_compra_id');
            $table->unsignedBigInteger('detalle_compra_id')->nullable();
            $table->unsignedBigInteger('produccion_id')->nullable();
            $table->string('tipo_movimiento');
            $table->string('cantidad');
            $table->dateTime('fecha_movimiento');
            $table->string('motivo')->nullable();
            $table->string('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Request::flushMacros();
        parent::tearDown();
    }

    public function test_sumas_salidas_y_limites_conservan_diez_decimales(): void
    {
        $inventario = InventarioCompra::create(['nombre' => 'Exacto']);
        DB::transaction(function () use ($inventario) {
            $this->stock->entrada($inventario->id, '0.1');
            $this->stock->entrada($inventario->id, '0.2');
            $this->assertSame('0.3000000000', $inventario->fresh()->cantidad);
            $this->stock->entrada($inventario->id, '0.0000000001');
            $this->stock->salida($inventario->id, '0.3');
            $this->assertSame('0.0000000001', $inventario->fresh()->cantidad);
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->stock->entrada($inventario->id, '9999999999.9999999998');
            $this->stock->salida($inventario->id, '0.0000000001');
            $this->assertSame('9999999999.9999999998', $inventario->fresh()->cantidad);
        });
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_rechaza_float_redondeo_desbordamiento_y_cantidades_no_positivas(): void
    {
        $inventario = InventarioCompra::create(['nombre' => 'Validación']);
        foreach ([0.1, '0.00000000001', '10000000000', 'abc', [], '0', '-0.00001'] as $cantidad) {
            $this->rechaza(fn () => DB::transaction(fn () => $this->stock->entrada($inventario->id, $cantidad)), ValidationException::class);
            $this->rechaza(fn () => DB::transaction(fn () => $this->stock->salida($inventario->id, $cantidad)), ValidationException::class);
        }
        $this->assertSame('0.0000000000', $inventario->fresh()->cantidad);
        $this->rechaza(fn () => $this->stock->entrada($inventario->id, '1'), \LogicException::class);
        $this->assertSame('1.2300000000', $this->stock->normalizarCantidad('1.23000000000'));
    }

    public function test_error_revierte_toda_la_operacion_de_stock(): void
    {
        $inventario = InventarioCompra::create(['nombre' => 'Rollback']);
        $this->rechaza(fn () => DB::transaction(function () use ($inventario) {
            $this->stock->entrada($inventario->id, '1.0000000001');
            $this->stock->salida($inventario->id, '1.0000000002');
        }), ValidationException::class);
        $this->assertSame('0.0000000000', $inventario->fresh()->cantidad);
        DB::transaction(fn () => $this->stock->entrada($inventario->id, '9999999999.9999999999'));
        $this->rechaza(fn () => DB::transaction(fn () => $this->stock->entrada($inventario->id, '0.0000000001')), ValidationException::class);
        $this->assertSame('9999999999.9999999999', $inventario->fresh()->cantidad);
    }

    public function test_casts_y_campos_servidor_no_pierden_precision(): void
    {
        $detalle = new DetalleCompra(['cantidad' => '1.1234567890', 'precio_unitario' => '2.1234567890', 'porcentaje_descuento' => '3.1234567890']);
        $this->assertSame('1.1234567890', $detalle->cantidad);
        $this->assertSame('2.1234567890', $detalle->precio_unitario);
        $this->assertSame('3.1234567890', $detalle->porcentaje_descuento);
        $this->assertSame('1.1234567890', (new MovimientoInventarioCompra(['cantidad' => '1.1234567890']))->cantidad);
        $this->assertFalse((new InventarioCompra)->isFillable('cantidad'));
        foreach (['inventario_aplicado', 'fecha_anulacion', 'anulado_por_id', 'marca_activa'] as $campo) {
            $this->assertFalse((new Compra)->isFillable($campo));
        }
        DB::table('compras')->insert(['estado' => 1]);
        $this->assertFalse(Compra::first()->inventario_aplicado);
    }

    public function test_crud_inventario_edita_metadatos_y_el_stock_se_ingresa_por_movimientos(): void
    {
        $controller = new InventarioCompraController($this->stock);
        $datos = ['nombre' => 'Manual', 'unidad_medida' => 'g', 'cantidad' => '0.1234567890', 'stock_minimo' => '0.0000000001', 'stock_maximo' => '0.0000000002', 'estado' => '1'];
        $controller->store(Request::create('/', 'POST', $datos));
        $inventario = InventarioCompra::firstOrFail();
        $this->assertSame('0.0000000000', $inventario->cantidad);
        $this->assertSame('0.0000000001', $inventario->stock_minimo);
        (new MovimientoInventarioCompraController($this->stock))->store(Request::create('/', 'POST', $this->datosMovimiento($inventario->id)));
        $datos['cantidad'] = '0.0000000001';
        $controller->update(Request::create('/', 'PUT', $datos), $inventario);
        $this->assertSame('0.1234567890', $inventario->fresh()->cantidad);
        $this->assertSame(1, MovimientoInventarioCompra::count());
    }

    public function test_movimientos_manuales_conservan_aplicacion_reversion_y_ajustes(): void
    {
        $inventario = InventarioCompra::create(['nombre' => 'Movimientos']);
        $controller = new MovimientoInventarioCompraController($this->stock);
        $datos = $this->datosMovimiento($inventario->id);
        $controller->store(Request::create('/', 'POST', $datos));
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $this->assertSame('0.1234567890', $inventario->fresh()->cantidad);
        $datos['cantidad'] = '0.3000000001';
        $controller->update(Request::create('/', 'PUT', $datos), $movimiento);
        $this->assertSame('0.3000000001', $inventario->fresh()->cantidad);
        $controller->cambiarEstado($movimiento);
        $this->assertSame('0.0000000000', $inventario->fresh()->cantidad);
        // La misma instancia quedó desactualizada: se debe recargar bajo bloqueo.
        $controller->cambiarEstado($movimiento);
        $this->assertSame('0.3000000001', $inventario->fresh()->cantidad);
        $datos['tipo_movimiento'] = 'AJUSTE';
        $datos['cantidad'] = '-0.0000000001';
        $controller->store(Request::create('/', 'POST', $datos));
        $this->assertSame('0.3000000000', $inventario->fresh()->cantidad);
        $datos['cantidad'] = '0.5';
        unset($datos['estado']);
        $controller->store(Request::create('/', 'POST', $datos));
        $this->assertSame('0.3000000000', $inventario->fresh()->cantidad);
        $datos['tipo_movimiento'] = 'SALIDA';
        $datos['estado'] = '1';
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $datos)), ValidationException::class);
        $this->assertSame(3, MovimientoInventarioCompra::count());
    }

    public function test_verificacion_no_escribe_datos_y_detecta_duplicados(): void
    {
        DB::table('compras')->insert(['created_at' => '2020-01-01 12:00:00', 'updated_at' => '2020-01-01 12:00:00']);
        $antes = (array) DB::table('compras')->first();
        $this->migracion('000006_verificar_integridad_compras_e_inventario')->up();
        $this->assertSame($antes, (array) DB::table('compras')->first());
        DB::table('compras')->insert(['numero_autorizacion' => 'otra']);
        $this->rechaza(fn () => $this->migracion('000006_verificar_integridad_compras_e_inventario')->up(), RuntimeException::class, 'compras activas duplicadas');
        DB::table('compras')->where('id', 2)->update(['numero' => 2, 'numero_autorizacion' => 'autorizacion']);
        $this->rechaza(fn () => $this->migracion('000006_verificar_integridad_compras_e_inventario')->up(), RuntimeException::class, 'numero_autorizacion');
        DB::table('compras')->where('id', 2)->update(['estado' => 0]);
        $this->migracion('000006_verificar_integridad_compras_e_inventario')->up();
        DB::table('compras')->where('id', 2)->update(['estado' => 2]);
        $this->rechaza(fn () => $this->migracion('000006_verificar_integridad_compras_e_inventario')->up(), RuntimeException::class, 'estado inválido');
    }

    public function test_unique_movimientos_admite_manual_null_y_entrada_salida_por_detalle(): void
    {
        $migracion = $this->migracion('000009_proteger_movimientos_por_detalle_y_tipo');
        $migracion->up();
        $datos = $this->datosMovimiento(1);
        DB::table('movimientos_inventario_compra')->insert($datos);
        DB::table('movimientos_inventario_compra')->insert($datos);
        $datos['detalle_compra_id'] = 37;
        DB::table('movimientos_inventario_compra')->insert($datos);
        $this->rechaza(fn () => DB::table('movimientos_inventario_compra')->insert($datos), QueryException::class);
        $datos['tipo_movimiento'] = 'SALIDA';
        DB::table('movimientos_inventario_compra')->insert($datos);
        $migracion->up();
        $this->assertSame(4, DB::table('movimientos_inventario_compra')->count());
        $migracion->down();
    }

    public function test_verificacion_de_movimientos_incluye_inactivos_y_aborta_antes_del_unique(): void
    {
        $datos = $this->datosMovimiento(1);
        $datos['detalle_compra_id'] = 37;
        DB::table('movimientos_inventario_compra')->insert($datos);
        $datos['estado'] = 0;
        DB::table('movimientos_inventario_compra')->insert($datos);
        $this->rechaza(fn () => $this->migracion('000009_proteger_movimientos_por_detalle_y_tipo')->up(), RuntimeException::class, '#37');
        $this->assertSame(2, DB::table('movimientos_inventario_compra')->count());
        $this->assertFalse(collect(Schema::getIndexes('movimientos_inventario_compra'))->contains('name', 'movimientos_compra_detalle_tipo_unique'));
    }

    public function test_error_de_unique_revierte_cambios_de_stock_en_edicion(): void
    {
        $inventario = InventarioCompra::create(['nombre' => 'Atomicidad']);
        DB::table('detalles_compra')->insert(['id' => 37]);
        $this->migracion('000009_proteger_movimientos_por_detalle_y_tipo')->up();
        $controller = new MovimientoInventarioCompraController($this->stock);
        $datos = $this->datosMovimiento($inventario->id);
        $controller->store(Request::create('/', 'POST', $datos));
        $datos['detalle_compra_id'] = 37;
        DB::transaction(function () use ($datos, $inventario) {
            MovimientoInventarioCompra::create($datos);
            $this->stock->entrada($inventario->id, $datos['cantidad']);
        });
        // Simula una inconsistencia al guardar, después de ajustar stock.
        MovimientoInventarioCompra::updating(function ($movimiento) {
            $movimiento->detalle_compra_id = 37;
        });
        $datos['cantidad'] = '0.2234567890';
        $antes = $inventario->fresh()->cantidad;
        $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', $datos), MovimientoInventarioCompra::findOrFail(1)), QueryException::class);
        $this->assertSame($antes, $inventario->fresh()->cantidad);
        $this->assertNull(MovimientoInventarioCompra::findOrFail(1)->detalle_compra_id);
    }

    public function test_ddl_mysql_crea_y_confirma_reemplazos_antes_de_retirar_unique_anteriores(): void
    {
        $this->simularDdlMySql();
        $migracion = $this->migracion('000008_establecer_unicidad_de_compras_activas');
        $migracion->up();
        $this->assertStringContainsString('CASE WHEN estado = 1 THEN 1 ELSE NULL END) STORED', $this->ddl[0]);
        $this->assertStringContainsString('ADD UNIQUE INDEX `compras_documento_activo_unique`', $this->ddl[1]);
        $this->assertStringContainsString('ADD UNIQUE INDEX `compras_autorizacion_activa_unique`', $this->ddl[1]);
        $this->assertStringContainsString('DROP INDEX `compras_proveedor_id_serie_numero_unique`', $this->ddl[2]);
        $migracion->up();
        $this->assertCount(3, $this->ddl);
        $migracion->down();
        $this->assertStringContainsString('ADD UNIQUE INDEX `compras_proveedor_id_serie_numero_unique`', $this->ddl[3]);
        $this->assertStringContainsString('DROP INDEX `compras_documento_activo_unique`', $this->ddl[4]);
        $this->assertStringContainsString('DROP COLUMN marca_activa', $this->ddl[5]);
    }

    public function test_marca_generada_permite_reutilizacion_inactiva_y_rechaza_activas_duplicadas(): void
    {
        DB::statement('CREATE TABLE compras_activas_prueba (
            id INTEGER PRIMARY KEY, proveedor_id INTEGER NOT NULL, serie TEXT NOT NULL,
            numero INTEGER NOT NULL, numero_autorizacion TEXT NOT NULL, estado INTEGER NOT NULL,
            marca_activa INTEGER GENERATED ALWAYS AS (CASE WHEN estado = 1 THEN 1 ELSE NULL END) STORED,
            UNIQUE (proveedor_id, serie, numero, marca_activa), UNIQUE (numero_autorizacion, marca_activa))');
        $datos = ['proveedor_id' => 37, 'serie' => 'A', 'numero' => 5, 'numero_autorizacion' => 'UUID', 'estado' => 1];
        DB::table('compras_activas_prueba')->insert($datos);
        $this->rechaza(fn () => DB::table('compras_activas_prueba')->insert($datos), QueryException::class);
        $this->rechaza(fn () => DB::table('compras_activas_prueba')->insert(array_replace($datos, ['numero_autorizacion' => 'OTRA'])), QueryException::class);
        $this->rechaza(fn () => DB::table('compras_activas_prueba')->insert(array_replace($datos, ['proveedor_id' => 72])), QueryException::class);
        DB::table('compras_activas_prueba')->where('id', 1)->update(['estado' => 0]);
        DB::table('compras_activas_prueba')->insert($datos);
        $datos['estado'] = 0;
        DB::table('compras_activas_prueba')->insert($datos);
        $this->assertSame(3, DB::table('compras_activas_prueba')->count());
        $this->assertNull(DB::table('compras_activas_prueba')->where('id', 1)->value('marca_activa'));
        $this->assertSame(1, DB::table('compras_activas_prueba')->where('id', 2)->value('marca_activa'));
        $this->rechaza(fn () => DB::table('compras_activas_prueba')->where('id', 1)->update(['estado' => 1]), QueryException::class);
        $this->rechaza(fn () => DB::table('compras_activas_prueba')->where('id', 1)->update(['marca_activa' => 1]), QueryException::class);
    }

    public function test_stock_negativo_historico_aborta_sin_corregir_el_saldo(): void
    {
        DB::table('inventarios_compra')->insert(['cantidad' => '-0.0000000001']);
        $this->rechaza(fn () => $this->migracion('000006_verificar_integridad_compras_e_inventario')->up(), RuntimeException::class, 'stock negativo');
        $this->assertSame('-0.0000000001', DB::table('inventarios_compra')->value('cantidad'));
    }

    public function test_fallo_de_reemplazo_conserva_proteccion_y_permite_reintentar(): void
    {
        $this->simularDdlMySql();
        $this->fallarIndices = true;
        $migracion = $this->migracion('000008_establecer_unicidad_de_compras_activas');
        $this->rechaza(fn () => $migracion->up(), RuntimeException::class, 'Fallo simulado');
        $this->assertArrayHasKey('compras_proveedor_id_serie_numero_unique', $this->indices);
        $this->assertArrayHasKey('compras_numero_autorizacion_unique', $this->indices);
        $this->assertStringNotContainsString('DROP INDEX', implode('\n', $this->ddl));
        $this->fallarIndices = false;
        $migracion->up();
        $this->assertArrayHasKey('compras_documento_activo_unique', $this->indices);
        $this->assertArrayNotHasKey('compras_proveedor_id_serie_numero_unique', $this->indices);
    }

    public function test_rollback_aborta_sin_ddl_ante_documentos_reutilizados(): void
    {
        DB::table('compras')->insert([['estado' => 1], ['estado' => 0]]);
        $this->simularDdlMySql();
        $this->rechaza(fn () => $this->migracion('000008_establecer_unicidad_de_compras_activas')->down(), RuntimeException::class, 'documentos reutilizados');
        $this->assertSame([], $this->ddl);
        $this->assertSame(2, $this->database->table('compras')->count());
    }

    public function test_rollback_no_retira_campos_utilizados_y_ddl_mantiene_fk_restrict(): void
    {
        $this->simularDdlMySql();
        $migracion = $this->migracion('000007_agregar_control_inventario_y_anulacion_a_compras');
        $migracion->up();
        $sql = $this->ddl[0];
        $this->assertStringContainsString('inventario_aplicado BOOLEAN NOT NULL DEFAULT FALSE', $sql);
        $this->assertStringContainsString('fecha_anulacion DATETIME NULL', $sql);
        $this->assertStringContainsString('anulado_por_id BIGINT UNSIGNED NULL', $sql);
        $this->assertStringContainsString('REFERENCES `users` (id) ON DELETE RESTRICT', $sql);
        foreach (['inventario_aplicado' => 1, 'fecha_anulacion' => '2026-10-02 12:00:00', 'anulado_por_id' => 37] as $campo => $valor) {
            $this->database->table('compras')->delete();
            $this->database->table('compras')->insert([$campo => $valor]);
            $this->rechaza(fn () => $migracion->down(), RuntimeException::class, 'información utilizada');
            $this->assertCount(1, $this->ddl);
        }
        $this->database->table('compras')->delete();
        $migracion->down();
        $this->assertStringContainsString('DROP FOREIGN KEY compras_anulado_por_id_foreign', $this->ddl[1]);
    }

    private function migracion(string $nombre): object
    {
        return require dirname(__DIR__, 2) . '/database/migrations/2026_10_02_' . $nombre . '.php';
    }

    private function datosMovimiento(int $inventarioId): array
    {
        return ['inventario_compra_id' => $inventarioId, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => '0.1234567890', 'fecha_movimiento' => '2026-10-02 12:00:00', 'estado' => '1'];
    }

    private function rechaza(callable $operacion, string $clase, ?string $mensaje = null): void
    {
        try {
            $operacion();
        } catch (Throwable $exception) {
            $this->assertInstanceOf($clase, $exception);
            if ($mensaje !== null) {
                $this->assertStringContainsString($mensaje, $exception->getMessage());
            }
            return;
        }
        $this->fail('La operación debía rechazarse.');
    }

    // Captura DDL de MySQL; solo las consultas de datos se ejecutan en SQLite en memoria.
    private function simularDdlMySql(): void
    {
        $this->indices = [
            'compras_proveedor_id_serie_numero_unique' => $this->indice('compras_proveedor_id_serie_numero_unique', ['proveedor_id', 'serie', 'numero']),
            'compras_numero_autorizacion_unique' => $this->indice('compras_numero_autorizacion_unique', ['numero_autorizacion']),
        ];
        $conexion = Mockery::mock(MySqlConnection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getDatabaseName')->andReturn('prueba_aislada');
        $conexion->shouldReceive('getTablePrefix')->andReturn('');
        $conexion->shouldReceive('getQueryGrammar')->andReturn(new \Illuminate\Database\Query\Grammars\MySqlGrammar);
        $conexion->shouldReceive('selectOne')->andReturn((object) ['tipo' => 'tinyint', 'extra' => 'STORED GENERATED', 'nullable' => 'YES', 'expresion' => '(case when (`estado` = 1) then 1 else NULL end)']);
        DB::shouldReceive('connection')->andReturn($conexion);
        DB::shouldReceive('table')->andReturnUsing(fn ($tabla) => $this->database->table($tabla));
        DB::shouldReceive('statement')->andReturnUsing(function ($sql) {
            $this->ddl[] = $sql;
            if ($this->fallarIndices && str_contains($sql, 'ADD UNIQUE INDEX')) {
                throw new RuntimeException('Fallo simulado al crear el reemplazo.');
            }
            if (str_contains($sql, 'ADD COLUMN marca_activa')) {
                $this->marcaActiva = true;
            }
            if (str_contains($sql, 'DROP COLUMN marca_activa')) {
                $this->marcaActiva = false;
            }
            preg_match_all('/ADD UNIQUE INDEX `([^`]+)` \(([^)]+)\)/', $sql, $agregados, PREG_SET_ORDER);
            foreach ($agregados as $agregado) {
                $columnas = array_map(fn ($columna) => trim($columna, ' `'), explode(',', $agregado[2]));
                $this->indices[$agregado[1]] = $this->indice($agregado[1], $columnas);
            }
            preg_match_all('/DROP INDEX `([^`]+)`/', $sql, $retirados, PREG_SET_ORDER);
            foreach ($retirados as $retirado) {
                unset($this->indices[$retirado[1]]);
            }
            return true;
        });
        Schema::shouldReceive('getIndexes')->andReturnUsing(fn () => array_values($this->indices));
        Schema::shouldReceive('hasColumn')->andReturnUsing(fn ($tabla, $columna) => $columna === 'marca_activa' && $this->marcaActiva);
    }

    private function indice(string $nombre, array $columnas): array
    {
        return ['name' => $nombre, 'columns' => $columnas, 'unique' => true, 'primary' => false];
    }
}
