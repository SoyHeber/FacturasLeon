<?php

namespace Tests\Unit;

use App\Http\Controllers\DetalleCompraController;
use App\Http\Controllers\InventarioCompraController;
use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Http\Controllers\OpcionController;
use App\Http\Controllers\RolController;
use App\Models\Accion;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\Opcion;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\CompraInventarioTestCase;

class CierreIntegridadCompraInventarioTest extends CompraInventarioTestCase
{
    private Router $router;
    private ?Factory $vistas = null;
    private ?string $directorioVistas = null;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('inventarios_compra', function (Blueprint $table) {
            $table->string('descripcion')->nullable();
            $table->string('unidad_medida')->default('UNIDAD');
            $table->string('stock_minimo')->default('0.0000000000');
            $table->string('stock_maximo')->nullable();
            $table->string('ubicacion')->nullable();
        });
        Schema::table('producciones', function (Blueprint $table) {
            $table->boolean('estado')->default(true);
            $table->dateTime('fecha_produccion')->nullable();
        });

        $container = Container::getInstance();
        $this->router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $this->router);
        Route::clearResolvedInstance('router');
        require dirname(__DIR__, 2) . '/routes/web.php';
        $this->router->getRoutes()->refreshNameLookups();
        $request = Request::create('http://localhost');
        $session = new Store('cierre', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $container->instance('request', $request);
        $container->instance('session', $session);
        $url = new UrlGenerator($this->router->getRoutes(), $request);
        $url->setSessionResolver(fn () => $session);
        $container->instance('url', $url);
        $redirect = new Redirector($url);
        $redirect->setSession($session);
        $container->instance('redirect', $redirect);
    }

    protected function tearDown(): void
    {
        AbstractPaginator::viewFactoryResolver(fn () => Container::getInstance()->make('view'));
        if ($this->directorioVistas !== null) {
            foreach (glob($this->directorioVistas . '/cache/*') as $archivo) {
                unlink($archivo);
            }
            unlink($this->directorioVistas . '/layouts/app-bootstrap.blade.php');
            rmdir($this->directorioVistas . '/cache');
            rmdir($this->directorioVistas . '/layouts');
            rmdir($this->directorioVistas);
        }
        parent::tearDown();
    }

    public function test_detalles_solo_tienen_rutas_index_show_y_las_escrituras_son_404_405(): void
    {
        $rutas = $this->router->getRoutes();
        foreach (['create', 'store', 'edit', 'update', 'cambiar-estado'] as $accion) {
            $this->assertNull($rutas->getByName('detalles_compra.' . $accion));
        }
        foreach (['create', 'store', 'edit', 'update', 'cambiarEstado'] as $metodo) {
            $this->assertFalse(method_exists(DetalleCompraController::class, $metodo));
        }
        foreach (['index', 'show'] as $accion) {
            $ruta = $rutas->getByName('detalles_compra.' . $accion);
            $this->assertContains('auth', $ruta->middleware());
            $this->assertContains('permiso', $ruta->middleware());
        }
        foreach ([['GET', '/detalles-compra/create'], ['GET', '/detalles-compra/1/edit'], ['PATCH', '/detalles-compra/1/estado']] as [$metodo, $url]) {
            $this->rechaza(fn () => $rutas->match(Request::create($url, $metodo)), NotFoundHttpException::class);
        }
        foreach ([['POST', '/detalles-compra'], ['PUT', '/detalles-compra/1'], ['PATCH', '/detalles-compra/1'], ['DELETE', '/detalles-compra/1']] as [$metodo, $url]) {
            $this->rechaza(fn () => $rutas->match(Request::create($url, $metodo)), MethodNotAllowedHttpException::class);
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/resources/views/detalles_compra/create.blade.php');
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/resources/views/detalles_compra/edit.blade.php');
        $this->assertNull($rutas->getByName('compras.edit'));
        $this->assertNull($rutas->getByName('compras.update'));
    }

    public function test_vistas_de_detalles_solo_ofrecen_consulta_incluso_con_todos_los_permisos(): void
    {
        $this->registrarCompra();
        $this->prepararVistas();
        $controller = new DetalleCompraController;
        $html = $controller->index(Request::create('/detalles-compra'))->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Ver', $html);
        $this->assertStringContainsString('name="cantidad"', $html);
        $this->sinAccionesDeEdicion($html);
        $html = $controller->show(DetalleCompra::firstOrFail())->with('errors', new ViewErrorBag)->render();
        $this->sinAccionesDeEdicion($html);
        $this->assertStringContainsString('FACT', $html);
    }

    /** @dataProvider tiposAutomaticos */
    public function test_automaticos_rechazan_edit_update_y_estado_con_modelo_desactualizado(bool $salida): void
    {
        $compra = $this->registrarCompra();
        if ($salida) {
            $this->servicio()->anularCompra($compra->id, 17);
        }
        $movimiento = MovimientoInventarioCompra::where('tipo_movimiento', $salida ? 'SALIDA' : 'ENTRADA')->firstOrFail();
        $movimiento->detalle_compra_id = null;
        $antes = $this->snapshot();
        $controller = new MovimientoInventarioCompraController($this->stock);

        $this->rechazaValidacion(fn () => $controller->edit($movimiento), 'movimiento', 'son históricos');
        // El bloqueo y la protección deben ocurrir incluso con un formulario inválido o vacío.
        $this->rechazaValidacion(fn () => $controller->update(Request::create('/', 'PUT'), $movimiento), 'movimiento', 'son históricos');
        $this->rechazaValidacion(fn () => $controller->cambiarEstado($movimiento), 'movimiento', 'son históricos');
        $this->assertSame($antes, $this->snapshot());
    }

    public static function tiposAutomaticos(): array
    {
        return ['entrada' => [false], 'salida' => [true]];
    }

    public function test_automatico_no_puede_desvincularse_ni_inactivarse_desde_peticion_manipulada(): void
    {
        $this->registrarCompra();
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $datos = array_replace($this->datosMovimiento(), ['detalle_compra_id' => null, 'cantidad' => '999999', 'estado' => 0]);
        $antes = $this->snapshot();
        $controller = new MovimientoInventarioCompraController($this->stock);
        $this->rechazaValidacion(fn () => $controller->update(Request::create('/', 'PUT', $datos), $movimiento), 'movimiento', 'no pueden modificarse manualmente');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_proteccion_automatica_se_consulta_dentro_de_la_transaccion(): void
    {
        $this->registrarCompra();
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $lecturas = [];
        DB::listen(function ($query) use (&$lecturas) {
            if (str_contains($query->sql, 'from "movimientos_inventario_compra"') && str_contains($query->sql, 'where')) {
                $lecturas[] = DB::connection()->transactionLevel();
            }
        });
        $controller = new MovimientoInventarioCompraController($this->stock);
        $this->rechazaValidacion(fn () => $controller->update(Request::create('/', 'PUT'), $movimiento), 'movimiento', 'son históricos');
        $this->rechazaValidacion(fn () => $controller->cambiarEstado($movimiento), 'movimiento', 'son históricos');
        $this->assertSame([1, 1], $lecturas);
    }

    public function test_movimiento_manual_ignora_vinculo_a_compra_al_crear_y_editar(): void
    {
        $this->registrarCompra();
        $entradaOriginal = (array) DB::table('movimientos_inventario_compra')->first();
        $detalleId = DetalleCompra::firstOrFail()->id;
        $controller = new MovimientoInventarioCompraController($this->stock);
        $datos = array_replace($this->datosMovimiento(), ['detalle_compra_id' => $detalleId, 'cantidad' => '0.1']);
        $controller->store(Request::create('/', 'POST', $datos));
        $manual = MovimientoInventarioCompra::orderByDesc('id')->firstOrFail();
        $this->assertNull($manual->detalle_compra_id);
        $this->assertSame('11.1000000000', InventarioCompra::findOrFail(7)->cantidad);
        $datos['cantidad'] = '0.2';
        $controller->update(Request::create('/', 'PUT', $datos), $manual);
        $this->assertNull($manual->fresh()->detalle_compra_id);
        $this->assertSame('11.2000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame($entradaOriginal, (array) DB::table('movimientos_inventario_compra')->where('id', $entradaOriginal['id'])->first());
    }

    public function test_detalle_inexistente_enviado_manualmente_se_ignora_y_produccion_se_conserva(): void
    {
        DB::table('producciones')->insert(['id' => 101]);
        $datos = array_replace($this->datosMovimiento(), ['detalle_compra_id' => 999999, 'produccion_id' => 101]);
        (new MovimientoInventarioCompraController($this->stock))->store(Request::create('/', 'POST', $datos));
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $this->assertNull($movimiento->detalle_compra_id);
        $this->assertSame(101, (int) $movimiento->produccion_id);
        $this->assertSame('10.5000000000', InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_manual_conserva_edicion_inactivacion_activacion_y_ajustes_exactos(): void
    {
        $controller = new MovimientoInventarioCompraController($this->stock);
        $controller->store(Request::create('/', 'POST', $this->datosMovimiento()));
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $datos = array_replace($this->datosMovimiento(), ['cantidad' => '0.0000000001']);
        $controller->update(Request::create('/', 'PUT', $datos), $movimiento);
        $this->assertSame('10.0000000001', InventarioCompra::findOrFail(7)->cantidad);
        $controller->cambiarEstado($movimiento);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $controller->cambiarEstado($movimiento);
        $this->assertSame('10.0000000001', InventarioCompra::findOrFail(7)->cantidad);
        $datos['tipo_movimiento'] = 'AJUSTE';
        $datos['cantidad'] = '-0.0000000001';
        $controller->store(Request::create('/', 'POST', $datos));
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_crear_inventario_ignora_cantidad_y_utiliza_default_cero_exacto(): void
    {
        $controller = new InventarioCompraController($this->stock);
        foreach (['999999', 'valor inválido'] as $cantidad) {
            $datos = array_replace($this->datosInventario(), ['cantidad' => $cantidad]);
            $controller->store(Request::create('/', 'POST', $datos));
            $inventario = InventarioCompra::orderByDesc('id')->firstOrFail();
            $this->assertSame('0.0000000000', $inventario->cantidad);
            $this->assertSame('0.0000000000', $inventario->getRawOriginal('cantidad'));
        }
        $controller->store(Request::create('/', 'POST', $this->datosInventario()));
        $this->assertSame('0.0000000000', InventarioCompra::orderByDesc('id')->firstOrFail()->cantidad);
        $this->assertSame(0, MovimientoInventarioCompra::count());
    }

    public function test_editar_inventario_ignora_cantidad_y_conserva_stock_fresco(): void
    {
        $inventario = InventarioCompra::findOrFail(7);
        (new MovimientoInventarioCompraController($this->stock))->store(Request::create('/', 'POST', $this->datosMovimiento()));
        $this->assertSame('10.0000000000', $inventario->cantidad);
        $controller = new InventarioCompraController($this->stock);
        foreach (['999999', 'valor inválido'] as $cantidad) {
            $datos = array_replace($this->datosInventario(), ['cantidad' => $cantidad]);
            $controller->update(Request::create('/', 'PUT', $datos), $inventario);
            $this->assertSame('10.5000000000', $inventario->fresh()->cantidad);
            $this->assertSame('Material editado', $inventario->fresh()->nombre);
            $this->assertSame('0.0000000001', $inventario->fresh()->stock_minimo);
        }
        $controller->update(Request::create('/', 'PUT', $this->datosInventario()), $inventario);
        $this->assertSame('10.5000000000', $inventario->fresh()->cantidad);
        $this->assertSame(1, MovimientoInventarioCompra::count());
        $this->assertFalse(method_exists($this->stock, 'establecerCantidad'));
    }

    public function test_stock_inicial_se_ingresa_mediante_movimiento_con_trazabilidad(): void
    {
        (new InventarioCompraController($this->stock))->store(Request::create('/', 'POST', $this->datosInventario()));
        $inventario = InventarioCompra::orderByDesc('id')->firstOrFail();
        $datos = array_replace($this->datosMovimiento(), ['inventario_compra_id' => $inventario->id, 'cantidad' => '0.1234567890']);
        (new MovimientoInventarioCompraController($this->stock))->store(Request::create('/', 'POST', $datos));
        $this->assertSame('0.1234567890', $inventario->fresh()->cantidad);
        $this->assertNull(MovimientoInventarioCompra::firstOrFail()->detalle_compra_id);
    }

    public function test_formularios_no_ofrecen_cantidad_editable_ni_selector_de_detalle(): void
    {
        $this->prepararVistas();
        $inventarios = new InventarioCompraController($this->stock);
        foreach ([$inventarios->create(), $inventarios->edit(InventarioCompra::findOrFail(7))] as $vista) {
            $html = $vista->with('errors', new ViewErrorBag)->render();
            $this->assertStringNotContainsString('name="cantidad"', $html);
            $this->assertStringContainsString('name="stock_minimo"', $html);
            $this->assertStringContainsString('movimiento', $html);
        }
        $controller = new MovimientoInventarioCompraController($this->stock);
        $controller->store(Request::create('/', 'POST', $this->datosMovimiento()));
        foreach ([$controller->create(), $controller->edit(MovimientoInventarioCompra::firstOrFail())] as $vista) {
            $html = $vista->with('errors', new ViewErrorBag)->render();
            $this->assertStringNotContainsString('name="detalle_compra_id"', $html);
            $this->assertStringContainsString('name="produccion_id"', $html);
            $this->assertStringContainsString('name="cantidad"', $html);
        }
    }

    public function test_vistas_distinguen_origen_y_ocultan_acciones_solo_en_automaticos(): void
    {
        $this->registrarCompra();
        $this->prepararVistas();
        $controller = new MovimientoInventarioCompraController($this->stock);
        $automatico = MovimientoInventarioCompra::firstOrFail();
        $html = $controller->index(Request::create('/movimientos-inventario-compra'))->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Automático - Compra', $html);
        $this->assertStringContainsString('Compra #', $html);
        $this->sinAccionesDeEdicion($html);
        $html = $controller->show($automatico)->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Automático - Compra', $html);
        $this->sinAccionesDeEdicion($html);
        $controller->store(Request::create('/', 'POST', $this->datosMovimiento()));
        $manual = MovimientoInventarioCompra::orderByDesc('id')->firstOrFail();
        $html = $controller->show($manual)->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Manual', $html);
        $this->assertStringContainsString('Editar', $html);
        $this->assertStringContainsString('Inactivar', $html);
        $controller->cambiarEstado($manual);
        $html = $controller->show($manual->fresh())->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Activar', $html);
        $html = $controller->index(Request::create('/movimientos-inventario-compra'))->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Manual', $html);
        $this->assertStringContainsString('name="detalle_compra_id"', $html);
        $this->assertStringContainsString('Editar', $html);
    }

    /** @dataProvider estadosSalida */
    public function test_salida_preexistente_activa_o_inactiva_aborta_antes_de_stock(bool $activa): void
    {
        $compra = $this->registrarCompra();
        $salida = (array) DB::table('movimientos_inventario_compra')->first();
        unset($salida['id']);
        $salida['tipo_movimiento'] = 'SALIDA';
        $salida['estado'] = $activa;
        DB::table('movimientos_inventario_compra')->insert($salida);
        $antes = $this->snapshot();
        MovimientoInventarioCompra::creating(fn () => $this->fail('No debe crearse otra SALIDA.'));
        InventarioCompra::updating(fn () => $this->fail('No debe modificarse stock.'));
        $this->rechazaValidacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'compra', 'ya tiene una SALIDA');
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosSalida(): array
    {
        return ['activa' => [true], 'inactiva' => [false]];
    }

    public function test_ciclo_compra_entrada_anulacion_salida_se_conserva(): void
    {
        $compra = $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7, '0.1234567890'), $this->linea(44, '0.0000000001')]), 17);
        $detalles = $this->filas('detalles_compra');
        $this->assertSame('10.1234567890', InventarioCompra::findOrFail(7)->cantidad);
        Carbon::setTestNow('2026-10-02 15:00:00');
        $this->servicio()->anularCompra($compra->id, 17);
        $this->assertFalse($compra->fresh()->estado);
        $this->assertTrue($compra->fresh()->inventario_aplicado);
        $this->assertSame($detalles, $this->filas('detalles_compra'));
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame(4, MovimientoInventarioCompra::where('estado', true)->count());
        foreach (DetalleCompra::all() as $detalle) {
            $movimientos = $detalle->movimientosInventarioCompra()->get()->keyBy('tipo_movimiento');
            $this->assertSame($movimientos['ENTRADA']->cantidad, $movimientos['SALIDA']->cantidad);
        }
    }

    public function test_seeder_limpia_solo_permisos_obsoletos_y_es_idempotente(): void
    {
        $this->crearSeguridad();
        // Los IDs de acciones tampoco se presuponen.
        DB::table('acciones')->insert(['id' => 50, 'nombre' => 'Modificar', 'clave' => 'modificar']);
        (new SeguridadSeeder)->run();
        $rol = Rol::create(['nombre' => 'Operador']);
        $compras = Opcion::where('ruta', 'compras')->firstOrFail();
        $detalles = Opcion::where('ruta', 'detalles_compra')->firstOrFail();
        $movimientos = Opcion::where('ruta', 'movimientos_inventario_compra')->firstOrFail();
        $acciones = Accion::pluck('id', 'clave');
        $compras->acciones()->attach($acciones['modificar']);
        $detalles->acciones()->attach($acciones['crear']);
        foreach ([[$compras, 'ver'], [$compras, 'modificar'], [$detalles, 'ver'], [$detalles, 'crear'], [$movimientos, 'modificar']] as [$opcion, $clave]) {
            DB::table('roles_opciones_acciones')->insert(['rol_id' => $rol->id, 'opcion_id' => $opcion->id, 'accion_id' => $acciones[$clave]]);
        }
        (new SeguridadSeeder)->run();
        $this->assertSame(['crear', 'eliminar', 'ver'], $compras->acciones()->orderBy('clave')->pluck('clave')->all());
        $this->assertSame(['ver'], $detalles->acciones()->pluck('clave')->all());
        $this->assertSame(['crear', 'eliminar', 'modificar', 'ver'], $movimientos->acciones()->orderBy('clave')->pluck('clave')->all());
        $this->assertSame(4, Accion::count());
        $this->assertSame(3, DB::table('roles_opciones_acciones')->where('rol_id', $rol->id)->count());
        $antes = $this->filas('roles_opciones_acciones');
        (new SeguridadSeeder)->run();
        $this->assertSame($antes, $this->filas('roles_opciones_acciones'));
    }

    public function test_administracion_de_roles_no_ofrece_ni_guarda_acciones_inexistentes(): void
    {
        $this->crearSeguridad();
        (new SeguridadSeeder)->run();
        $this->prepararVistas();
        $rol = Rol::create(['nombre' => 'Operador']);
        $controller = new RolController;
        $datos = $controller->permisos($rol)->getData();
        $compras = $datos['modulos']->flatMap->opciones->firstWhere('ruta', 'compras');
        $detalles = $datos['modulos']->flatMap->opciones->firstWhere('ruta', 'detalles_compra');
        $modificar = Accion::where('clave', 'modificar')->firstOrFail();
        $ver = Accion::where('clave', 'ver')->firstOrFail();
        $html = $controller->permisos($rol)->with('errors', new ViewErrorBag)->render();
        $this->assertStringNotContainsString('id="permiso-' . $compras->id . '-' . $modificar->id . '"', $html);
        $this->assertStringNotContainsString('id="permiso-' . $detalles->id . '-' . $modificar->id . '"', $html);
        $this->assertStringContainsString('id="permiso-' . $detalles->id . '-' . $ver->id . '"', $html);
        $controller->guardarPermisos(Request::create('/', 'PUT', ['permisos' => [
            $compras->id => [$modificar->id, $ver->id], $detalles->id => [$modificar->id, $ver->id],
        ]]), $rol);
        $this->assertSame(2, DB::table('roles_opciones_acciones')->where('rol_id', $rol->id)->count());
        $this->assertSame(0, DB::table('roles_opciones_acciones')->where('rol_id', $rol->id)->where('accion_id', $modificar->id)->count());
    }

    public function test_opciones_no_permiten_rehabilitar_acciones_retiradas_por_peticion(): void
    {
        $this->crearSeguridad();
        (new SeguridadSeeder)->run();
        $this->prepararVistas();
        $controller = new OpcionController;
        foreach (['compras', 'detalles_compra'] as $ruta) {
            $opcion = Opcion::where('ruta', $ruta)->firstOrFail();
            $editar = $controller->edit($opcion);
            $modificar = Accion::where('clave', 'modificar')->firstOrFail();
            $this->assertNotContains('modificar', $editar->getData()['acciones']->pluck('clave')->all());
            $html = $editar->with('errors', new ViewErrorBag)->render();
            $this->assertStringNotContainsString('id="accion-' . $modificar->id . '"', $html);
            $antes = $opcion->acciones()->pluck('accion_id')->all();
            $datos = ['modulo_id' => $opcion->modulo_id, 'nombre' => $opcion->nombre, 'ruta' => $ruta, 'acciones' => [$modificar->id]];
            $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', $datos), $opcion), ValidationException::class);
            $this->assertSame($antes, $opcion->acciones()->pluck('accion_id')->all());
        }
    }

    private function registrarCompra(): Compra
    {
        return $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7)]), 17);
    }

    private function datosMovimiento(): array
    {
        return ['inventario_compra_id' => 7, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => '0.5',
            'fecha_movimiento' => '2026-10-02 12:30:00', 'estado' => 1];
    }

    private function datosInventario(): array
    {
        return ['nombre' => 'Material editado', 'descripcion' => 'Descripción nueva', 'unidad_medida' => 'g',
            'stock_minimo' => '0.0000000001', 'stock_maximo' => '0.0000000002', 'ubicacion' => 'Bodega', 'estado' => 1];
    }

    private function filas(string $tabla): array
    {
        return DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function snapshot(): array
    {
        $this->assertSame(0, DB::connection()->transactionLevel());
        return array_map(fn ($tabla) => $this->filas($tabla), ['compras', 'detalles_compra', 'movimientos_inventario_compra', 'inventarios_compra']);
    }

    private function rechazaValidacion(callable $operacion, string $campo, string $mensaje): void
    {
        try {
            $operacion();
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($mensaje, implode(' ', $exception->errors()[$campo]));
            $this->assertSame(0, DB::connection()->transactionLevel());
            return;
        }
        $this->fail('La operación debía rechazarse.');
    }

    private function sinAccionesDeEdicion(string $html): void
    {
        foreach (['Editar', 'Activar', 'Inactivar', '+ Nuevo detalle', 'name="_method"'] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
    }

    private function crearSeguridad(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/database/migrations/2026_09_26_00000*.php') as $archivo) {
            (require $archivo)->up();
        }
    }

    private function prepararVistas(): void
    {
        if ($this->vistas !== null) {
            return;
        }
        $container = Container::getInstance();
        $container->instance('translator', new Translator(new ArrayLoader, 'es'));
        $gate = new Gate($container, fn () => User::findOrFail(17));
        $gate->before(fn () => true);
        $container->instance(GateContract::class, $gate);
        $this->directorioVistas = sys_get_temp_dir() . '/FacturasLeon-cierre-vistas-' . bin2hex(random_bytes(8));
        mkdir($this->directorioVistas . '/cache', 0777, true);
        mkdir($this->directorioVistas . '/layouts');
        file_put_contents($this->directorioVistas . '/layouts/app-bootstrap.blade.php', "@yield('content')");
        $files = new Filesystem;
        $compiler = new BladeCompiler($files, $this->directorioVistas . '/cache');
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
        $this->vistas = new Factory($engines, new FileViewFinder($files, [$this->directorioVistas, dirname(__DIR__, 2) . '/resources/views']), new Dispatcher($container));
        $this->vistas->setContainer($container);
        $this->vistas->addNamespace('pagination', dirname(__DIR__, 2) . '/vendor/laravel/framework/src/Illuminate/Pagination/resources/views');
        $container->instance('view', $this->vistas);
        $container->alias('view', \Illuminate\Contracts\View\Factory::class);
        AbstractPaginator::viewFactoryResolver(fn () => $this->vistas);
    }
}
