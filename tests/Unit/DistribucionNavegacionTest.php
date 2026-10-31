<?php

namespace Tests\Unit;

use App\Http\Middleware\VerificarPermiso;
use App\Models\Opcion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DistribucionNavegacionSeeder;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CompraInventarioTestCase;

class DistribucionNavegacionTest extends CompraInventarioTestCase
{
    private ?string $directorioVistas = null;

    protected function setUp(): void
    {
        parent::setUp();
        Model::clearBootedModels();
        foreach ([
            '2026_09_26_000001_create_modulos_table',
            '2026_09_26_000002_create_opciones_table',
            '2026_09_26_000003_create_acciones_table',
            '2026_09_26_000004_create_opciones_acciones_table',
            '2026_09_26_000005_create_roles_table',
            '2026_09_26_000006_create_roles_usuarios_table',
            '2026_09_26_000007_create_roles_opciones_acciones_table',
        ] as $migracion) {
            (require dirname(__DIR__, 2).'/database/migrations/'.$migracion.'.php')->up();
        }
    }

    protected function tearDown(): void
    {
        if ($this->directorioVistas !== null) {
            $ruta = realpath($this->directorioVistas);
            $prefijo = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'FacturasLeon-menu-vistas-';
            if ($ruta !== false && str_starts_with($ruta, $prefijo)) {
                (new Filesystem)->deleteDirectory($ruta);
            }
        }
        parent::tearDown();
    }

    public function test_reorganiza_las_opciones_sin_cambiar_identidades_estados_ni_permisos(): void
    {
        $this->cargarDistribucionAnterior();
        DB::table('opciones')->where('ruta', 'marcas')->update(['estado' => false]);
        $antes = $this->filas('opciones');
        $permisos = $this->fotoPermisos();
        $efectivos = User::findOrFail(17)->permisos();
        $stock = $this->snapshotInventarios();

        (new DistribucionNavegacionSeeder)->run();

        $this->assertSame(['Operaciones', 'Inventario', 'Producción', 'Reportes Generales', 'Personas', 'Catálogos', 'Seguridad'],
            DB::table('modulos')->orderBy('orden')->pluck('nombre')->all());
        $this->assertSame($permisos, $this->fotoPermisos());
        $this->assertSame($efectivos, User::findOrFail(17)->permisos());
        $this->assertSame($stock, $this->snapshotInventarios());
        $despues = $this->filas('opciones');
        $this->assertCount(26, $despues);
        foreach ($antes as $indice => $opcion) {
            foreach (['modulo_id', 'nombre', 'orden', 'updated_at'] as $campo) {
                unset($opcion[$campo], $despues[$indice][$campo]);
            }
            $this->assertSame($opcion, $despues[$indice]);
        }
        $usuario = User::findOrFail(17);
        $this->assertSame(['Operaciones', 'Inventario'], $usuario->menu()->pluck('nombre')->all());
        $this->assertSame(['compras', 'inventarios'], $usuario->menu()->flatMap(fn ($modulo) => $modulo->opciones)->pluck('ruta')->all());
        $this->assertFalse($usuario->tienePermiso('users', 'ver'));
    }

    public function test_la_distribucion_es_repetible_y_conserva_los_metadatos_personalizados(): void
    {
        $this->cargarDistribucionAnterior();
        $seeder = new DistribucionNavegacionSeeder;
        $seeder->run();
        $modulos = $this->filas('modulos');
        $opciones = $this->filas('opciones');
        Carbon::setTestNow('2026-10-03 10:00:00');
        $seeder->run();
        $this->assertSame($modulos, $this->filas('modulos'));
        $this->assertSame($opciones, $this->filas('opciones'));
        $this->assertSame('icono-propio', DB::table('modulos')->where('id', 1)->value('icono'));
        $this->assertSame('Descripción personalizada', DB::table('opciones')->where('ruta', 'inventarios')->value('descripcion'));
    }

    public function test_rechaza_movimientos_entre_modulos_que_cambiarian_el_acceso_efectivo(): void
    {
        $this->cargarDistribucionAnterior();
        DB::table('modulos')->where('nombre', 'Operaciones')->update(['estado' => false]);
        $modulos = $this->filas('modulos');
        $opciones = $this->filas('opciones');
        $permisos = User::findOrFail(17)->permisos();
        $this->rechaza(fn () => (new DistribucionNavegacionSeeder)->run(), RuntimeException::class);
        $this->assertSame($modulos, $this->filas('modulos'));
        $this->assertSame($opciones, $this->filas('opciones'));
        $this->assertSame($permisos, User::findOrFail(17)->permisos());
    }

    public function test_conflicto_de_nombre_revierte_toda_la_reorganizacion(): void
    {
        $this->cargarDistribucionAnterior();
        $id = DB::table('modulos')->insertGetId(['nombre' => 'Inventario', 'orden' => 20, 'estado' => true]);
        DB::table('opciones')->insert(['modulo_id' => $id, 'nombre' => 'Productos terminados', 'ruta' => 'consulta_propia', 'estado' => true]);
        $modulos = $this->filas('modulos');
        $opciones = $this->filas('opciones');
        $this->rechaza(fn () => (new DistribucionNavegacionSeeder)->run(), QueryException::class);
        $this->assertSame($modulos, $this->filas('modulos'));
        $this->assertSame($opciones, $this->filas('opciones'));
    }

    public function test_instalacion_nueva_registra_la_misma_distribucion_y_las_acciones_permitidas(): void
    {
        (new SeguridadSeeder)->run();
        $this->assertSame(7, DB::table('modulos')->count());
        $this->assertSame(34, DB::table('opciones')->count());
        $this->assertSame(['ver', 'crear', 'eliminar'], Opcion::where('ruta', 'compras')->firstOrFail()->acciones()->orderBy('acciones.id')->pluck('clave')->all());
        $this->assertSame(['ver'], Opcion::where('ruta', 'detalles_compra')->firstOrFail()->acciones()->pluck('clave')->all());
        $this->assertSame(['Operaciones', 'Inventario', 'Producción', 'Reportes Generales', 'Personas', 'Catálogos', 'Seguridad'],
            User::findOrFail(17)->menu()->pluck('nombre')->all());
        $this->assertSame('Inventario', Opcion::with('modulo')->where('ruta', 'inventarios_compra')->firstOrFail()->modulo->nombre);
        $this->assertSame('Personas', Opcion::with('modulo')->where('ruta', 'direcciones')->firstOrFail()->modulo->nombre);
        $this->assertSame('Catálogos', Opcion::with('modulo')->where('ruta', 'metodos_pago')->firstOrFail()->modulo->nombre);
    }

    public function test_contexto_muestra_modulo_y_seccion_tambien_para_quien_solo_puede_crear(): void
    {
        $this->cargarDistribucionAnterior();
        (new DistribucionNavegacionSeeder)->run();
        $usuario = User::findOrFail(17);
        $this->assertTrue($usuario->tienePermiso('inventarios_compra', 'crear'));
        $this->assertFalse($usuario->tienePermiso('inventarios_compra', 'ver'));
        $html = $this->contexto('inventarios_compra.create');
        $this->assertStringContainsString('Inventario', $html);
        $this->assertStringContainsString('Materias primas', $html);
        $this->assertStringNotContainsString('Producción', $html);
        $this->assertStringNotContainsString('Ubicación actual', $this->contexto('profile.edit'));
        $this->assertStringNotContainsString('Ubicación actual', $this->contexto('dashboard'));
    }

    public function test_el_middleware_permite_crear_sin_conceder_modificar(): void
    {
        $this->cargarDistribucionAnterior();
        (new DistribucionNavegacionSeeder)->run();
        $usuario = User::findOrFail(17);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $usuario);
        $request->setRouteResolver(fn () => (new Route('GET', '/', fn () => null))->name('inventarios_compra.create'));
        $this->assertSame(200, (new VerificarPermiso)->handle($request, fn () => new Response)->getStatusCode());
        $this->assertFalse($usuario->tienePermiso('inventarios_compra', 'modificar'));
    }

    private function cargarDistribucionAnterior(): void
    {
        $modulos = [
            [1, 'Catálogos', 1],
            [2, 'Operaciones', 2],
            [3, 'Producción', 3],
            [4, 'Personas', 4],
            [5, 'Seguridad', 5],
        ];
        foreach ($modulos as [$id, $nombre, $orden]) {
            DB::table('modulos')->insert(['id' => $id, 'nombre' => $nombre, 'orden' => $orden, 'icono' => 'icono-propio', 'estado' => true]);
        }
        $opciones = [
            [1, 1, 'Categorías', 'categorias', 1],
            [2, 1, 'Marcas', 'marcas', 2],
            [3, 1, 'Productos', 'productos', 3],
            [4, 1, 'Países', 'paises', 4],
            [5, 1, 'Departamentos', 'departamentos', 5],
            [6, 1, 'Municipios', 'municipios', 6],
            [7, 2, 'Inventarios', 'inventarios', 1],
            [8, 2, 'Movimientos Inventario', 'movimientos_inventario', 2],
            [9, 2, 'Compras', 'compras', 3],
            [10, 2, 'Detalles Compra', 'detalles_compra', 4],
            [11, 2, 'Inventarios Compra', 'inventarios_compra', 5],
            [12, 2, 'Movimientos Inventario Compra', 'movimientos_inventario_compra', 6],
            [13, 3, 'Materiales Producto', 'materiales_producto', 1],
            [14, 3, 'Producciones', 'producciones', 2],
            [15, 3, 'Direcciones', 'direcciones', 3],
            [16, 3, 'Métodos de Pago', 'metodos_pago', 4],
            [17, 3, 'Tipos de Identificación', 'tipos_identificacion', 5],
            [18, 4, 'Proveedores', 'proveedores', 1],
            [19, 4, 'Clientes', 'clientes', 2],
            [20, 5, 'Usuarios', 'users', 1],
            [21, 5, 'Roles', 'roles', 2],
            [22, 5, 'Módulos', 'modulos', 3],
            [23, 5, 'Opciones', 'opciones', 4],
            [24, 5, 'Acciones', 'acciones', 5],
            [25, 1, 'Tipos de Documento', 'tipos_documento', 7],
            [26, 2, 'Ventas', 'ventas', 1],
        ];
        foreach ($opciones as [$id, $modulo, $nombre, $ruta, $orden]) {
            DB::table('opciones')->insert(['id' => $id, 'modulo_id' => $modulo, 'nombre' => $nombre,
                'ruta' => $ruta, 'orden' => $orden, 'icono' => 'icono-propio', 'estado' => true,
                'descripcion' => 'Descripción personalizada']);
        }
        foreach (['ver', 'crear', 'modificar', 'eliminar'] as $indice => $clave) {
            DB::table('acciones')->insert(['id' => $indice + 1, 'nombre' => ucfirst($clave), 'clave' => $clave]);
        }
        DB::table('roles')->insert([['id' => 60, 'nombre' => 'Operador', 'estado' => true], ['id' => 61, 'nombre' => 'Inactivo', 'estado' => false]]);
        DB::table('roles_usuarios')->insert([['rol_id' => 60, 'user_id' => 17], ['rol_id' => 61, 'user_id' => 17]]);
        foreach (['compras' => [1], 'inventarios' => [1, 2], 'inventarios_compra' => [2]] as $ruta => $acciones) {
            foreach ($acciones as $accion) {
                $id = DB::table('opciones')->where('ruta', $ruta)->value('id');
                DB::table('opciones_acciones')->insert(['opcion_id' => $id, 'accion_id' => $accion]);
                DB::table('roles_opciones_acciones')->insert(['rol_id' => 60, 'opcion_id' => $id, 'accion_id' => $accion]);
            }
        }
        DB::table('opciones_acciones')->insert(['opcion_id' => 20, 'accion_id' => 1]);
        DB::table('roles_opciones_acciones')->insert(['rol_id' => 61, 'opcion_id' => 20, 'accion_id' => 1]);
    }

    private function filas(string $tabla): array
    {
        return DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function fotoPermisos(): array
    {
        return array_map(fn ($tabla) => $this->filas($tabla), ['acciones', 'roles', 'roles_usuarios', 'opciones_acciones', 'roles_opciones_acciones']);
    }

    private function contexto(string $ruta): string
    {
        $container = Container::getInstance();
        if ($this->directorioVistas === null) {
            $this->directorioVistas = sys_get_temp_dir().'/FacturasLeon-menu-vistas-'.bin2hex(random_bytes(8));
            mkdir($this->directorioVistas);
            $files = new Filesystem;
            $compiler = new BladeCompiler($files, $this->directorioVistas);
            $engines = new EngineResolver;
            $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
            $view = new Factory($engines, new FileViewFinder($files, [dirname(__DIR__, 2).'/resources/views']), new Dispatcher($container));
            $view->setContainer($container);
            $container->instance('view', $view);
        }
        $request = Request::create('/');
        $request->setRouteResolver(fn () => (new Route('GET', '/', fn () => null))->name($ruta));
        $container->instance('request', $request);

        return $container->make('view')->make('layouts.ubicacion-modulo')->render();
    }
}
