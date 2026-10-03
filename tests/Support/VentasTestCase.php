<?php

namespace Tests\Support;

use App\Http\Controllers\VentaController;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Auth\Access\Gate;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
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
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class VentasTestCase extends TestCase
{
    protected Manager $database;

    protected VentaController $controller;

    protected Router $router;

    protected string $directorioVistas;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $container->instance('config', new Repository(['app' => ['key' => 'clave-de-prueba', 'debug' => true]]));
        $configFel = require dirname(__DIR__, 2).'/config/fel.php';
        config([
            'fel.tipos_identificacion_locales' => $configFel['tipos_identificacion_locales'],
            'fel.tipos_receptor_ainnova' => $configFel['tipos_receptor_ainnova'],
        ]);

        // No se inicia Laravel ni se carga .env: la única conexión es SQLite en memoria.
        $this->database = new Manager($container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $events = new Dispatcher($container);
        $this->database->setEventDispatcher($events);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        Model::clearBootedModels();
        $container->instance('db', $this->database->getDatabaseManager());
        $container->bind('db.schema', fn () => $this->database->schema());
        $container->instance('events', $events);
        $validator = new Factory(new Translator(new ArrayLoader, 'es'), $container);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->database->getDatabaseManager()));
        $container->instance('validator', $validator);
        Request::macro('validate', fn (array $rules) => $validator->make($this->all(), $rules)->validate());
        $this->router = new Router($events, $container);
        $container->instance('router', $this->router);
        require dirname(__DIR__, 2).'/routes/web.php';
        $this->router->getRoutes()->refreshNameLookups();
        $request = Request::create('http://localhost/ventas');
        $session = new Store('ventas', new ArraySessionHandler(120));
        $request->setLaravelSession($session);
        $container->instance('request', $request);
        $container->instance('session', $session);
        $url = new UrlGenerator($this->router->getRoutes(), $request);
        $container->instance('url', $url);
        $redirect = new Redirector($url);
        $redirect->setSession($session);
        $container->instance('redirect', $redirect);
        $gate = new Gate($container, fn () => User::findOrFail(7));
        $gate->before(function ($user, $ability) {
            [$opcion, $accion] = explode('.', $ability);

            return $user->tienePermiso($opcion, $accion);
        });
        $container->instance(\Illuminate\Contracts\Auth\Access\Gate::class, $gate);
        $this->crearEsquema();
        $this->crearVistas($container, $events);
        $this->controller = new VentaController;
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            $this->database->getDatabaseManager()->disconnect();
        }
        Facade::clearResolvedInstances();
        Request::flushMacros();
        Model::clearBootedModels();
        if (isset($this->directorioVistas)) {
            $destino = str_replace('\\', '/', (string) realpath($this->directorioVistas));
            $temporal = str_replace('\\', '/', (string) realpath(sys_get_temp_dir())).'/facturas-leon-ventas-';
            if (! str_starts_with($destino, $temporal)) {
                throw new \RuntimeException('El directorio de vistas no pertenece al temporal de estas pruebas.');
            }
            (new Filesystem)->deleteDirectory($this->directorioVistas);
        }
        parent::tearDown();
    }

    protected function datos(array $cambios = []): array
    {
        return array_replace([
            'cliente_id' => 11, 'tipo_documento_id' => 17, 'metodo_pago_id' => 6,
            'fecha' => '2026-10-03', 'moneda' => 'GTQ', 'observacion' => 'Venta de prueba',
            'detalles' => [$this->linea()],
        ], $cambios);
    }

    protected function linea(array $cambios = []): array
    {
        return array_replace(['producto_id' => 31, 'cantidad' => '2', 'precio_unitario' => '112',
            'porcentaje_descuento' => '10', 'tratamiento_tributario' => 'GRAVADO'], $cambios);
    }

    protected function request(array $datos, string $metodo = 'POST'): Request
    {
        $container = Container::getInstance();
        $request = Request::create('http://localhost/ventas', $metodo, $datos);
        $request->setUserResolver(fn () => User::findOrFail(7));
        $request->setLaravelSession($container->make('session'));
        $container->instance('request', $request);

        return $request;
    }

    protected function crearVenta(array $cambios = []): Venta
    {
        $this->controller->store($this->request($this->datos($cambios)));

        return Venta::latest('id')->firstOrFail();
    }

    protected function rechaza(callable $operacion, string $clase): void
    {
        try {
            $operacion();
        } catch (Throwable $exception) {
            $this->assertInstanceOf($clase, $exception);

            return;
        }
        $this->fail('La operación debía rechazarse.');
    }

    private function crearEsquema(): void
    {
        $migraciones = [
            '2014_10_12_000000_create_users_table', '2026_04_24_015142_create_categorias_table',
            '2026_04_28_034635_create_marcas_table', '2026_05_07_054259_create_paises_table',
            '2026_05_08_014239_create_departamentos_table', '2026_05_08_021717_create_municipios_table',
            '2026_05_10_230226_create_metodos_pago_table', '2026_05_10_233700_create_tipos_identificacion_table',
            '2026_05_11_032431_create_direcciones_table', '2026_05_19_053449_create_clientes_table',
            '2026_08_26_020414_remove_nombre_from_clientes_table', '2026_08_26_020700_create_personas_table',
            '2026_08_26_021226_create_sociedades_table', '2026_08_15_005246_create_productos_table',
            '2026_08_15_020605_create_inventarios_table', '2026_08_26_040638_create_movimientos_inventario_table',
            '2026_09_26_000001_create_modulos_table', '2026_09_26_000002_create_opciones_table',
            '2026_09_26_000003_create_acciones_table', '2026_09_26_000004_create_opciones_acciones_table',
            '2026_09_26_000005_create_roles_table', '2026_09_26_000006_create_roles_usuarios_table',
            '2026_09_26_000007_create_roles_opciones_acciones_table', '2026_10_02_000001_create_tipos_documento_table',
            '2026_10_03_000004_create_ventas_table', '2026_10_03_000005_create_detalles_ventas_table',
            '2026_10_03_000006_create_documentos_fel_table', '2026_10_03_000007_create_intentos_fel_table',
        ];
        foreach ($migraciones as $migracion) {
            (require dirname(__DIR__, 2).'/database/migrations/'.$migracion.'.php')->up();
        }
        // SQLite exige declarar la FK al agregar la columna, en lugar de ALTER ADD CONSTRAINT.
        DB::statement('ALTER TABLE intentos_fel ADD COLUMN usuario_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT');
        DB::table('users')->insert(['id' => 7, 'name' => 'Usuario de prueba', 'email' => 'prueba@example.test', 'password' => 'sin-uso']);
        DB::table('paises')->insert(['id' => 1, 'nombre' => 'Guatemala', 'codigo' => 'GT']);
        DB::table('departamentos')->insert(['id' => 2, 'pais_id' => 1, 'nombre' => 'Guatemala', 'codigo' => '01']);
        DB::table('municipios')->insert(['id' => 3, 'departamento_id' => 2, 'nombre' => 'Guatemala', 'codigo' => '0101']);
        DB::table('direcciones')->insert(['id' => 4, 'municipio_id' => 3, 'direccion' => 'Zona 1', 'referencia' => 'Frente al parque', 'codigo_postal' => '01001']);
        DB::table('tipos_identificacion')->insert(['id' => 5, 'codigo' => '1', 'nombre' => 'Número de identificación tributaria']);
        DB::table('metodos_pago')->insert(['id' => 6, 'nombre' => 'Efectivo']);
        DB::table('clientes')->insert([
            ['id' => 11, 'tipo_identificacion_id' => 5, 'direccion_id' => 4, 'numero_identificacion' => '1234567K', 'correo' => 'ana@example.test'],
            ['id' => 12, 'tipo_identificacion_id' => 5, 'direccion_id' => 4, 'numero_identificacion' => '7654321K', 'correo' => null],
        ]);
        DB::table('personas')->insert(['cliente_id' => 11, 'nombre1' => 'Ana', 'nombre2' => 'María', 'apellido1' => 'López', 'apellido2' => 'Pérez']);
        DB::table('sociedades')->insert(['cliente_id' => 12, 'nombre' => 'Empresa Ejemplo, S.A.']);
        DB::table('tipos_documento')->insert([
            ['id' => 17, 'codigo' => 'FACT', 'nombre' => 'Factura'], ['id' => 18, 'codigo' => 'OTRO', 'nombre' => 'Otro documento'],
        ]);
        DB::table('categorias')->insert(['id' => 1, 'nombre' => 'Anillos']);
        DB::table('marcas')->insert(['id' => 1, 'nombre' => 'León']);
        DB::table('productos')->insert([
            ['id' => 31, 'codigo' => 'AN-01', 'nombre' => 'Anillo', 'descripcion' => 'Anillo de plata', 'categoria_id' => 1, 'marca_id' => 1],
            ['id' => 32, 'codigo' => 'PU-02', 'nombre' => 'Pulsera', 'descripcion' => null, 'categoria_id' => 1, 'marca_id' => 1],
        ]);
        DB::table('inventarios')->insert(['producto_id' => 31, 'cantidad' => 5]);
        (new SeguridadSeeder)->run();
    }

    private function crearVistas(Container $container, Dispatcher $events): void
    {
        $files = new Filesystem;
        $this->directorioVistas = sys_get_temp_dir().'/facturas-leon-ventas-'.bin2hex(random_bytes(8));
        $files->makeDirectory($this->directorioVistas.'/layouts', 0755, true);
        $files->makeDirectory($this->directorioVistas.'/cache');
        $files->put($this->directorioVistas.'/layouts/app-bootstrap.blade.php', "@yield('content') @stack('scripts')");
        $compiler = new BladeCompiler($files, $this->directorioVistas.'/cache');
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
        $view = new ViewFactory($engines, new FileViewFinder($files, [$this->directorioVistas, dirname(__DIR__, 2).'/resources/views']), $events);
        $view->setContainer($container);
        $view->share('errors', new ViewErrorBag);
        $view->addNamespace('pagination', dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Pagination/resources/views');
        $container->instance('view', $view);
        $container->alias('view', \Illuminate\Contracts\View\Factory::class);
        AbstractPaginator::viewFactoryResolver(fn () => Container::getInstance()->make('view'));
        AbstractPaginator::useBootstrapFive();
    }
}
