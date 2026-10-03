<?php

namespace Tests\Unit;

use App\Http\Controllers\MaterialProductoController;
use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Http\Controllers\ProduccionController;
use App\Models\ConsumoProduccion;
use App\Models\MaterialProducto;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Models\User;
use App\Services\CalculadoraProduccionService;
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
        $controller->store(Request::create('/', 'POST', ['producto_id' => 5, 'inventario_compra_id' => 44, 'cantidad_requerida' => '0.0000000001']));
        $material = MaterialProducto::where('producto_id', 5)->firstOrFail();
        $this->assertSame('0.0000000001', $material->cantidad_requerida);
        $this->assertSame(['productos', 'materiales_producto'], array_column($grammar->bloqueos, 'tabla'));
        $grammar->bloqueos = [];
        $controller->update(Request::create('/', 'PUT', ['producto_id' => 3, 'inventario_compra_id' => 44,
            'cantidad_requerida' => '9999999999.9999999999', 'estado' => true]), $material);
        $this->assertSame(['productos', 'productos', 'materiales_producto', 'materiales_producto'], array_column($grammar->bloqueos, 'tabla'));
        $this->assertSame([[3], [5], [$material->id], [3, 44, $material->id]], array_column($grammar->bloqueos, 'parametros'));
        $this->assertSame([1, 1, 1, 1], array_column($grammar->bloqueos, 'transaccion'));
        $material->refresh();
        $this->assertSame('9999999999.9999999999', $material->cantidad_requerida);
        $grammar->bloqueos = [];
        $controller->cambiarEstado($material);
        $this->assertSame(['productos', 'materiales_producto'], array_column($grammar->bloqueos, 'tabla'));
        $this->assertFalse($material->fresh()->estado);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_receta_update_usa_producto_actual_en_lugar_de_modelo_obsoleto(): void
    {
        $material = MaterialProducto::firstOrFail();
        DB::table('materiales_producto')->where('id', $material->id)->update(['producto_id' => 9]);
        $grammar = $this->registrarBloqueos();
        (new MaterialProductoController)->update(Request::create('/', 'PUT', ['producto_id' => 5,
            'inventario_compra_id' => 7, 'cantidad_requerida' => '0.1234567891', 'estado' => true]), $material);
        $this->assertSame([[5], [9], [$material->id], [5, 7, $material->id]], array_column($grammar->bloqueos, 'parametros'));
        $this->assertSame('0.1234567891', $material->fresh()->cantidad_requerida);
    }

    public function test_receta_duplicada_o_decimal_invalido_no_deja_cambios_parciales(): void
    {
        $antes = $this->filas('materiales_producto');
        $controller = new MaterialProductoController;
        $datos = ['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '1'];
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $datos)), ValidationException::class);
        foreach (['0', '0.00000000001', '10000000000', 0.1] as $cantidad) {
            $datos['cantidad_requerida'] = $cantidad;
            $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', $datos), MaterialProducto::firstOrFail()), ValidationException::class);
        }
        $this->assertSame($antes, $this->filas('materiales_producto'));
        $this->assertSame(0, DB::connection()->transactionLevel());
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
                if ($query->lock) {
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
