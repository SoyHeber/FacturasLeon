<?php

namespace Tests\Unit;

use App\Http\Controllers\MaterialProductoController;
use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Http\Controllers\ProduccionController;
use App\Models\ConsumoProduccion;
use App\Models\MaterialProducto;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CalculadoraProduccionService;
use App\Services\InventarioCompraService;
use App\Services\ProduccionInventarioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CompraInventarioTestCase;
use Tests\Support\EsquemaProductoTerminado;

class BorradoresProduccionTest extends CompraInventarioTestCase
{
    private CalculadoraProduccionService $calculadora;

    private ?string $directorioVistas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculadora = new CalculadoraProduccionService;
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        DB::table('productos')->insert([['id' => 3, 'nombre' => 'Anillo'], ['id' => 5, 'nombre' => 'Pulsera'], ['id' => 9, 'nombre' => 'Collar']]);
        Schema::drop('producciones');
        (require dirname(__DIR__, 2).'/database/migrations/2026_09_17_014935_create_producciones_table.php')->up();
        Schema::table('producciones', function (Blueprint $table) {
            $table->enum('estado_produccion', ['LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA'])->default('BORRADOR');
            $table->boolean('inventario_aplicado')->default(false);
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->foreignId('confirmado_por_id')->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->foreignId('anulado_por_id')->nullable();
        });
        Schema::table('inventarios_compra', function (Blueprint $table) {
            $table->string('unidad_medida', 30)->default('g');
        });
        // SQLite usa texto para conservar los extremos DECIMAL sin conversión binaria.
        Schema::create('materiales_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->string('cantidad_requerida');
            $table->text('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->unique(['producto_id', 'inventario_compra_id']);
            $table->timestamps();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_30_000002_agregar_estado_general_recetas.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_02_000011_create_consumos_produccion_table.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_02_000012_agregar_consumo_a_movimientos_inventario_compra.php')->up();
        EsquemaProductoTerminado::crear();
        $this->crearMaterial();

        $container = Container::getInstance();
        $session = new Store('produccion', new ArraySessionHandler(120));
        $session->start();
        $container->make('redirect')->setSession($session);
        $request = Request::create('http://localhost');
        $request->setLaravelSession($session);
        $container->instance('request', $request);
        $container->instance('session', $session);
        $routes = new RouteCollection;
        foreach (['producciones', 'materiales_producto', 'movimientos_inventario_compra', 'movimientos_inventario'] as $nombre) {
            foreach (['index', 'create', 'store', 'edit', 'update', 'show', 'anular', 'confirmar', 'cambiar-estado'] as $metodo) {
                $ruta = '/'.$nombre.'/'.$metodo.(in_array($metodo, ['edit', 'update', 'show', 'anular', 'confirmar', 'cambiar-estado']) ? '/{id}' : '');
                $routes->add((new Route('GET', $ruta, fn () => null))->name($nombre.'.'.$metodo));
            }
        }
        $url = $container->make('redirect')->getUrlGenerator();
        $url->setRoutes($routes);
        $container->instance('url', $url);
        $gate = new Gate($container, fn () => new User);
        $gate->before(fn () => true);
        $container->instance(GateContract::class, $gate);
    }

    protected function tearDown(): void
    {
        if ($this->directorioVistas !== null) {
            $ruta = realpath($this->directorioVistas);
            $raiz = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'FacturasLeon-produccion-vistas-';
            if ($ruta !== false && str_starts_with($ruta, $raiz)) {
                (new Filesystem)->deleteDirectory($ruta);
            }
        }
        parent::tearDown();
    }

    public function test_store_impone_borrador_y_ignora_todos_los_campos_tecnicos(): void
    {
        $antes = $this->snapshot();
        (new ProduccionController)->store(Request::create('/', 'POST', $this->datosProduccion() + [
            'estado' => 'ANULADA', 'estado_produccion' => 'CONFIRMADA', 'user_id' => 999,
            'inventario_aplicado' => true, 'fecha_confirmacion' => '2026-10-02', 'confirmado_por_id' => 999,
            'fecha_anulacion' => '2026-10-02', 'anulado_por_id' => 999,
        ]));
        $produccion = Produccion::firstOrFail();
        $this->assertSame('BORRADOR', $produccion->estado_produccion);
        $this->assertFalse($produccion->inventario_aplicado);
        $this->assertTrue($produccion->estado);
        $this->assertSame(17, (int) $produccion->user_id);
        foreach (['fecha_confirmacion', 'confirmado_por_id', 'fecha_anulacion', 'anulado_por_id'] as $campo) {
            $this->assertNull($produccion->$campo);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('cantidadesProduccionInvalidas')]
    public function test_store_rechaza_cantidad_de_produccion_invalida(mixed $cantidad): void
    {
        $datos = array_replace($this->datosProduccion(), ['cantidad' => $cantidad]);
        $this->rechaza(fn () => (new ProduccionController)->store(Request::create('/', 'POST', $datos)), ValidationException::class);
        $this->assertSame(0, Produccion::count());
    }

    public static function cantidadesProduccionInvalidas(): array
    {
        return [['0'], ['-1'], ['1.5'], ['4294967296'], [null], [1.0]];
    }

    public function test_borrador_edita_solo_campos_permitidos_y_recarga_bajo_bloqueo(): void
    {
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $antes = $this->snapshot();
        $grammar = $this->registrarBloqueos();
        $datos = ['producto_id' => 5, 'cantidad' => '7', 'fecha_produccion' => '2026-10-02 14:00:00', 'observacion' => 'Cambio',
            'estado' => false, 'estado_produccion' => 'ANULADA', 'inventario_aplicado' => true,
            'confirmado_por_id' => 999, 'anulado_por_id' => 999, 'fecha_confirmacion' => '2026-10-02', 'fecha_anulacion' => '2026-10-02'];
        (new ProduccionController)->update(Request::create('/', 'PUT', $datos), $produccion);
        $produccion->refresh();
        $this->assertSame(5, (int) $produccion->producto_id);
        $this->assertSame(7, $produccion->cantidad);
        $this->assertSame('Cambio', $produccion->observacion);
        $this->assertSame('2026-10-02 14:00:00', $produccion->fecha_produccion->format('Y-m-d H:i:s'));
        $this->assertSame('BORRADOR', $produccion->estado_produccion);
        $this->assertTrue($produccion->estado);
        $this->assertFalse($produccion->inventario_aplicado);
        foreach (['fecha_confirmacion', 'confirmado_por_id', 'fecha_anulacion', 'anulado_por_id'] as $campo) {
            $this->assertNull($produccion->$campo);
        }
        $this->assertSame(['producciones'], array_column($grammar->bloqueos, 'tabla'));
        $this->assertSame(1, $grammar->bloqueos[0]['transaccion']);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('estadosHistoricos')]
    public function test_estado_actual_historico_rechaza_update_aunque_modelo_recibido_sea_borrador(string $estado): void
    {
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        DB::table('producciones')->where('id', $produccion->id)->update(['estado_produccion' => $estado]);
        $antes = (array) DB::table('producciones')->first();
        $this->rechaza(fn () => (new ProduccionController)->update(Request::create('/', 'PUT', $this->datosProduccion()), $produccion), ValidationException::class);
        $this->assertSame($antes, (array) DB::table('producciones')->first());
        $this->rechaza(fn () => (new ProduccionController)->edit($produccion->fresh()), ValidationException::class);
        $this->rechaza(fn () => (new ProduccionController)->cambiarEstado($produccion), ValidationException::class);
    }

    public static function estadosHistoricos(): array
    {
        return [['LEGADA'], ['CONFIRMADA'], ['ANULADA']];
    }

    public function test_receta_de_una_y_varias_materias_es_exacta_y_excluye_lineas_inactivas(): void
    {
        $antes = $this->snapshot();
        $produccion = new Produccion(['producto_id' => 3, 'cantidad' => '3']);
        $estimacion = $this->calculadora->calcular($produccion);
        $this->assertCount(1, $estimacion);
        $this->assertSame('0.1234567890', $estimacion[0]['cantidad_por_unidad']);
        $this->assertSame('0.3703703670', $estimacion[0]['cantidad_total']);
        $this->crearMaterial('0.0000000001', 44);
        $estimacion = $this->calculadora->calcular($produccion);
        $this->assertCount(2, $estimacion);
        $this->assertSame('0.0000000003', $estimacion[1]['cantidad_total']);
        DB::table('materiales_producto')->where('inventario_compra_id', 44)->update(['estado' => false, 'cantidad_requerida' => 'inválida']);
        $this->assertCount(1, $this->calculadora->calcular($produccion));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_estimacion_no_exige_stock_disponible_ni_inventario_activo(): void
    {
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '0.0000000000', 'estado' => false]);
        $antes = $this->snapshot();
        $estimacion = $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => '100']));
        $this->assertSame('12.3456789000', $estimacion[0]['cantidad_total']);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_limite_decimal_es_exacto_y_desbordamiento_se_rechaza(): void
    {
        DB::table('materiales_producto')->update(['cantidad_requerida' => '9999999999.9999999999']);
        $estimacion = $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => '1']));
        $this->assertSame('9999999999.9999999999', $estimacion[0]['cantidad_total']);
        $this->rechaza(fn () => $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => '2'])), ValidationException::class);
        DB::table('materiales_producto')->update(['cantidad_requerida' => '0.0000000001']);
        $estimacion = $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => '4294967295']));
        $this->assertSame('0.4294967295', $estimacion[0]['cantidad_total']);
    }

    public function test_receta_vacia_o_solo_inactiva_se_rechaza(): void
    {
        $this->rechaza(fn () => $this->calculadora->calcular(new Produccion(['producto_id' => 5, 'cantidad' => 1])), ValidationException::class);
        DB::table('materiales_producto')->update(['estado' => false]);
        $this->rechaza(fn () => $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => 1])), ValidationException::class);
    }

    #[DataProvider('cantidadesRecetaInvalidas')]
    public function test_normalizacion_rechaza_cantidad_requerida_invalida(mixed $cantidad): void
    {
        $this->rechaza(fn () => $this->calculadora->normalizarCantidadRequerida($cantidad), ValidationException::class);
    }

    public static function cantidadesRecetaInvalidas(): array
    {
        return [['0'], ['-0.0000000001'], ['10000000000'], ['0.00000000001'], ['abc'], ['1e999'], [0.1], [null], [[]]];
    }

    public function test_calculo_detecta_receta_corrupta_sin_ocultar_precision_con_cast(): void
    {
        foreach (['0', '-1', 'abc', '0.00000000001', '10000000000'] as $cantidad) {
            DB::table('materiales_producto')->update(['cantidad_requerida' => $cantidad]);
            $this->rechaza(fn () => $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => 1])), ValidationException::class);
        }
    }

    public function test_inventario_relacionado_inexistente_se_rechaza(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('materiales_producto')->update(['inventario_compra_id' => 999]);
        Schema::enableForeignKeyConstraints();
        $this->rechaza(fn () => $this->calculadora->calcular(new Produccion(['producto_id' => 3, 'cantidad' => 1])), ValidationException::class);
    }

    public function test_receta_create_update_y_estado_bloquean_productos_primero_en_orden(): void
    {
        $antes = $this->snapshot();
        $controller = new MaterialProductoController;
        $grammar = $this->registrarBloqueos();
        $controller->store(Request::create('/', 'POST', ['producto_id' => 5, 'materiales' => [
            ['inventario_compra_id' => 44, 'cantidad_requerida' => '0.0000000001'],
        ]]));
        $material = MaterialProducto::where('producto_id', 5)->firstOrFail();
        $this->assertSame('0.0000000001', $material->cantidad_requerida);
        $this->assertSame(['productos', 'inventarios_compra', 'materiales_producto'], array_column($grammar->bloqueos, 'tabla'));
        $grammar->bloqueos = [];
        $datos = $this->datosEdicionReceta($material);
        $datos['materiales'][0]['cantidad_requerida'] = '9999999999.9999999999';
        $controller->update(Request::create('/', 'PUT', $datos), $material);
        $this->assertSame(['productos', 'materiales_producto', 'materiales_producto', 'inventarios_compra'], array_column($grammar->bloqueos, 'tabla'));
        $this->assertSame([[5], [$material->id], [5], [44]], array_column($grammar->bloqueos, 'parametros'));
        $this->assertSame([1, 1, 1, 1], array_column($grammar->bloqueos, 'transaccion'));
        $material->refresh();
        $this->assertSame('9999999999.9999999999', $material->cantidad_requerida);
        $grammar->bloqueos = [];
        $controller->cambiarEstado($material);
        $this->assertSame(['productos', 'materiales_producto', 'materiales_producto'], array_column($grammar->bloqueos, 'tabla'));
        $this->assertTrue($material->fresh()->estado);
        $this->assertFalse(Producto::findOrFail(5)->receta_activa);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_receta_update_usa_producto_actual_en_lugar_de_modelo_obsoleto(): void
    {
        $material = MaterialProducto::firstOrFail();
        $datos = $this->datosEdicionReceta($material);
        DB::table('materiales_producto')->where('id', $material->id)->update(['producto_id' => 9]);
        $grammar = $this->registrarBloqueos();
        $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $material), ValidationException::class);
        $this->assertSame([[9], [$material->id]], array_column($grammar->bloqueos, 'parametros'));
        $this->assertSame(9, $material->fresh()->producto_id);
        $this->assertSame('0.1234567890', $material->fresh()->cantidad_requerida);
    }

    public function test_receta_duplicada_o_decimal_invalido_no_deja_cambios_parciales(): void
    {
        $antes = $this->filas('materiales_producto');
        $controller = new MaterialProductoController;
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', ['producto_id' => 3, 'materiales' => [
            ['inventario_compra_id' => 7, 'cantidad_requerida' => '1'],
        ]])), ValidationException::class);
        foreach (['0', '0.00000000001', '10000000000', 0.1] as $cantidad) {
            $edicion = $this->datosEdicionReceta(MaterialProducto::firstOrFail());
            $edicion['materiales'][0]['cantidad_requerida'] = $cantidad;
            try {
                $controller->update(Request::create('/', 'PUT', $edicion), MaterialProducto::firstOrFail());
                $this->fail('La cantidad inválida debe rechazarse.');
            } catch (ValidationException $excepcion) {
                $this->assertArrayHasKey('materiales.0.cantidad_requerida', $excepcion->errors());
            }
        }
        $this->assertSame($antes, $this->filas('materiales_producto'));
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_crear_varias_lineas_conserva_decimales_receta_previa_e_inventarios(): void
    {
        $antes = $this->snapshot();
        $existente = MaterialProducto::firstOrFail()->getAttributes();
        $respuesta = (new MaterialProductoController)->store(Request::create('/', 'POST', $this->datosMateriales()));
        $lineas = MaterialProducto::where('producto_id', 5)->orderBy('inventario_compra_id')->get();
        $this->assertCount(2, $lineas);
        $this->assertSame(['0.0000000001', '0.1234567891'], $lineas->pluck('cantidad_requerida')->all());
        $this->assertTrue($lineas->every(fn ($linea) => $linea->estado));
        $this->assertSame($existente, MaterialProducto::findOrFail($existente['id'])->getAttributes());
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(route('materiales_producto.index'), $respuesta->getTargetUrl());
        $this->assertNotEmpty(session('success'));
    }

    #[DataProvider('cantidadesMaterialInvalidas')]
    public function test_ultima_cantidad_invalida_se_identifica_por_fila_y_no_guarda_parcialmente(mixed $cantidad): void
    {
        $datos = $this->datosMateriales();
        $datos['materiales'][1]['cantidad_requerida'] = $cantidad;
        $antes = $this->filas('materiales_producto');
        $inventariosAntes = $this->snapshotInventarios();
        try {
            (new MaterialProductoController)->store(Request::create('/', 'POST', $datos));
            $this->fail('La última fila debe rechazarse.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('materiales.1.cantidad_requerida', $excepcion->errors());
        }
        $this->assertSame($antes, $this->filas('materiales_producto'));
        $this->assertSame($inventariosAntes, $this->snapshotInventarios());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public static function cantidadesMaterialInvalidas(): array
    {
        return [['0'], ['-1'], ['0.00000000001'], ['10000000000'], [0.1], ['1e3'], [[]], ['']];
    }

    public function test_repite_material_en_el_lote_o_ya_asignado_aunque_inactivo_se_rechaza(): void
    {
        $controller = new MaterialProductoController;
        $datos = $this->datosMateriales();
        $datos['materiales'][1]['inventario_compra_id'] = 7;
        $antes = $this->filas('materiales_producto');
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $datos)), ValidationException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));
        DB::table('materiales_producto')->update(['estado' => false]);
        $antes = $this->filas('materiales_producto');
        $datos = $this->datosMateriales(['producto_id' => 3, 'materiales' => [
            ['inventario_compra_id' => 44, 'cantidad_requerida' => '2'],
            ['inventario_compra_id' => 7, 'cantidad_requerida' => '3'],
        ]]);
        try {
            $controller->store(Request::create('/', 'POST', $datos));
            $this->fail('El material existente, incluso inactivo, no debe duplicarse.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('materiales.1.inventario_compra_id', $excepcion->errors());
        }
        $this->assertSame($antes, $this->filas('materiales_producto'));
    }

    public function test_catalogos_inactivos_lineas_vacias_y_campos_no_permitidos_no_se_guardan(): void
    {
        $controller = new MaterialProductoController;
        $antes = $this->filas('materiales_producto');
        foreach ([[], array_fill(0, 201, ['inventario_compra_id' => 7, 'cantidad_requerida' => '1']),
            [['inventario_compra_id' => 7, 'cantidad_requerida' => '1', 'estado' => true]],
            [['inventario_compra_id' => 999, 'cantidad_requerida' => '1']]] as $lineas) {
            $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $this->datosMateriales(['materiales' => $lineas]))), ValidationException::class);
        }
        DB::table('productos')->where('id', 5)->update(['estado' => false]);
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $this->datosMateriales())), ValidationException::class);
        DB::table('productos')->where('id', 5)->update(['estado' => true]);
        DB::table('inventarios_compra')->where('id', 44)->update(['estado' => false]);
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $this->datosMateriales())), ValidationException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));
    }

    public function test_fallo_de_persistencia_en_ultima_linea_revierte_el_lote_completo(): void
    {
        $antes = $this->filas('materiales_producto');
        $inventariosAntes = $this->snapshotInventarios();
        MaterialProducto::creating(function ($material) {
            if ((int) $material->producto_id === 5 && (int) $material->inventario_compra_id === 44) {
                return false;
            }
        });
        $this->rechaza(fn () => (new MaterialProductoController)->store(Request::create('/', 'POST', $this->datosMateriales())), RuntimeException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));
        $this->assertSame($inventariosAntes, $this->snapshotInventarios());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_ampliar_receta_preserva_consumos_confirmados_y_la_produccion_futura_usa_todas_las_lineas(): void
    {
        \App\Models\Inventario::create(['producto_id' => 3, 'estado' => true]);
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $servicio = new ProduccionInventarioService(new InventarioCompraService, $this->calculadora);
        $servicio->confirmarProduccion($produccion->id, 17);
        $consumos = $this->filas('consumos_produccion');
        $antes = $this->snapshot();
        (new MaterialProductoController)->store(Request::create('/', 'POST', $this->datosMateriales([
            'producto_id' => 3, 'materiales' => [['inventario_compra_id' => 44, 'cantidad_requerida' => '0.25']],
        ])));
        $this->assertSame($consumos, $this->filas('consumos_produccion'));
        $this->assertSame($antes, $this->snapshot());
        $nueva = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $this->assertCount(2, $this->calculadora->calcular($nueva));
        $servicio->confirmarProduccion($nueva->id, 17);
        $this->assertSame(2, ConsumoProduccion::where('produccion_id', $nueva->id)->count());
        $this->assertSame($consumos[0], $this->filas('consumos_produccion')[0]);
    }

    public function test_formulario_restaura_filas_y_decimales_escapa_observaciones_y_respeta_permiso_crear(): void
    {
        $this->prepararVistas();
        $datos = $this->datosMateriales();
        $datos['materiales'][1]['observacion'] = '<script>alert(1)</script>';
        session()->flashInput($datos);
        $html = (new MaterialProductoController)->create()->render();
        $this->assertStringContainsString('Agregar materiales al producto', $html);
        $this->assertStringContainsString('name="materiales[0][inventario_compra_id]"', $html);
        $this->assertStringContainsString('name="materiales[1][cantidad_requerida]" value="0.1234567891"', $html);
        $this->assertStringContainsString('value="0.0000000001"', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('id="plantilla-material"', $html);
        $this->assertStringContainsString('Guardar materiales', $html);
        $this->assertStringNotContainsString('name="estado"', $html);
        $container = Container::getInstance();
        $gate = new Gate($container, fn () => new User);
        $gate->before(fn () => false);
        $container->instance(GateContract::class, $gate);
        $html = (new MaterialProductoController)->create()->render();
        $this->assertStringNotContainsString('Guardar materiales', $html);
        $this->assertStringNotContainsString('+ Agregar material', $html);
        $this->assertStringNotContainsString('>Quitar fila<', $html);
    }

    public function test_selectores_de_creacion_y_edicion_cargan_todo_el_catalogo_activo_con_mas_de_nueve_materiales(): void
    {
        $this->prepararVistas();
        DB::table('inventarios_compra')->where('id', 7)->update(['nombre' => 'Alambre de plata 925']);
        $base = (array) DB::table('inventarios_compra')->where('id', 7)->first();
        foreach (range(55, 79) as $id) {
            DB::table('inventarios_compra')->insert(array_replace($base, ['id' => $id, 'nombre' => 'Materia prima '.$id]));
        }
        DB::table('inventarios_compra')->insert(array_replace($base, ['id' => 99, 'nombre' => 'Materia prima inactiva', 'estado' => false]));
        $controller = new MaterialProductoController;
        $ancla = MaterialProducto::firstOrFail();
        session()->flashInput(['producto_id' => 3, 'materiales' => [
            ['inventario_compra_id' => 79, 'cantidad_requerida' => '0.25'],
        ]]);
        foreach ([$controller->create(), $controller->edit($ancla)] as $vista) {
            $this->assertCount(27, $vista->getData()['inventariosCompra']);
            $documento = new \DOMDocument;
            $documento->loadHTML('<?xml encoding="UTF-8">'.$vista->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($documento);
            foreach (['//tbody[@id="lineas-materiales"]', '//template[@id="plantilla-material"]'] as $contenedor) {
                $selector = $contenedor.'//select[@data-campo="inventario_compra_id"]';
                $this->assertSame(28, $xpath->query($selector.'/option')->length);
                $alambre = $xpath->query($selector.'/option[2]')->item(0);
                $this->assertSame('7', $alambre->getAttribute('value'));
                $this->assertSame('Alambre de plata 925', $alambre->getAttribute('data-nombre'));
                $this->assertSame('7 · Alambre de plata 925', $alambre->textContent);
                $this->assertSame(1, $xpath->query($selector.'/option[@value="79"]')->length);
                $this->assertSame(0, $xpath->query($selector.'/option[@value="99"]')->length);
                $this->assertSame(1, $xpath->query($contenedor.'//input[@data-buscar-material]')->length);
                $this->assertStringContainsString('27 materiales activos disponibles', $xpath->query($contenedor.'//*[@data-total-materiales]')->item(0)->textContent);
            }
            $this->assertSame(1, $xpath->query('//tbody[@id="lineas-materiales"]//option[@value="79" and @selected]')->length);
        }
    }

    public function test_create_edit_show_muestran_estimacion_y_no_modifican_inventario(): void
    {
        $this->prepararVistas();
        $antes = $this->snapshot();
        $controller = new ProduccionController;
        $request = Request::create('/producciones/create', 'GET', ['producto_id' => 3, 'cantidad' => '3']);
        $request->setLaravelSession(Container::getInstance()->make('session'));
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        foreach ([$controller->create($request), $controller->edit($produccion, $request), $controller->show($produccion)] as $vista) {
            $html = $vista->with('errors', new ViewErrorBag)->render();
            foreach (['Estimación de materiales', 'Material A', 'Cantidad por unidad', 'Cantidad total estimada', '0.1234567890', '0.3703703670', 'todavía no afecta inventario'] as $texto) {
                $this->assertStringContainsString($texto, $html);
            }
            $this->assertStringNotContainsString('name="estado"', $html);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_formularios_conservan_datos_enviados_tras_error_en_lugar_de_estimacion_anterior(): void
    {
        $this->prepararVistas();
        $controller = new ProduccionController;
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $datos = array_replace($this->datosProduccion(), [
            'cantidad' => '7', 'fecha_produccion' => 'invalida', 'observacion' => 'Observación nueva',
        ]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', $datos), $produccion), ValidationException::class);
        Container::getInstance()->make('session')->flashInput($datos);
        $request = Request::create('/', 'GET', ['producto_id' => 3, 'cantidad' => '3', 'observacion' => 'Estimación anterior']);

        foreach ([$controller->create($request), $controller->edit($produccion, $request)] as $vista) {
            $formulario = $vista->getData()['datosFormulario'];
            $this->assertSame('7', $formulario['cantidad']);
            $this->assertSame('invalida', $formulario['fecha_produccion']);
            $this->assertSame('Observación nueva', $formulario['observacion']);
            $this->assertSame('0.8641975230', $vista->getData()['estimacion'][0]['cantidad_total']);
        }
        $this->assertSame(3, $produccion->fresh()->cantidad);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('estadosHistoricos')]
    public function test_show_historico_muestra_historial_y_oculta_editar(string $estado): void
    {
        $this->prepararVistas();
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $produccion->forceFill(['estado_produccion' => $estado])->save();
        $antes = $this->snapshot();
        $html = (new ProduccionController)->show($produccion)->render();
        $this->assertStringContainsString('Historial de consumos', $html);
        $this->assertStringNotContainsString('Estimación de materiales', $html);
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertStringNotContainsString('Confirmar producción', $html);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_vista_sin_receta_muestra_aviso_y_deja_consultar_registro(): void
    {
        $this->prepararVistas();
        $produccion = Produccion::create(array_replace($this->datosProduccion(), ['producto_id' => 5, 'user_id' => 17, 'estado' => true]));
        $html = (new ProduccionController)->show($produccion)->render();
        $this->assertStringContainsString('El producto no tiene una receta con materiales activos.', $html);
        $this->assertStringContainsString('Editar', $html);
        $this->assertStringContainsString('Confirmar producción', $html);
        $this->assertStringContainsString('Se descontarán las materias primas del inventario', $html);
    }

    #[DataProvider('accionesPorEstado')]
    public function test_show_e_index_muestran_solo_acciones_del_estado(string $estado, array $permitidas, array $ocultas): void
    {
        $this->prepararVistas();
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $produccion->forceFill(['estado_produccion' => $estado])->save();
        $antes = $this->snapshot();
        $vistas = Container::getInstance()->make('view');
        $htmlShow = (new ProduccionController)->show($produccion)->render();
        $htmlIndex = $vistas->make('producciones.index', ['producciones' => new LengthAwarePaginator([$produccion], 1, 10)])->render();
        foreach ([$htmlShow, $htmlIndex] as $html) {
            foreach ($permitidas as $accion) {
                $this->assertStringContainsString($accion, $html);
            }
            foreach (array_merge($ocultas, ['Inactivar', '>Activar', 'Reactivar']) as $accion) {
                $this->assertStringNotContainsString($accion, $html);
            }
            if ($estado === 'LEGADA') {
                $this->assertStringContainsString('no conciliada', $html);
            }
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public static function accionesPorEstado(): array
    {
        return [
            ['BORRADOR', ['Editar', 'Confirmar producción', 'Cancelar producción'], ['Anular producción']],
            ['CONFIRMADA', ['Anular producción'], ['Editar', 'Confirmar producción', 'Cancelar producción']],
            ['ANULADA', [], ['Editar', 'Confirmar producción', 'Cancelar producción', 'Anular producción']],
            ['LEGADA', [], ['Editar', 'Confirmar producción', 'Cancelar producción', 'Anular producción']],
        ];
    }

    public function test_show_anulada_muestra_auditoria_snapshot_y_movimientos_sin_consultar_receta(): void
    {
        $this->prepararVistas();
        DB::table('users')->where('id', 17)->update(['name' => 'Responsable de anulación']);
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $produccion->forceFill(['estado_produccion' => 'ANULADA', 'inventario_aplicado' => true,
            'fecha_confirmacion' => '2026-10-02 12:30:00', 'confirmado_por_id' => 17,
            'fecha_anulacion' => '2026-10-03 09:15:00', 'anulado_por_id' => 17])->save();
        $consumo = ConsumoProduccion::create(['produccion_id' => $produccion->id, 'inventario_compra_id' => 7,
            'material_producto_id' => MaterialProducto::firstOrFail()->id, 'nombre_material' => 'Material histórico original',
            'unidad_medida' => 'g', 'cantidad_requerida' => '0.1', 'cantidad_producida' => 3, 'cantidad_consumida' => '0.3']);
        foreach (['SALIDA', 'ENTRADA'] as $tipo) {
            MovimientoInventarioCompra::create(['produccion_id' => $produccion->id, 'consumo_produccion_id' => $consumo->id,
                'inventario_compra_id' => 7, 'tipo_movimiento' => $tipo, 'cantidad' => '0.3', 'fecha_movimiento' => now(), 'estado' => true]);
        }
        DB::table('materiales_producto')->update(['estado' => false, 'cantidad_requerida' => '99']);
        $antes = $this->snapshot();
        $calculadora = \Mockery::mock(CalculadoraProduccionService::class);
        $calculadora->shouldNotReceive('calcular');
        $html = (new ProduccionController($calculadora))->show($produccion)->render();
        foreach (['Anulada:', '03/10/2026 09:15', 'Responsable de anulación', 'Material histórico original',
            '0.3000000000', 'SALIDA', 'ENTRADA', 'Historial de movimientos'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('Estimación de materiales', $html);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_vistas_de_movimientos_automaticos_de_produccion_ocultan_edicion_y_estado(): void
    {
        $this->prepararVistas();
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $consumo = ConsumoProduccion::create(['produccion_id' => $produccion->id, 'inventario_compra_id' => 7,
            'material_producto_id' => MaterialProducto::firstOrFail()->id, 'nombre_material' => 'Material A', 'unidad_medida' => 'g',
            'cantidad_requerida' => '0.1', 'cantidad_producida' => 3, 'cantidad_consumida' => '0.3']);
        $controller = new MovimientoInventarioCompraController(new \App\Services\InventarioCompraService);
        foreach (['SALIDA', 'ENTRADA'] as $tipo) {
            $movimiento = MovimientoInventarioCompra::create(['produccion_id' => $produccion->id, 'consumo_produccion_id' => $consumo->id,
                'inventario_compra_id' => 7, 'tipo_movimiento' => $tipo, 'cantidad' => '0.3', 'fecha_movimiento' => now(), 'estado' => true]);
            $htmlShow = $controller->show($movimiento)->render();
            $htmlIndex = Container::getInstance()->make('view')->make('movimientos_inventario_compra.index', [
                'movimientosInventarioCompra' => new LengthAwarePaginator([$movimiento], 1, 10),
            ])->render();
            foreach ([$htmlShow, $htmlIndex] as $html) {
                $this->assertStringContainsString('Automático - Producción', $html);
                $this->assertStringContainsString('0.3000000000', $html);
                $this->assertStringNotContainsString('Editar', $html);
                $this->assertStringNotContainsString('Inactivar', $html);
            }
        }
    }

    public function test_historial_muestra_entrada_y_salida_terminadas_como_automaticas_sin_edicion(): void
    {
        $this->prepararVistas();
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17, 'estado' => true]);
        $produccion->forceFill(['estado_produccion' => Produccion::CONFIRMADA, 'producto_terminado_aplicado' => true])->save();
        $inventario = \App\Models\Inventario::create(['producto_id' => $produccion->producto_id, 'estado' => true]);
        \App\Models\MovimientoInventario::create(['produccion_id' => $produccion->id, 'inventario_id' => $inventario->id,
            'tipo_movimiento' => 'ENTRADA', 'cantidad' => 3, 'fecha_movimiento' => now(), 'estado' => true]);
        $html = (new ProduccionController)->show($produccion)->render();
        $this->assertStringContainsString('Movimientos de producto terminado', $html);
        $this->assertStringContainsString('ENTRADA - Confirmación', $html);
        $this->assertStringContainsString('Automático - Producción', $html);
        $this->assertStringContainsString('stock suficiente del producto terminado', $html);
        $this->assertStringNotContainsString('SALIDA - Anulación', $html);

        $produccion->forceFill(['estado_produccion' => Produccion::ANULADA])->save();
        \App\Models\MovimientoInventario::create(['produccion_id' => $produccion->id, 'inventario_id' => $inventario->id,
            'tipo_movimiento' => 'SALIDA', 'cantidad' => 3, 'fecha_movimiento' => now(), 'estado' => true]);
        $antes = $this->snapshot();
        $html = (new ProduccionController)->show($produccion)->render();
        foreach (['ENTRADA - Confirmación', 'SALIDA - Anulación', 'Automático - Producción', 'Solo lectura'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertStringNotContainsString('Inactivar', $html);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_ver_y_editar_desde_cualquier_linea_muestran_componentes_vigentes_e_historial(): void
    {
        $this->prepararVistas();
        $segunda = $this->crearMaterial('0.2500000000', 44);
        $segunda->update(['estado' => false, 'observacion' => 'Material histórico']);
        $antes = $this->snapshot();
        $controller = new MaterialProductoController;
        foreach ([$controller->show($segunda), $controller->edit($segunda)] as $vista) {
            $this->assertCount(2, $vista->getData()['producto']->materialesProducto);
            $html = $vista->render();
            foreach (['Material A', 'Material B', '0.1234567890', '0.2500000000', 'Material histórico'] as $texto) {
                $this->assertStringContainsString($texto, $html);
            }
            $this->assertStringContainsString('Componentes retirados', $html);
            $this->assertStringNotContainsString('Inactivar material', $html);
            $this->assertStringNotContainsString('type="checkbox"', $html);
        }
        $html = $controller->edit(MaterialProducto::firstOrFail())->render();
        $this->assertStringNotContainsString('name="materiales[1][id]" value="'.$segunda->id.'"', $html);
        $this->assertStringContainsString('name="huella_receta"', $html);
        $this->assertGreaterThan(strpos($html, '</template>'), strpos($html, 'name="huella_receta"'));
        $this->assertStringContainsString('Guardar receta', $html);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_listado_agrupa_por_producto_y_filtra_componentes_vigentes_y_estado_general(): void
    {
        $this->prepararVistas();
        $segunda = $this->crearMaterial('0.2500000000', 44);
        $otra = MaterialProducto::create(['producto_id' => 5, 'inventario_compra_id' => 7, 'cantidad_requerida' => '0.5000000000', 'estado' => false]);
        DB::table('productos')->where('id', 5)->update(['receta_activa' => false]);
        $controller = new MaterialProductoController;
        $vista = $controller->index(Request::create('/', 'GET'));
        $this->assertSame(2, $vista->getData()['recetas']->total());
        $this->assertSame([5, 3], $vista->getData()['recetas']->pluck('id')->all());
        $this->assertStringContainsString('Ver receta', $vista->render());
        $filtrada = $controller->index(Request::create('/', 'GET', ['material' => 'Material B', 'cantidad_requerida' => '0.25', 'estado' => '1']));
        $this->assertSame(1, $filtrada->getData()['recetas']->total());
        $this->assertCount(2, $filtrada->getData()['recetas']->first()->materialesProducto);
        $this->assertStringContainsString('Material A', $filtrada->render());
        $sinCoincidencia = $controller->index(Request::create('/', 'GET', ['material' => 'Material B', 'cantidad_requerida' => '0.1234567890']));
        $this->assertSame(0, $sinCoincidencia->getData()['recetas']->total());
        $porId = $controller->index(Request::create('/', 'GET', ['id' => $segunda->id]))->getData()['recetas'];
        $this->assertSame([3], $porId->pluck('id')->all());
        $this->assertCount(2, $porId->first()->materialesProducto);
        $this->assertSame([5], $controller->index(Request::create('/', 'GET', ['id' => $otra->id]))->getData()['recetas']->pluck('id')->all());
        $this->assertSame(0, $controller->index(Request::create('/', 'GET', ['id' => 999]))->getData()['recetas']->total());
        $this->assertSame(1, $controller->index(Request::create('/', 'GET', ['estado' => '1']))->getData()['recetas']->total());
        $this->assertSame([5], $controller->index(Request::create('/', 'GET', ['estado' => '0']))->getData()['recetas']->pluck('id')->all());
        $this->assertSame(0, $controller->index(Request::create('/', 'GET', ['id' => $segunda->id, 'material' => 'Material A']))->getData()['recetas']->total());
    }

    public function test_editar_receta_modifica_agrega_y_retira_sin_reescribir_consumos_confirmados(): void
    {
        $this->crearMaterial('0.2500000000', 44);
        $nuevoInventario = (array) DB::table('inventarios_compra')->where('id', 7)->first();
        DB::table('inventarios_compra')->insert(array_replace($nuevoInventario, ['id' => 55, 'nombre' => 'Material C']));
        $produccion = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $servicio = new ProduccionInventarioService(new InventarioCompraService, $this->calculadora);
        $servicio->confirmarProduccion($produccion->id, 17);
        $historial = $this->filas('consumos_produccion');
        $antes = $this->snapshot();
        $ancla = MaterialProducto::firstOrFail();
        $ids = MaterialProducto::where('producto_id', 3)->pluck('id')->all();
        $datos = $this->datosEdicionReceta($ancla);
        $datos['materiales'][0]['cantidad_requerida'] = '0.5000000000';
        array_pop($datos['materiales']);
        $datos['materiales'][] = ['inventario_compra_id' => 55, 'cantidad_requerida' => '0.1'];
        (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $ancla);
        $lineas = MaterialProducto::where('producto_id', 3)->orderBy('id')->get();
        $this->assertCount(3, $lineas);
        $this->assertSame($ids, $lineas->take(2)->modelKeys());
        $this->assertSame('0.5000000000', $lineas[0]->cantidad_requerida);
        $this->assertFalse($lineas[1]->estado);
        $this->assertSame($antes, $this->snapshot());
        $nueva = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $servicio->confirmarProduccion($nueva->id, 17);
        $this->assertSame([7, 55], ConsumoProduccion::where('produccion_id', $nueva->id)->orderBy('inventario_compra_id')->pluck('inventario_compra_id')->all());
        $this->assertSame($historial, array_slice($this->filas('consumos_produccion'), 0, 2));
    }

    public function test_edicion_rechaza_identidades_ajenas_duplicados_y_solicitudes_truncadas(): void
    {
        $this->crearMaterial('0.2500000000', 44);
        $inventario = (array) DB::table('inventarios_compra')->where('id', 7)->first();
        DB::table('inventarios_compra')->insert(array_replace($inventario, ['id' => 55, 'nombre' => 'Material C']));
        $ancla = MaterialProducto::firstOrFail();
        $base = $this->datosEdicionReceta($ancla);
        $omisiones = $base;
        array_pop($omisiones['materiales']);
        unset($omisiones['huella_receta']);
        $otroId = $base;
        $otroId['materiales'][0]['id'] = 999;
        $otroMaterial = $base;
        $otroMaterial['materiales'][0]['inventario_compra_id'] = 55;
        $repetido = $base;
        $repetido['materiales'][] = ['inventario_compra_id' => 7, 'cantidad_requerida' => '0.1'];
        $cantidadInvalida = $base;
        $cantidadInvalida['materiales'][1]['cantidad_requerida'] = '0';
        $antes = $this->filas('materiales_producto');
        foreach ([$omisiones, $otroId, $otroMaterial, $repetido, $cantidadInvalida, array_replace($base, ['producto_id' => 5])] as $datos) {
            $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $ancla), ValidationException::class);
            $this->assertSame($antes, $this->filas('materiales_producto'));
        }
    }

    public function test_formulario_obsoleto_no_sobrescribe_cambios_ni_materiales_agregados_por_otro_usuario(): void
    {
        $ancla = MaterialProducto::firstOrFail();
        $anterior = $this->datosEdicionReceta($ancla);
        $nuevo = $anterior;
        $nuevo['materiales'][0]['cantidad_requerida'] = '0.5';
        (new MaterialProductoController)->update(Request::create('/', 'PUT', $nuevo), $ancla);
        $guardado = $this->filas('materiales_producto');
        try {
            (new MaterialProductoController)->update(Request::create('/', 'PUT', $anterior), $ancla);
            $this->fail('El formulario anterior debe rechazarse.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('receta', $excepcion->errors());
        }
        $this->assertSame($guardado, $this->filas('materiales_producto'));
        $anterior = $this->datosEdicionReceta($ancla);
        $this->crearMaterial('0.25', 44);
        $guardado = $this->filas('materiales_producto');
        $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $anterior), $ancla), ValidationException::class);
        $this->assertSame($guardado, $this->filas('materiales_producto'));
    }

    public function test_fallo_al_guardar_ultima_linea_de_la_edicion_revierte_toda_la_receta(): void
    {
        $segunda = $this->crearMaterial('0.25', 44);
        $ancla = MaterialProducto::firstOrFail();
        $datos = $this->datosEdicionReceta($ancla);
        $datos['materiales'][0]['cantidad_requerida'] = '0.5';
        $datos['materiales'][1]['cantidad_requerida'] = '0.75';
        $antes = $this->filas('materiales_producto');
        MaterialProducto::updating(fn ($linea) => $linea->id === $segunda->id ? false : null);
        $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $ancla), RuntimeException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));
    }

    public function test_modificar_edita_toda_la_composicion_y_eliminar_controla_solo_el_estado_general(): void
    {
        $ancla = MaterialProducto::firstOrFail();
        $base = $this->datosEdicionReceta($ancla);
        $antes = $this->filas('materiales_producto');
        $this->permisosReceta(['materiales_producto.ver']);
        $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $base), $ancla), AuthorizationException::class);
        $this->rechaza(fn () => (new MaterialProductoController)->cambiarEstado($ancla), AuthorizationException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));

        $this->permisosReceta(['materiales_producto.modificar', 'materiales_producto.ver']);
        $base['materiales'][0]['cantidad_requerida'] = '0.5';
        $base['materiales'][] = ['inventario_compra_id' => 44, 'cantidad_requerida' => '0.1'];
        (new MaterialProductoController)->update(Request::create('/', 'PUT', $base), $ancla);
        $this->assertSame('0.5000000000', $ancla->fresh()->cantidad_requerida);
        $html = (new MaterialProductoController)->edit($ancla)->render();
        $this->assertStringContainsString('Guardar receta', $html);
        $this->assertStringContainsString('+ Agregar material', $html);
        $this->assertStringContainsString('Quitar fila', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertStringNotContainsString('Inactivar receta', (new MaterialProductoController)->show($ancla)->render());

        $datos = $this->datosEdicionReceta($ancla);
        array_shift($datos['materiales']);
        (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $ancla);
        $this->assertFalse($ancla->fresh()->estado);
        $this->assertTrue(Producto::findOrFail(3)->receta_activa);
        $this->permisosReceta(['materiales_producto.ver']);
        $html = (new MaterialProductoController)->index(Request::create('/'))->render();
        $this->assertStringNotContainsString('Editar receta', $html);
        $this->assertStringNotContainsString('Inactivar receta', $html);
        $this->permisosReceta(['materiales_producto.ver', 'materiales_producto.eliminar']);
        $this->assertStringContainsString('Inactivar receta', (new MaterialProductoController)->index(Request::create('/'))->render());
        (new MaterialProductoController)->cambiarEstado($ancla);
        $this->assertFalse(Producto::findOrFail(3)->receta_activa);
    }

    public function test_activar_receta_exige_producto_componentes_e_insumos_validos(): void
    {
        $ancla = MaterialProducto::firstOrFail();
        $this->datosEdicionReceta($ancla);
        DB::table('productos')->where('id', 3)->update(['receta_activa' => false]);
        $controller = new MaterialProductoController;
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => false]);
        $this->assertStringContainsString('Insumo inactivo', $controller->show($ancla)->render());
        $this->rechaza(fn () => $controller->cambiarEstado($ancla), ValidationException::class);
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => true]);
        DB::table('productos')->where('id', 3)->update(['estado' => false]);
        $this->rechaza(fn () => $controller->cambiarEstado($ancla), ValidationException::class);
        DB::table('productos')->where('id', 3)->update(['estado' => true]);
        $ancla->update(['estado' => false]);
        $this->rechaza(fn () => $controller->cambiarEstado($ancla), ValidationException::class);
        $ancla->update(['estado' => true, 'cantidad_requerida' => '0']);
        $this->rechaza(fn () => $controller->cambiarEstado($ancla), ValidationException::class);
        $this->assertFalse(Producto::findOrFail(3)->receta_activa);
        $ancla->update(['cantidad_requerida' => '0.1234567890']);
        $controller->cambiarEstado($ancla);
        $this->assertTrue(Producto::findOrFail(3)->receta_activa);
        $this->assertTrue($ancla->fresh()->estado);
        $this->assertSame(1, MaterialProducto::count());
    }

    public function test_edicion_rechaza_alta_de_insumo_inactivo_aunque_las_otras_lineas_sean_validas(): void
    {
        $ancla = MaterialProducto::firstOrFail();
        $datos = $this->datosEdicionReceta($ancla);
        $datos['materiales'][0]['cantidad_requerida'] = '0.5';
        $datos['materiales'][] = ['inventario_compra_id' => 44, 'cantidad_requerida' => '1'];
        DB::table('inventarios_compra')->where('id', 44)->update(['estado' => false]);
        $antes = $this->filas('materiales_producto');
        $this->rechaza(fn () => (new MaterialProductoController)->update(Request::create('/', 'PUT', $datos), $ancla), ValidationException::class);
        $this->assertSame($antes, $this->filas('materiales_producto'));
    }

    public function test_filtros_estan_bajo_los_titulos_y_el_boton_afecta_la_receta_completa(): void
    {
        $this->prepararVistas();
        $segunda = $this->crearMaterial('0.2500000000', 44);
        $primera = MaterialProducto::firstOrFail();
        $controller = new MaterialProductoController;
        $documento = new \DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$controller->index(Request::create('/'))->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $this->assertSame(2, $xpath->query('//table/thead/tr')->length);
        $this->assertSame('table-dark', $xpath->query('//table/thead/tr[1]')->item(0)->getAttribute('class'));
        $this->assertSame(6, $xpath->query('//table/thead/tr[1]/th')->length);
        $this->assertSame(6, $xpath->query('//table/thead/tr[2]/th')->length);
        foreach (['id', 'producto', 'material', 'observacion', 'estado'] as $campo) {
            $nodo = $xpath->query('//table/thead/tr[2]//*[@name="'.$campo.'"]');
            $this->assertSame(1, $nodo->length);
            $this->assertSame('filtros', $nodo->item(0)->getAttribute('form'));
        }
        $this->assertSame(1, $xpath->query('//tbody//form')->length);
        $this->assertSame(2, $xpath->query('//tbody/tr')->length);
        $this->assertSame('ID material', $xpath->query('//table/thead/tr[1]/th[1]')->item(0)->textContent);
        $this->assertSame('#'.$primera->id, trim($xpath->query('//tbody/tr[1]/td[1]')->item(0)->textContent));
        $this->assertSame('#'.$segunda->id, trim($xpath->query('//tbody/tr[2]/td[1]')->item(0)->textContent));
        $this->assertFalse($xpath->query('//tbody/tr[1]/td[1]')->item(0)->hasAttribute('rowspan'));
        $this->assertSame('2', $xpath->query('//tbody/tr[1]/td[2]')->item(0)->getAttribute('rowspan'));
        $this->assertStringContainsString('Material B', $xpath->query('//tbody/tr[2]/td[2]')->item(0)->textContent);
        $this->assertStringContainsString('Sin observación', $xpath->query('//tbody/tr[2]/td[3]')->item(0)->textContent);
        $html = $controller->show(MaterialProducto::firstOrFail())->render();
        $this->assertSame(1, substr_count($html, '>Inactivar receta</button>'));
        $this->assertStringNotContainsString('Inactivar material', $html);
    }

    public function test_estado_general_conserva_componentes_y_rechaza_formularios_abiertos_antes_del_cambio(): void
    {
        $retirado = $this->crearMaterial('0.25', 44);
        $retirado->update(['estado' => false]);
        $ancla = MaterialProducto::firstOrFail();
        $datos = $this->datosEdicionReceta($ancla);
        $lineas = $this->filas('materiales_producto');
        $antes = $this->snapshot();
        $controller = new MaterialProductoController;
        $controller->cambiarEstado($retirado);
        $this->assertFalse(Producto::findOrFail(3)->receta_activa);
        $this->assertTrue(Producto::findOrFail(3)->estado);
        $this->assertSame($lineas, $this->filas('materiales_producto'));
        $this->assertSame($antes, $this->snapshot());
        $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', $datos), $ancla), ValidationException::class);
        $controller->cambiarEstado($ancla);
        $this->assertTrue(Producto::findOrFail(3)->receta_activa);
        $this->assertFalse($retirado->fresh()->estado);
        $this->assertSame($lineas, $this->filas('materiales_producto'));
    }

    public function test_receta_inactiva_bloquea_estimacion_y_confirmacion_sin_reescribir_produccion_historica(): void
    {
        $confirmada = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $servicio = new ProduccionInventarioService(new InventarioCompraService, $this->calculadora);
        $servicio->confirmarProduccion($confirmada->id, 17);
        $consumos = $this->filas('consumos_produccion');
        $borrador = Produccion::create($this->datosProduccion() + ['user_id' => 17]);
        $borrador->load('producto');
        (new MaterialProductoController)->cambiarEstado(MaterialProducto::firstOrFail());
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->calculadora->calcular($borrador), ValidationException::class);
        $this->rechaza(fn () => $servicio->confirmarProduccion($borrador->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('BORRADOR', $borrador->fresh()->estado_produccion);
        $this->assertSame($consumos, $this->filas('consumos_produccion'));
        $this->assertSame('CONFIRMADA', $confirmada->fresh()->estado_produccion);
        $this->assertFalse(Producto::findOrFail(3)->receta_activa);
    }

    public function test_reincorporar_componente_reutiliza_su_id_sin_activar_la_receta(): void
    {
        $segunda = $this->crearMaterial('0.25', 44);
        $ancla = MaterialProducto::firstOrFail();
        DB::table('productos')->where('id', 3)->update(['receta_activa' => false]);
        $controller = new MaterialProductoController;
        $datos = $this->datosEdicionReceta($ancla);
        array_pop($datos['materiales']);
        $controller->update(Request::create('/', 'PUT', $datos), $ancla);
        $this->assertFalse($segunda->fresh()->estado);
        $datos = $this->datosEdicionReceta($ancla);
        $datos['materiales'][] = ['inventario_compra_id' => 44, 'cantidad_requerida' => '0.75', 'observacion' => 'Reincorporado'];
        $controller->update(Request::create('/', 'PUT', $datos), $ancla);
        $this->assertSame(2, MaterialProducto::count());
        $this->assertTrue($segunda->fresh()->estado);
        $this->assertSame('0.7500000000', $segunda->fresh()->cantidad_requerida);
        $this->assertFalse(Producto::findOrFail(3)->receta_activa);
        // Quitar y volver a agregar la misma materia prima en una edición tampoco duplica la línea.
        $datos = $this->datosEdicionReceta($ancla);
        unset($datos['materiales'][1]['id']);
        $datos['materiales'][1]['cantidad_requerida'] = '0.5';
        $controller->update(Request::create('/', 'PUT', $datos), $ancla);
        $this->assertSame(2, MaterialProducto::count());
        $this->assertSame('0.5000000000', $segunda->fresh()->cantidad_requerida);
    }

    public function test_fallo_al_guardar_estado_general_no_modifica_la_receta(): void
    {
        $ancla = MaterialProducto::firstOrFail();
        $antes = $this->filas('productos');
        $lineas = $this->filas('materiales_producto');
        Producto::updating(fn () => false);
        $this->rechaza(fn () => (new MaterialProductoController)->cambiarEstado($ancla), RuntimeException::class);
        $this->assertSame($antes, $this->filas('productos'));
        $this->assertSame($lineas, $this->filas('materiales_producto'));
    }

    public function test_migracion_conserva_recetas_anteriores_y_protege_la_reversion(): void
    {
        MaterialProducto::create(['producto_id' => 5, 'inventario_compra_id' => 7, 'cantidad_requerida' => '0.5', 'estado' => false]);
        $lineas = $this->filas('materiales_producto');
        $migracion = require dirname(__DIR__, 2).'/database/migrations/2026_10_30_000002_agregar_estado_general_recetas.php';
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('productos', 'receta_activa'));
        $migracion->up();
        $this->assertTrue(Producto::findOrFail(3)->receta_activa);
        $this->assertFalse(Producto::findOrFail(5)->receta_activa);
        $this->assertTrue(Producto::findOrFail(9)->receta_activa);
        $this->assertSame($lineas, $this->filas('materiales_producto'));
        $this->rechaza(fn () => $migracion->down(), RuntimeException::class);
        $this->assertTrue(Schema::hasColumn('productos', 'receta_activa'));
    }

    private function permisosReceta(array $permisos): void
    {
        $gate = new Gate(Container::getInstance(), fn () => new User);
        $gate->before(fn ($user, $ability) => in_array($ability, $permisos, true));
        \Illuminate\Support\Facades\Gate::swap($gate);
    }

    private function datosEdicionReceta(MaterialProducto $ancla): array
    {
        if ($this->directorioVistas === null) {
            $this->prepararVistas();
        }
        $datos = (new MaterialProductoController)->edit($ancla)->getData();

        return ['producto_id' => $datos['producto']->id, 'huella_receta' => $datos['huellaReceta'],
            'materiales' => $datos['materialesActuales']->map(fn ($linea) => [
                'id' => $linea->id, 'inventario_compra_id' => $linea->inventario_compra_id,
                'cantidad_requerida' => $linea->getRawOriginal('cantidad_requerida'),
                'observacion' => $linea->observacion,
            ])->all()];
    }

    private function datosMateriales(array $cambios = []): array
    {
        return array_replace(['producto_id' => 5, 'materiales' => [
            ['inventario_compra_id' => 7, 'cantidad_requerida' => '0.0000000001'],
            ['inventario_compra_id' => 44, 'cantidad_requerida' => '0.1234567891', 'observacion' => 'Material por unidad'],
        ]], $cambios);
    }

    private function crearMaterial(string $cantidad = '0.1234567890', int $inventarioId = 7): MaterialProducto
    {
        return MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => $inventarioId,
            'cantidad_requerida' => $cantidad, 'estado' => true]);
    }

    private function datosProduccion(): array
    {
        return ['producto_id' => 3, 'cantidad' => '3', 'fecha_produccion' => '2026-10-02 12:30:00', 'observacion' => 'Borrador'];
    }

    private function filas(string $tabla): array
    {
        return DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function snapshot(): array
    {
        return array_map(fn ($tabla) => $this->filas($tabla), ['inventarios_compra', 'movimientos_inventario_compra', 'consumos_produccion']);
    }

    private function registrarBloqueos(): SQLiteGrammar
    {
        $grammar = new class extends SQLiteGrammar
        {
            public array $bloqueos = [];

            public function compileSelect(Builder $query)
            {
                if ($query->lock !== null) {
                    $this->bloqueos[] = ['tabla' => $query->from, 'parametros' => $query->getBindings(),
                        'transaccion' => $query->getConnection()->transactionLevel()];
                }

                return parent::compileSelect($query);
            }
        };
        DB::connection()->setQueryGrammar($grammar);

        return $grammar;
    }

    private function prepararVistas(): void
    {
        $container = Container::getInstance();
        $this->directorioVistas = sys_get_temp_dir().'/FacturasLeon-produccion-vistas-'.bin2hex(random_bytes(8));
        mkdir($this->directorioVistas.'/cache', 0777, true);
        mkdir($this->directorioVistas.'/layouts');
        file_put_contents($this->directorioVistas.'/layouts/app-bootstrap.blade.php', "@yield('content') @stack('scripts')");
        $files = new Filesystem;
        $compiler = new BladeCompiler($files, $this->directorioVistas.'/cache');
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
        $vistas = new Factory($engines, new FileViewFinder($files, [$this->directorioVistas, dirname(__DIR__, 2).'/resources/views']), new Dispatcher($container));
        $vistas->setContainer($container);
        $vistas->share('errors', new ViewErrorBag);
        $vistas->addNamespace('pagination', dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Pagination/resources/views');
        AbstractPaginator::viewFactoryResolver(fn () => Container::getInstance()->make('view'));
        $container->instance('view', $vistas);
        $container->alias('view', \Illuminate\Contracts\View\Factory::class);
    }
}
