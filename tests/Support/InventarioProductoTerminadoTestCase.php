<?php

namespace Tests\Support;

use App\Models\Inventario;
use App\Services\InventarioService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use Mockery;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class InventarioProductoTerminadoTestCase extends TestCase
{
    protected Manager $database;

    protected InventarioService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);

        // Solo SQLite en memoria; no se carga Laravel ni .env.
        $this->database = new Manager($container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->database->setEventDispatcher(new Dispatcher($container));
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        $container->instance('db', $this->database->getDatabaseManager());
        $container->bind('db.schema', fn () => $this->database->schema());
        $validator = new Factory(new Translator(new ArrayLoader, 'es'), $container);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->database->getDatabaseManager()));
        $container->instance('validator', $validator);
        Request::macro('validate', fn (array $rules) => $validator->make($this->all(), $rules)->validate());

        $routes = new RouteCollection;
        foreach (['inventarios', 'movimientos_inventario'] as $nombre) {
            $routes->add((new Route('GET', '/'.$nombre, fn () => null))->name($nombre.'.index'));
        }
        $redirect = new Redirector(new UrlGenerator($routes, Request::create('http://localhost')));
        $redirect->setSession(new Store('inventario', new ArraySessionHandler(120)));
        $container->instance('redirect', $redirect);
        $this->stock = new InventarioService;
        $this->crearEsquema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Request::flushMacros();
        parent::tearDown();
    }

    protected function migracion(string $nombre): object
    {
        return require dirname(__DIR__, 2).'/database/migrations/2026_10_03_'.$nombre.'.php';
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

    protected function saldo(int $id = 7): string
    {
        return Inventario::findOrFail($id)->cantidad;
    }

    protected function datosMovimiento(array $cambios = []): array
    {
        return array_replace(['inventario_id' => 7, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => '10',
            'fecha_movimiento' => '2026-10-03 12:00:00', 'estado' => 1], $cambios);
    }

    protected function snapshot(): array
    {
        return [DB::table('inventarios')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all(),
            DB::table('movimientos_inventario')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all()];
    }

    private function crearEsquema(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
        });
        Schema::create('producciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->string('estado_produccion')->default('CONFIRMADA');
            $table->boolean('inventario_aplicado')->default(true);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->restrictOnDelete();
            // TEXT conserva el rango UNSIGNED completo que SQLite no representa como INTEGER.
            $table->string('cantidad')->default('0');
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->unsignedInteger('stock_maximo')->nullable();
            $table->string('ubicacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->restrictOnDelete();
            $table->foreignId('produccion_id')->nullable()->constrained('producciones')->restrictOnDelete();
            $table->foreignId('detalle_venta_id')->nullable();
            $table->index('produccion_id', 'movimientos_inventario_produccion_id_index');
            $table->unique(['produccion_id', 'tipo_movimiento'], 'movimientos_inventario_produccion_tipo_unique');
            $table->string('tipo_movimiento');
            $table->string('cantidad');
            $table->dateTime('fecha_movimiento');
            $table->string('motivo')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        DB::table('productos')->insert([
            ['id' => 3, 'codigo' => 'A', 'nombre' => 'Producto A'],
            ['id' => 4, 'codigo' => 'B', 'nombre' => 'Producto B'],
            ['id' => 5, 'codigo' => 'C', 'nombre' => 'Producto C'],
        ]);
        DB::table('inventarios')->insert([
            ['id' => 7, 'producto_id' => 3, 'cantidad' => '20'],
            ['id' => 44, 'producto_id' => 4, 'cantidad' => '30'],
        ]);
        DB::table('producciones')->insert(['id' => 101, 'producto_id' => 3, 'cantidad' => 10]);
        $this->migracion('000003_agregar_producto_terminado_aplicado_a_producciones')->up();
    }
}
