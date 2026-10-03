<?php

namespace Tests\Support;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Services\CalculadoraDteService;
use App\Services\CompraInventarioService;
use App\Services\InventarioCompraService;
use Carbon\Carbon;
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

abstract class CompraInventarioTestCase extends TestCase
{
    protected Manager $database;
    protected InventarioCompraService $stock;
    protected array $saldosIniciales;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:30:00'));
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);

        // Esta conexión no carga .env, phpunit.xml ni la configuración de MySQL.
        $this->database = new Manager($container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->database->setEventDispatcher(new Dispatcher($container));
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $container->instance('db', $this->database->getDatabaseManager());
        $container->bind('db.schema', fn () => $this->database->schema());
        $validator = new Factory(new Translator(new ArrayLoader, 'es'), $container);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->database->getDatabaseManager()));
        $container->instance('validator', $validator);
        Request::macro('validate', fn (array $rules, array $messages = []) => $validator->make($this->all(), $rules, $messages)->validate());
        $container->instance('auth', new class {
            public function id(): int
            {
                return 17;
            }
        });
        $container->alias('auth', \Illuminate\Contracts\Auth\Factory::class);
        $routes = new RouteCollection;
        $routes->add((new Route('GET', '/compras', fn () => null))->name('compras.index'));
        $redirect = new Redirector(new UrlGenerator($routes, Request::create('http://localhost')));
        $redirect->setSession(new Store('registro', new ArraySessionHandler(120)));
        $container->instance('redirect', $redirect);
        $this->stock = new InventarioCompraService;
        $this->crearEsquemaAislado();
        DB::table('users')->insert(['id' => 17]);
        DB::table('proveedores')->insert(['id' => 13]);
        DB::table('tipos_documento')->insert(['id' => 77, 'codigo' => 'FACT', 'nombre' => 'Factura', 'estado' => true]);
        DB::table('inventarios_compra')->insert([
            ['id' => 7, 'nombre' => 'Material A', 'cantidad' => '10.0000000000', 'estado' => 1, 'created_at' => '2020-01-01 12:00:00', 'updated_at' => '2020-01-01 12:00:00'],
            ['id' => 44, 'nombre' => 'Material B', 'cantidad' => '20.0000000000', 'estado' => 1, 'created_at' => '2020-01-01 12:00:00', 'updated_at' => '2020-01-01 12:00:00'],
        ]);
        $this->saldosIniciales = $this->snapshotInventarios();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Request::flushMacros();
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function servicio(?InventarioCompraService $stock = null): CompraInventarioService
    {
        return new CompraInventarioService($stock ?? $this->stock, new CalculadoraDteService);
    }

    protected function datosCompra(array $detalles): array
    {
        return ['proveedor_id' => 13, 'tipo_documento_id' => 77, 'serie' => 'A', 'numero' => 1,
            'numero_autorizacion' => '00000000-0000-0000-0000-000000000001', 'fecha_emision' => '2024-01-01 12:00:00',
            'moneda' => 'GTQ', 'detalles' => $detalles];
    }

    protected function linea(int $inventarioId, mixed $cantidad = '1'): array
    {
        return ['inventario_compra_id' => $inventarioId, 'cantidad' => $cantidad, 'precio_unitario' => '10.000000',
            'porcentaje_descuento' => '0.0000', 'importe_exento' => '0', 'importe_otros' => '0'];
    }

    protected function snapshotInventarios(): array
    {
        return DB::table('inventarios_compra')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    protected function sinComprasParciales(): void
    {
        $this->assertSame(0, Compra::count());
        $this->assertSame(0, DetalleCompra::count());
        $this->assertSame(0, MovimientoInventarioCompra::count());
        $this->assertSame($this->saldosIniciales, $this->snapshotInventarios());
        $this->assertSame(0, DB::connection()->transactionLevel());
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

    protected function crearEsquemaAislado(): void
    {
        foreach (['proveedores', 'producciones'] as $tabla) {
            Schema::create($tabla, fn (Blueprint $table) => $table->id());
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Operador de prueba');
        });
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
        });
        Schema::create('inventarios_compra', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // TEXT conserva el valor exacto: SQLite no tiene el DECIMAL nativo de MySQL.
            $table->string('cantidad')->default('0.0000000000');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('serie');
            $table->unsignedBigInteger('numero');
            $table->string('numero_autorizacion');
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_certificacion')->nullable();
            $table->string('moneda');
            foreach (['importe_bruto', 'importe_descuento', 'importe_exento', 'importe_otros', 'importe_neto', 'importe_iva', 'importe_total'] as $campo) {
                $table->string($campo)->default('0.00');
            }
            $table->string('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->boolean('inventario_aplicado')->default(false);
            $table->dateTime('fecha_anulacion')->nullable();
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('marca_activa')->nullable()->storedAs('CASE WHEN estado = 1 THEN 1 ELSE NULL END');
            $table->unique(['proveedor_id', 'serie', 'numero', 'marca_activa']);
            $table->unique(['numero_autorizacion', 'marca_activa']);
            $table->timestamps();
        });
        Schema::create('detalles_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->unsignedInteger('numero_linea');
            foreach (['cantidad', 'precio_unitario', 'porcentaje_descuento', 'importe_bruto', 'importe_descuento', 'importe_exento', 'importe_otros', 'importe_neto', 'importe_iva', 'importe_total'] as $campo) {
                $table->string($campo);
            }
            $table->string('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->unique(['compra_id', 'numero_linea']);
            $table->timestamps();
        });
        Schema::create('movimientos_inventario_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->foreignId('detalle_compra_id')->nullable()->constrained('detalles_compra')->restrictOnDelete();
            $table->foreignId('produccion_id')->nullable()->constrained('producciones')->restrictOnDelete();
            $table->enum('tipo_movimiento', ['ENTRADA', 'SALIDA', 'AJUSTE']);
            $table->string('cantidad');
            $table->dateTime('fecha_movimiento');
            $table->string('motivo')->nullable();
            $table->string('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->unique(['detalle_compra_id', 'tipo_movimiento']);
            $table->timestamps();
        });
    }
}
