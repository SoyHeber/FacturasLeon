<?php

namespace Tests\Unit;

use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Http\Controllers\ProduccionController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\ConsumoProduccion;
use App\Models\InventarioCompra;
use App\Models\MaterialProducto;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Services\CalculadoraProduccionService;
use App\Services\InventarioCompraService;
use App\Services\ProduccionInventarioService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ProduccionInventarioTestCase;

class ConfirmacionProduccionInventarioTest extends ProduccionInventarioTestCase
{
    public function test_confirma_y_snapshot_salidas_stock_auditoria_coinciden_con_calculo(): void
    {
        $calculo = (new CalculadoraProduccionService)->calcular($this->produccion);
        $confirmada = $this->servicioProduccion()->confirmarProduccion($this->produccion->id, 17);
        $consumo = ConsumoProduccion::firstOrFail();
        $salida = MovimientoInventarioCompra::firstOrFail();

        $this->assertSame('CONFIRMADA', $confirmada->estado_produccion);
        $this->assertTrue($confirmada->inventario_aplicado);
        $this->assertSame('2026-10-02 12:30:00', $confirmada->fecha_confirmacion->format('Y-m-d H:i:s'));
        $this->assertSame(17, $confirmada->confirmado_por_id);
        $this->assertNull($confirmada->fecha_anulacion);
        $this->assertNull($confirmada->anulado_por_id);
        $this->assertSame($calculo[0]['cantidad_por_unidad'], $consumo->cantidad_requerida);
        $this->assertSame($calculo[0]['cantidad_total'], $consumo->cantidad_consumida);
        $this->assertSame(3, $consumo->cantidad_producida);
        $this->assertSame('Material A', $consumo->nombre_material);
        $this->assertSame('g', $consumo->unidad_medida);
        $this->assertSame($consumo->cantidad_consumida, $salida->cantidad);
        $this->assertSame($consumo->id, (int) $salida->consumo_produccion_id);
        $this->assertSame($confirmada->id, (int) $salida->produccion_id);
        $this->assertNull($salida->detalle_compra_id);
        $this->assertSame('SALIDA', $salida->tipo_movimiento);
        $this->assertTrue($salida->estado);
        $this->assertTrue($salida->fecha_movimiento->equalTo($confirmada->fecha_confirmacion));
        $this->assertStringContainsString('producción #'.$confirmada->id, $salida->motivo);
        $this->assertStringContainsString('consumo #'.$consumo->id, $salida->motivo);
        $this->assertSame('9.6296296330', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_varias_materias_y_cantidades_minimas_y_maximas_se_consumen_exactamente(): void
    {
        $this->agregarMaterial('0.0000000001');
        $this->servicioProduccion()->confirmarProduccion($this->produccion->id, 17);
        $this->assertSame(2, ConsumoProduccion::count());
        $this->assertSame(2, MovimientoInventarioCompra::count());
        $this->assertSame('19.9999999997', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame('0.0000000003', ConsumoProduccion::where('inventario_compra_id', 44)->firstOrFail()->cantidad_consumida);

        $otra = Produccion::create(['producto_id' => 3, 'user_id' => 17, 'cantidad' => 1, 'fecha_produccion' => now(), 'estado' => true]);
        DB::table('materiales_producto')->where('inventario_compra_id', 7)->update(['cantidad_requerida' => '9999999999.9999999999']);
        DB::table('materiales_producto')->where('inventario_compra_id', 44)->update(['estado' => false]);
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '9999999999.9999999999']);
        $this->servicioProduccion()->confirmarProduccion($otra->id, 17);
        $this->assertSame('0.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('9999999999.9999999999', $otra->consumos()->firstOrFail()->cantidad_consumida);
    }

    public function test_stock_insuficiente_se_detecta_en_todos_antes_de_crear_consumos_o_salidas(): void
    {
        $this->agregarMaterial('7');
        $antes = $this->snapshot();
        ConsumoProduccion::creating(fn () => $this->fail('No debe crearse ningún consumo.'));
        MovimientoInventarioCompra::creating(fn () => $this->fail('No debe crearse ninguna SALIDA.'));
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_inventario_inactivo_receta_vacia_o_invalida_no_aplican_inventario(): void
    {
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => false]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => true]);
        DB::table('materiales_producto')->update(['estado' => false]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('materiales_producto')->update(['estado' => true, 'cantidad_requerida' => '0']);
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('estadosNoConfirmables')]
    public function test_estado_no_borrador_no_puede_confirmarse(string $estado): void
    {
        $this->produccion->forceFill(['estado_produccion' => $estado])->save();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosNoConfirmables(): array
    {
        return [['LEGADA'], ['CONFIRMADA'], ['ANULADA']];
    }

    public function test_inventario_aplicado_auditoria_previa_o_borrador_inactivo_rechazan_confirmacion(): void
    {
        foreach (['inventario_aplicado' => true, 'estado' => false, 'confirmado_por_id' => 17, 'fecha_anulacion' => '2026-10-02 10:00:00'] as $campo => $valor) {
            $original = $this->produccion->getAttributes();
            $this->produccion->forceFill([$campo => $valor])->save();
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
            $this->produccion->forceFill($original)->save();
        }
    }

    public function test_segunda_confirmacion_no_recalcula_ni_duplica_el_snapshot(): void
    {
        $this->confirmar();
        DB::table('materiales_producto')->update(['cantidad_requerida' => '9', 'estado' => false]);
        DB::table('inventarios_compra')->where('id', 7)->update(['nombre' => 'Nombre nuevo', 'unidad_medida' => 'kg']);
        $antes = $this->snapshot();
        $calculadora = Mockery::mock(CalculadoraProduccionService::class);
        $calculadora->shouldNotReceive('calcular');
        $this->rechaza(fn () => $this->servicioProduccion(null, $calculadora)->confirmarProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('Material A', ConsumoProduccion::firstOrFail()->nombre_material);
        $this->assertSame('0.1234567890', ConsumoProduccion::firstOrFail()->cantidad_requerida);
    }

    public function test_stock_agrupado_se_comprueba_antes_de_escribir_aun_con_lineas_repetidas(): void
    {
        Schema::table('materiales_producto', fn (Blueprint $table) => $table->dropUnique(['producto_id', 'inventario_compra_id']));
        MaterialProducto::create(['producto_id' => 3, 'inventario_compra_id' => 7, 'cantidad_requerida' => '0.1', 'estado' => true]);
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '0.5000000000']);
        $antes = $this->snapshot();
        ConsumoProduccion::creating(fn () => $this->fail('El stock agrupado debe comprobarse antes de crear consumos.'));
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_bloquea_produccion_producto_e_inventarios_en_orden_y_lee_receta_despues_del_producto(): void
    {
        $this->agregarMaterial();
        $grammar = new class extends SQLiteGrammar
        {
            public array $lecturas = [];

            public function compileSelect(Builder $query)
            {
                $this->lecturas[] = ['tabla' => $query->from, 'bloqueo' => $query->lock,
                    'parametros' => $query->getBindings(), 'transaccion' => $query->getConnection()->transactionLevel()];

                return parent::compileSelect($query);
            }
        };
        DB::connection()->setQueryGrammar($grammar);
        $this->confirmar();
        $bloqueos = array_values(array_filter($grammar->lecturas, fn ($lectura) => $lectura['bloqueo']));
        $this->assertSame('producciones', $bloqueos[0]['tabla']);
        $this->assertSame('productos', $bloqueos[1]['tabla']);
        $inventarios = array_values(array_filter($bloqueos, fn ($lectura) => $lectura['tabla'] === 'inventarios_compra'));
        $this->assertSame([[7], [44]], array_column($inventarios, 'parametros'));
        foreach ($bloqueos as $lectura) {
            $this->assertSame(1, $lectura['transaccion']);
        }
        $tablas = array_column($grammar->lecturas, 'tabla');
        $this->assertGreaterThan(array_search('productos', $tablas, true), array_search('materiales_producto', $tablas, true));
    }

    public function test_inventario_aplicado_se_marca_solo_despues_de_consumos_salidas_y_stock(): void
    {
        $this->agregarMaterial();
        $stock = new class extends InventarioCompraService
        {
            public array $estados = [];

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $produccion = Produccion::firstOrFail();
                $this->estados[] = [$produccion->estado_produccion, $produccion->inventario_aplicado, $produccion->fecha_confirmacion];

                return parent::salida($inventarioId, $cantidad);
            }
        };
        Produccion::updating(function ($produccion) {
            $this->assertSame(2, ConsumoProduccion::count());
            $this->assertSame(2, MovimientoInventarioCompra::count());
            $this->assertSame('9.6296296330', InventarioCompra::findOrFail(7)->cantidad);
            $this->assertSame('19.9999999997', InventarioCompra::findOrFail(44)->cantidad);
            $this->assertFalse(Produccion::findOrFail($produccion->id)->inventario_aplicado);
        });
        $confirmada = $this->servicioProduccion($stock)->confirmarProduccion($this->produccion->id, 17);
        $this->assertSame([['BORRADOR', false, null], ['BORRADOR', false, null]], $stock->estados);
        $this->assertTrue($confirmada->fresh()->inventario_aplicado);
    }

    public function test_fallo_intermedio_revierte_todos_los_consumos_salidas_y_stock(): void
    {
        $this->agregarMaterial();
        $antes = $this->snapshot();
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $inventario = parent::salida($inventarioId, $cantidad);
                if (++$this->llamadas === 2) {
                    throw new RuntimeException('Fallo en la segunda salida.');
                }

                return $inventario;
            }
        };
        $this->rechaza(fn () => $this->servicioProduccion($stock)->confirmarProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame(2, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_final_o_usuario_inexistente_revierten_la_confirmacion_completa(): void
    {
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->confirmarProduccion($this->produccion->id, 999), QueryException::class);
        $this->assertSame($antes, $this->snapshot());
        Produccion::updating(fn () => throw new RuntimeException('No se pudo guardar la confirmación.'));
        $this->rechaza(fn () => $this->confirmar(), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_deadlock_reintenta_releyendo_saldos_y_receta_sin_duplicar(): void
    {
        $this->agregarMaterial();
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public array $saldos = [];

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $this->saldos[] = InventarioCompra::findOrFail($inventarioId)->cantidad;
                $inventario = parent::salida($inventarioId, $cantidad);
                if (++$this->llamadas === 2) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }

                return $inventario;
            }
        };
        $calculadora = new class extends CalculadoraProduccionService
        {
            public int $llamadas = 0;

            public function calcular(Produccion $produccion): array
            {
                $this->llamadas++;

                return parent::calcular($produccion);
            }
        };
        $confirmada = $this->servicioProduccion($stock, $calculadora)->confirmarProduccion($this->produccion->id, 17);
        $this->assertSame(4, $stock->llamadas);
        $this->assertSame(2, $calculadora->llamadas);
        $this->assertSame(['10.0000000000', '20.0000000000', '10.0000000000', '20.0000000000'], $stock->saldos);
        $this->assertSame(2, ConsumoProduccion::count());
        $this->assertSame(2, MovimientoInventarioCompra::count());
        $this->assertSame('9.6296296330', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('19.9999999997', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame('CONFIRMADA', $confirmada->estado_produccion);
    }

    public function test_deadlocks_repetidos_se_limitan_a_tres_intentos_y_revierten_todo(): void
    {
        $antes = $this->snapshot();
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::salida($inventarioId, $cantidad);
                $this->llamadas++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $this->rechaza(fn () => $this->servicioProduccion($stock)->confirmarProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_consumos_previos_o_movimientos_incompatibles_activos_e_inactivos_impiden_confirmar(): void
    {
        $this->confirmar();
        $this->produccion->refresh();
        $this->produccion->forceFill(['estado_produccion' => 'BORRADOR', 'inventario_aplicado' => false,
            'fecha_confirmacion' => null, 'confirmado_por_id' => null])->save();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('movimientos_inventario_compra')->delete();
        DB::table('consumos_produccion')->delete();
        foreach ([true, false] as $estado) {
            $movimiento = MovimientoInventarioCompra::create(['produccion_id' => $this->produccion->id,
                'inventario_compra_id' => 7, 'tipo_movimiento' => 'SALIDA', 'cantidad' => '0.1',
                'fecha_movimiento' => now(), 'estado' => $estado]);
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
            DB::table('movimientos_inventario_compra')->where('id', $movimiento->id)->delete();
        }
    }

    public function test_movimiento_automatico_no_admite_edit_update_ni_cambiar_estado_aunque_modelo_se_manipule(): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        $controller = new MovimientoInventarioCompraController(new InventarioCompraService);
        foreach (['edit', 'update', 'cambiarEstado'] as $metodo) {
            $movimiento = MovimientoInventarioCompra::firstOrFail();
            $movimiento->consumo_produccion_id = null;
            $operacion = fn () => $metodo === 'update'
                ? $controller->update(Request::create('/', 'PUT', []), $movimiento)
                : $controller->$metodo($movimiento);
            $this->rechaza($operacion, ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
        }
    }

    public function test_peticion_manual_nunca_asigna_consumo_produccion_id_en_store_o_update(): void
    {
        $this->confirmar();
        $stockAntes = InventarioCompra::findOrFail(7)->cantidad;
        $consumoId = ConsumoProduccion::firstOrFail()->id;
        $controller = new MovimientoInventarioCompraController(new InventarioCompraService);
        $datos = ['inventario_compra_id' => 7, 'tipo_movimiento' => 'SALIDA', 'cantidad' => '0.01',
            'fecha_movimiento' => '2026-10-02 12:30:00', 'consumo_produccion_id' => $consumoId];
        $controller->store(Request::create('/', 'POST', $datos));
        $manual = MovimientoInventarioCompra::latest('id')->firstOrFail();
        $this->assertNull($manual->consumo_produccion_id);
        $controller->update(Request::create('/', 'PUT', $datos), $manual);
        $this->assertNull($manual->fresh()->consumo_produccion_id);
        $this->assertSame(1, ConsumoProduccion::count());
        $this->assertSame($stockAntes, InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_controller_delega_id_y_usuario_autenticado_y_ruta_exige_modificar(): void
    {
        $servicio = Mockery::mock(ProduccionInventarioService::class);
        $servicio->shouldReceive('confirmarProduccion')->once()->with($this->produccion->id, 17)->andReturn($this->produccion);
        $respuesta = (new ProduccionController)->confirmar($this->produccion, $servicio);
        $this->assertTrue($respuesta->isRedirect());
        $ruta = Route::getRoutes()->getByName('producciones.confirmar');
        $this->assertSame(['PATCH'], $ruta->methods());
        $this->assertContains('auth', $ruta->gatherMiddleware());
        $this->assertContains('permiso', $ruta->gatherMiddleware());
        $this->assertSame('modificar', VerificarPermiso::ACCIONES_POR_METODO['confirmar']);
        $usuario = new class
        {
            public array $consultas = [];

            public function tienePermiso(string $opcion, string $accion): bool
            {
                $this->consultas[] = [$opcion, $accion];

                return $opcion === 'producciones' && $accion === 'modificar';
            }
        };
        $request = Request::create('/producciones/'.$this->produccion->id.'/confirmar', 'PATCH');
        $request->setRouteResolver(fn () => $ruta);
        $request->setUserResolver(fn () => $usuario);
        $resultado = (new VerificarPermiso)->handle($request, fn () => new Response('permitido'));
        $this->assertSame(200, $resultado->getStatusCode());
        $this->assertSame([['producciones', 'modificar']], $usuario->consultas);
    }
}
