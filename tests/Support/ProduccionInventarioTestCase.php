<?php

namespace Tests\Support;

use App\Models\MaterialProducto;
use App\Models\Produccion;
use App\Services\CalculadoraProduccionService;
use App\Services\InventarioCompraService;
use App\Services\InventarioService;
use App\Services\ProduccionInventarioService;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

abstract class ProduccionInventarioTestCase extends CompraInventarioTestCase
{
    protected Produccion $produccion;

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
        Schema::create('producciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->dateTime('fecha_produccion');
            $table->text('observacion')->nullable();
            $table->boolean('estado')->default(true);
            $table->enum('estado_produccion', ['LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA'])->default('BORRADOR');
            $table->boolean('inventario_aplicado')->default(false);
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->foreignId('confirmado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('inventarios_compra', function (Blueprint $table) {
            $table->string('unidad_medida', 30)->default('g');
        });
        Schema::create('materiales_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->string('cantidad_requerida');
            $table->boolean('estado')->default(true);
            $table->unique(['producto_id', 'inventario_compra_id']);
            $table->timestamps();
        });
        // SQLite conserva los decimales como texto para probar cantidades exactas.
        Schema::create('consumos_produccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produccion_id')->constrained('producciones')->restrictOnDelete();
            $table->foreignId('inventario_compra_id')->constrained('inventarios_compra')->restrictOnDelete();
            $table->foreignId('material_producto_id')->constrained('materiales_producto')->restrictOnDelete();
            $table->string('nombre_material', 100);
            $table->string('unidad_medida', 30);
            $table->string('cantidad_requerida');
            $table->unsignedInteger('cantidad_producida');
            $table->string('cantidad_consumida');
            $table->unique(['produccion_id', 'inventario_compra_id'], 'consumos_produccion_inventario_unique');
            $table->timestamps();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_02_000012_agregar_consumo_a_movimientos_inventario_compra.php')->up();
        EsquemaProductoTerminado::crear();
        MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '0.1234567890', 'estado' => true]);
        $this->produccion = Produccion::create(['producto_id' => 3, 'user_id' => 17, 'cantidad' => '3',
            'fecha_produccion' => '2026-10-01 08:00:00', 'observacion' => 'Borrador', 'estado' => true]);
        $this->produccion->refresh();

        $container = Container::getInstance();
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Route::clearResolvedInstance('router');
        require dirname(__DIR__, 2).'/routes/web.php';
        $router->getRoutes()->refreshNameLookups();
        $container->make('redirect')->getUrlGenerator()->setRoutes($router->getRoutes());
    }

    protected function servicioProduccion(?InventarioCompraService $stock = null, ?CalculadoraProduccionService $calculadora = null, ?InventarioService $terminado = null): ProduccionInventarioService
    {
        return new ProduccionInventarioService($stock ?? new InventarioCompraService, $calculadora ?? new CalculadoraProduccionService, $terminado ?? new InventarioService);
    }

    protected function confirmar(): Produccion
    {
        return $this->servicioProduccion()->confirmarProduccion($this->produccion->id, 17);
    }

    protected function agregarMaterial(string $cantidad = '0.0000000001'): void
    {
        MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 44, 'cantidad_requerida' => $cantidad, 'estado' => true]);
    }

    protected function snapshot(): array
    {
        $this->assertSame(0, DB::connection()->transactionLevel());

        return array_map(fn ($tabla) => DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all(),
            ['producciones', 'consumos_produccion', 'movimientos_inventario_compra', 'inventarios_compra', 'inventarios', 'movimientos_inventario']);
    }
}
