<?php

namespace Tests\Unit;

use App\Http\Controllers\CompraController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\User;
use App\Services\CompraInventarioService;
use App\Services\InventarioCompraService;
use Carbon\Carbon;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\CompraInventarioTestCase;

class AnulacionCompraInventarioTest extends CompraInventarioTestCase
{
    private ?string $directorioVistas = null;

    protected function tearDown(): void
    {
        AbstractPaginator::viewFactoryResolver(fn () => Container::getInstance()->make('view'));

        if ($this->directorioVistas !== null) {
            // Solo se retiran los archivos temporales creados por esta prueba.
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

    public function test_anula_una_linea_con_trazabilidad_y_saldo_exactos(): void
    {
        $compra = $this->crearCompra([$this->linea(7, '1.23456')]);
        $detallesAntes = $this->filas('detalles_compra');
        $entradasAntes = $this->filas('movimientos_inventario_compra');
        $cabeceraAntes = (array) DB::table('compras')->first();
        Carbon::setTestNow('2026-10-02 14:00:00');

        $anulada = $this->servicio()->anularCompra($compra->id, 17);

        $this->assertFalse($anulada->estado);
        $this->assertTrue($anulada->inventario_aplicado);
        $this->assertSame('2026-10-02 14:00:00', $anulada->fecha_anulacion->format('Y-m-d H:i:s'));
        $this->assertSame(17, (int) $anulada->anulado_por_id);
        $this->assertSame('Operador de prueba', $anulada->anuladoPor->name);
        $this->assertNull($anulada->fresh()->marca_activa);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame($detallesAntes, $this->filas('detalles_compra'));
        $this->assertSame($entradasAntes[0], (array) DB::table('movimientos_inventario_compra')->where('tipo_movimiento', 'ENTRADA')->first());
        $this->assertSame(1, Compra::count());
        $this->assertSame(1, DetalleCompra::count());

        $salida = MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->firstOrFail();
        $this->assertTrue($salida->estado);
        $this->assertNull($salida->produccion_id);
        $this->assertSame(DetalleCompra::firstOrFail()->cantidad, $salida->cantidad);
        $this->assertSame($anulada->fecha_anulacion->format('Y-m-d H:i:s'), $salida->fecha_movimiento->format('Y-m-d H:i:s'));
        $this->assertSame('Anulación de compra #' . $compra->id . ', línea 1.', $salida->motivo);

        $cabeceraDespues = (array) DB::table('compras')->first();
        foreach (['estado', 'fecha_anulacion', 'anulado_por_id', 'marca_activa', 'updated_at'] as $campo) {
            unset($cabeceraAntes[$campo], $cabeceraDespues[$campo]);
        }
        $this->assertSame($cabeceraAntes, $cabeceraDespues);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_varias_lineas_y_varios_inventarios_se_bloquean_una_vez_en_orden(): void
    {
        $compra = $this->crearCompra([$this->linea(44, '0.2'), $this->linea(7, '0.1'), $this->linea(44, '0.00001'), $this->linea(7, '0.2')]);
        $lecturas = [];
        DB::listen(function ($query) use (&$lecturas) {
            if (str_starts_with($query->sql, 'select * from "inventarios_compra"')) {
                $lecturas[] = (int) $query->bindings[0];
            }
        });

        $this->servicio()->anularCompra($compra->id, 17);

        $this->assertSame([7, 44], $lecturas);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame(4, MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->count());
        foreach (DetalleCompra::all() as $detalle) {
            $movimientos = $detalle->movimientosInventarioCompra()->get()->keyBy('tipo_movimiento');
            $this->assertTrue($movimientos['ENTRADA']->estado);
            $this->assertTrue($movimientos['SALIDA']->estado);
            $this->assertSame($movimientos['ENTRADA']->cantidad, $movimientos['SALIDA']->cantidad);
            $this->assertSame($detalle->inventario_compra_id, $movimientos['SALIDA']->inventario_compra_id);
        }
    }

    public function test_precision_de_diez_decimales_y_cantidad_maxima_se_revierten_exactamente(): void
    {
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '0.0000000000']);
        $compra = $this->crearCompra([$this->linea(7, '9999999999.9999999998'), $this->linea(7, '0.0000000001')]);
        $this->assertSame('9999999999.9999999999', InventarioCompra::findOrFail(7)->cantidad);

        $this->servicio()->anularCompra($compra->id, 17);

        $this->assertSame('0.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(['9999999999.9999999998', '0.0000000001'], MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->orderBy('id')->pluck('cantidad')->all());
    }

    public function test_segunda_anulacion_y_modelo_desactualizado_no_reactivan_ni_reaplican_stock(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        $controller = new CompraController;
        $controller->cambiarEstado($compra, $this->servicio());
        $antes = $this->snapshot();

        $this->assertTrue($compra->estado);
        $this->rechazaAnulacion(fn () => $controller->cambiarEstado($compra, $this->servicio()), 'ya fue anulada');
        $this->assertSame($antes, $this->snapshot());
        $this->assertFalse($compra->fresh()->estado);
    }

    public function test_historica_se_rechaza_sin_inferir_bandera_por_entradas_existentes(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        DB::table('compras')->where('id', $compra->id)->update(['inventario_aplicado' => 0]);
        $antes = $this->snapshot();

        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'Esta compra es histórica');
        $this->assertSame($antes, $this->snapshot());

        DB::table('movimientos_inventario_compra')->delete();
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'Debe conciliarse');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_entrada_faltante_o_sin_detalle_rechaza_toda_la_anulacion(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44)]);
        DB::table('movimientos_inventario_compra')->where('id', 2)->update(['detalle_compra_id' => null]);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'exactamente una ENTRADA');
        $this->assertSame($antes, $this->snapshot());
        DB::table('movimientos_inventario_compra')->where('id', 2)->delete();
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'exactamente una ENTRADA');
        $this->assertSame($antes, $this->snapshot());
    }

    /** @dataProvider entradasAlteradas */
    public function test_entrada_alterada_rechaza_todo_sin_modificar_stock(array $cambios): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44)]);
        DB::table('producciones')->insert(['id' => 101]);
        DB::table('movimientos_inventario_compra')->where('id', 2)->update($cambios);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'ENTRADA original');
        $this->assertSame($antes, $this->snapshot());
    }

    public static function entradasAlteradas(): array
    {
        return [
            'inactiva' => [['estado' => 0]],
            'produccion' => [['produccion_id' => 101]],
            'inventario incorrecto' => [['inventario_compra_id' => 7]],
            'cantidad alterada' => [['cantidad' => '1.0000000001']],
            'cantidad cero' => [['cantidad' => '0.0000000000']],
            'cantidad negativa' => [['cantidad' => '-1.0000000000']],
        ];
    }

    public function test_detalle_alterado_y_compra_sin_detalles_se_rechazan(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        DB::table('detalles_compra')->update(['cantidad' => '2.0000000000']);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'idéntica');
        $this->assertSame($antes, $this->snapshot());
        DB::table('movimientos_inventario_compra')->delete();
        DB::table('detalles_compra')->delete();
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'no tiene detalles');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_dos_entradas_para_un_detalle_se_rechazan_aun_sin_unique(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        Schema::table('movimientos_inventario_compra', function ($table) {
            $table->dropUnique(['detalle_compra_id', 'tipo_movimiento']);
        });
        $entrada = (array) DB::table('movimientos_inventario_compra')->first();
        unset($entrada['id']);
        DB::table('movimientos_inventario_compra')->insert($entrada);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'exactamente una ENTRADA');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_stock_se_comprueba_por_suma_completa_antes_de_crear_salidas(): void
    {
        $compra = $this->crearCompra([$this->linea(7, '2'), $this->linea(7, '3'), $this->linea(44)]);
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '4.9999999999']);
        $antes = $this->snapshot();
        MovimientoInventarioCompra::creating(fn () => $this->fail('No debe intentarse crear una SALIDA con stock total insuficiente.'));

        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'stock suficiente');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_insuficiencia_en_ultimo_inventario_no_crea_salidas_del_primero(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44, '2')]);
        DB::table('inventarios_compra')->where('id', 44)->update(['cantidad' => '1.9999999999']);
        $antes = $this->snapshot();
        MovimientoInventarioCompra::creating(fn () => $this->fail('El stock de todos los inventarios debe comprobarse antes de las SALIDAS.'));
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'stock suficiente');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_en_segunda_salida_revierte_primera_salida_y_stock(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44)]);
        $antes = $this->snapshot();
        $intentos = 0;
        MovimientoInventarioCompra::creating(function () use (&$intentos, $compra) {
            if (++$intentos === 2) {
                $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
                $this->assertTrue($compra->fresh()->estado);
                $this->assertNull($compra->fresh()->fecha_anulacion);
                throw new RuntimeException('Fallo de segunda SALIDA.');
            }
        });
        $this->rechaza(fn () => $this->servicio()->anularCompra($compra->id, 17), RuntimeException::class);
        $this->assertSame(2, $intentos);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_unique_de_salida_revierte_toda_la_anulacion(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(7)]);
        $antes = $this->snapshot();
        $intentos = 0;
        MovimientoInventarioCompra::creating(function ($movimiento) use (&$intentos) {
            if (++$intentos === 2) {
                $movimiento->detalle_compra_id = MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->firstOrFail()->detalle_compra_id;
            }
        });
        $this->rechaza(fn () => $this->servicio()->anularCompra($compra->id, 17), QueryException::class);
        $this->assertSame(2, $intentos);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_salida_preexistente_no_se_duplica_y_no_se_inactiva(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        $salida = (array) DB::table('movimientos_inventario_compra')->first();
        unset($salida['id']);
        $salida['tipo_movimiento'] = 'SALIDA';
        DB::table('movimientos_inventario_compra')->insert($salida);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($compra->id, 17), 'ya tiene una SALIDA');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_inventario_inactivo_permite_compensacion_historica(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => 0]);
        $this->servicio()->anularCompra($compra->id, 17);
        $this->assertFalse(InventarioCompra::findOrFail(7)->estado);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertFalse($compra->fresh()->estado);
    }

    public function test_documento_anulado_puede_reutilizarse_con_un_nuevo_id(): void
    {
        $datos = $this->datosCompra([$this->linea(7)]);
        $original = $this->servicio()->registrarCompra($datos, 17);
        $this->servicio()->anularCompra($original->id, 17);
        (new CompraController)->store(Request::create('/', 'POST', $datos), $this->servicio());
        $nueva = Compra::orderByDesc('id')->firstOrFail();
        $this->assertNotSame($original->id, $nueva->id);
        $this->assertTrue($nueva->estado);
        $this->assertFalse($original->fresh()->estado);
        $this->assertSame($original->numero_autorizacion, $nueva->numero_autorizacion);
        $this->assertSame(2, Compra::count());
        $this->assertSame(2, DetalleCompra::count());
        $this->assertSame(3, MovimientoInventarioCompra::count());
        $this->assertSame('11.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $antes = $this->snapshot();
        $this->rechazaAnulacion(fn () => $this->servicio()->anularCompra($original->id, 17), 'ya fue anulada');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_una_transaccion_y_estado_final_solo_despues_de_todas_las_salidas(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44)]);
        MovimientoInventarioCompra::creating(function () use ($compra) {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertTrue($compra->fresh()->estado);
            $this->assertTrue($compra->fresh()->inventario_aplicado);
        });
        Compra::updating(function ($actual) {
            $this->assertFalse($actual->estado);
            $this->assertSame(2, MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->count());
            $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
            $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
            $this->assertSame(1, DB::connection()->transactionLevel());
        });
        $this->servicio()->anularCompra($compra->id, 17);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_fallo_final_y_usuario_inexistente_revierten_salidas_y_stock(): void
    {
        $compra = $this->crearCompra([$this->linea(7), $this->linea(44)]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio()->anularCompra($compra->id, 999), QueryException::class);
        $this->assertSame($antes, $this->snapshot());
        Compra::updating(fn () => throw new RuntimeException('Fallo al guardar anulación.'));
        $this->rechaza(fn () => $this->servicio()->anularCompra($compra->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_deadlock_reintenta_sin_duplicar_salidas_y_relee_saldos(): void
    {
        $compra = $this->crearCompra([$this->linea(7, '0.1'), $this->linea(7, '0.2')]);
        $stock = new class extends InventarioCompraService {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $inventario = parent::salida($inventarioId, $cantidad);
                if (++$this->llamadas === 1) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }
                return $inventario;
            }
        };
        $this->servicio($stock)->anularCompra($compra->id, 17);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(2, MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->count());
        $this->assertFalse($compra->fresh()->estado);
    }

    public function test_deadlocks_repetidos_se_limitan_a_tres_intentos(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        $antes = $this->snapshot();
        $stock = new class extends InventarioCompraService {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::salida($inventarioId, $cantidad);
                $this->llamadas++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $this->rechaza(fn () => $this->servicio($stock)->anularCompra($compra->id, 17), RuntimeException::class);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_compra_inexistente_no_modifica_datos(): void
    {
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio()->anularCompra(999, 17), ModelNotFoundException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_controller_delega_la_anulacion_con_usuario_del_servidor(): void
    {
        $servicio = Mockery::mock(CompraInventarioService::class);
        $servicio->shouldReceive('anularCompra')->once()->with(42, 17)->andReturn(new Compra);
        $compra = new Compra;
        $compra->id = 42;
        $response = (new CompraController)->cambiarEstado($compra, $servicio);
        $this->assertSame('http://localhost/compras', $response->getTargetUrl());
        $this->assertFalse(method_exists(CompraController::class, 'edit'));
        $this->assertFalse(method_exists(CompraController::class, 'update'));
        foreach (['inventario_aplicado', 'fecha_anulacion', 'anulado_por_id', 'marca_activa'] as $campo) {
            $this->assertFalse($compra->isFillable($campo));
        }
    }

    public function test_rutas_reales_no_permiten_editar_y_mantienen_permiso_de_anulacion(): void
    {
        $router = $this->cargarRutas();
        $rutas = $router->getRoutes();
        $this->assertNull($rutas->getByName('compras.edit'));
        $this->assertNull($rutas->getByName('compras.update'));
        $anular = $rutas->getByName('compras.cambiar-estado');
        $this->assertSame(['PATCH'], $anular->methods());
        $this->assertContains('auth', $anular->middleware());
        $this->assertContains('permiso', $anular->middleware());
        $this->assertSame('eliminar', VerificarPermiso::ACCIONES_POR_METODO['cambiar-estado']);
        $this->rechaza(fn () => $rutas->match(Request::create('/compras/1/edit', 'GET')), NotFoundHttpException::class);
        foreach (['PUT', 'PATCH', 'POST'] as $metodo) {
            $this->rechaza(fn () => $rutas->match(Request::create('/compras/1', $metodo)), MethodNotAllowedHttpException::class);
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/resources/views/compras/edit.blade.php');
    }

    public function test_vistas_renderizadas_ocultan_edicion_reactivacion_y_anular_sin_permiso(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        foreach (['compras.index', 'compras.show'] as $vista) {
            $html = $this->renderizar($vista, $compra, true);
            $this->assertStringContainsString('Anular', $html);
            $this->assertStringNotContainsString('Editar', $html);
            $this->assertStringNotContainsString('Activar', $html);
            $html = $this->renderizar($vista, $compra, false);
            $this->assertStringNotContainsString('Anular', $html);
        }
        $this->servicio()->anularCompra($compra->id, 17);
        foreach (['compras.index', 'compras.show'] as $vista) {
            $html = $this->renderizar($vista, $compra->fresh(), true);
            $this->assertStringContainsString('Anulada', $html);
            $this->assertStringContainsString('Operador de prueba', $html);
            $this->assertStringContainsString('02/10/2026 12:30', $html);
            $this->assertStringNotContainsString('Anular', $html);
            $this->assertStringNotContainsString('Activar', $html);
            $this->assertStringNotContainsString('Editar', $html);
        }
    }

    public function test_vistas_muestran_historica_y_mensajes_de_validacion(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        DB::table('compras')->update(['inventario_aplicado' => 0]);
        foreach (['compras.index', 'compras.show'] as $vista) {
            $html = $this->renderizar($vista, $compra->fresh(), true, 'Esta compra es histórica y requiere conciliación.');
            $this->assertStringContainsString('histórica', strtolower($html));
            $this->assertStringContainsString('Esta compra es histórica y requiere conciliación.', $html);
        }
    }

    public function test_index_y_show_cargan_usuario_de_anulacion_sin_consultas_por_fila(): void
    {
        $compra = $this->crearCompra([$this->linea(7)]);
        $this->servicio()->anularCompra($compra->id, 17);
        $this->renderizar('compras.index', $compra->fresh(), true);

        $controller = new CompraController;
        $compras = $controller->index(Request::create('/compras'))->getData()['compras'];
        $this->assertTrue($compras->first()->relationLoaded('anuladoPor'));
        $detalle = $controller->show($compra->fresh())->getData()['compra'];
        $this->assertTrue($detalle->relationLoaded('anuladoPor'));
        $this->assertSame('Operador de prueba', $detalle->anuladoPor->name);
    }

    private function crearCompra(array $detalles): Compra
    {
        return $this->servicio()->registrarCompra($this->datosCompra($detalles), 17);
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

    private function rechazaAnulacion(callable $operacion, string $mensaje): void
    {
        try {
            $operacion();
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($mensaje, implode(' ', $exception->errors()['compra']));
            $this->assertSame(0, DB::connection()->transactionLevel());
            return;
        }
        $this->fail('La anulación debía rechazarse.');
    }

    private function cargarRutas(): Router
    {
        $container = Container::getInstance();
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Route::clearResolvedInstance('router');
        require dirname(__DIR__, 2) . '/routes/web.php';
        $router->getRoutes()->refreshNameLookups();
        return $router;
    }

    private function renderizar(string $vista, Compra $compra, bool $puedeAnular, ?string $error = null): string
    {
        $container = Container::getInstance();
        $router = $this->cargarRutas();
        $request = Request::create('http://localhost/compras');
        $container->instance('request', $request);
        $container->instance('url', new UrlGenerator($router->getRoutes(), $request));
        $session = new Store('vistas', new ArraySessionHandler(120));
        $session->start();
        $container->instance('session', $session);
        $container->instance('translator', new Translator(new ArrayLoader, 'es'));
        $gate = new Gate($container, fn () => User::findOrFail(17));
        $gate->define('compras.eliminar', fn () => $puedeAnular);
        $gate->define('compras.ver', fn () => true);
        $gate->define('compras.crear', fn () => true);
        $container->instance(GateContract::class, $gate);

        if ($this->directorioVistas === null) {
            $this->directorioVistas = sys_get_temp_dir() . '/FacturasLeon-anulacion-vistas-' . bin2hex(random_bytes(8));
            mkdir($this->directorioVistas . '/cache', 0777, true);
            mkdir($this->directorioVistas . '/layouts');
            file_put_contents($this->directorioVistas . '/layouts/app-bootstrap.blade.php', "@yield('content')");
        }
        $files = new Filesystem;
        $compiler = new BladeCompiler($files, $this->directorioVistas . '/cache');
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
        $factory = new Factory($engines, new FileViewFinder($files, [$this->directorioVistas, dirname(__DIR__, 2) . '/resources/views']), new Dispatcher($container));
        $factory->setContainer($container);
        $factory->addNamespace('pagination', dirname(__DIR__, 2) . '/vendor/laravel/framework/src/Illuminate/Pagination/resources/views');
        $container->instance('view', $factory);
        $container->alias('view', \Illuminate\Contracts\View\Factory::class);
        AbstractPaginator::viewFactoryResolver(fn () => $factory);
        $compra->load(['proveedor', 'user', 'tipoDocumento', 'anuladoPor', 'detalles.inventarioCompra']);
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag($error ? ['compra' => $error] : []));
        $compras = Compra::with(['proveedor', 'tipoDocumento', 'anuladoPor'])->paginate(10);
        return $factory->make($vista, compact('compra', 'compras', 'errors'))->render();
    }
}
