<?php

namespace Tests\Unit;

use App\Http\Controllers\MovimientoInventarioCompraController;
use App\Http\Controllers\ProduccionController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\ConsumoProduccion;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Services\CalculadoraProduccionService;
use App\Services\InventarioCompraService;
use App\Services\ProduccionInventarioService;
use Carbon\Carbon;
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

class AnulacionProduccionInventarioTest extends ProduccionInventarioTestCase
{
    public function test_cancelar_borrador_solo_registra_estado_fecha_y_usuario(): void
    {
        $antes = $this->snapshot();
        $stock = Mockery::mock(InventarioCompraService::class);
        $stock->shouldNotReceive('conInventariosBloqueados');
        $anulada = $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17);

        $this->assertSame('ANULADA', $anulada->estado_produccion);
        $this->assertFalse($anulada->inventario_aplicado);
        $this->assertSame('2026-10-02 12:30:00', $anulada->fecha_anulacion->format('Y-m-d H:i:s'));
        $this->assertSame(17, $anulada->anulado_por_id);
        $this->assertNull($anulada->fecha_confirmacion);
        $this->assertNull($anulada->confirmado_por_id);
        $this->assertSame(array_slice($antes, 1), array_slice($this->snapshot(), 1));
    }

    public function test_cancelacion_rechaza_borrador_con_inventario_aplicado_auditoria_o_consumos(): void
    {
        foreach (['inventario_aplicado' => true, 'confirmado_por_id' => 17, 'fecha_confirmacion' => now(),
            'anulado_por_id' => 17, 'fecha_anulacion' => now()] as $campo => $valor) {
            $original = $this->produccion->getAttributes();
            $this->produccion->forceFill([$campo => $valor])->save();
            $this->rechazarSinCambios();
            $this->produccion->forceFill($original)->save();
        }
        $this->confirmar();
        $this->produccion->refresh();
        DB::table('movimientos_inventario_compra')->delete();
        $this->produccion->forceFill(['estado_produccion' => 'BORRADOR', 'inventario_aplicado' => false,
            'fecha_confirmacion' => null, 'confirmado_por_id' => null])->save();
        $this->rechazarSinCambios();
    }

    public function test_cancelacion_rechaza_movimiento_automatico_aunque_este_inactivo_y_no_tenga_consumos_propios(): void
    {
        $this->confirmar();
        $otra = Produccion::create(['producto_id' => 3, 'user_id' => 17, 'cantidad' => 1, 'fecha_produccion' => now()]);
        MovimientoInventarioCompra::firstOrFail()->update(['produccion_id' => $otra->id, 'estado' => false]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($otra->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_ciclo_completo_devuelve_varias_materias_exactas_y_conserva_snapshot_salidas_y_confirmacion(): void
    {
        $this->agregarMaterial();
        $confirmada = $this->confirmar();
        $consumos = $this->filas('consumos_produccion');
        $salidas = $this->filas('movimientos_inventario_compra');
        DB::table('materiales_producto')->update(['cantidad_requerida' => '999', 'estado' => false]);
        DB::table('inventarios_compra')->where('id', 7)->update(['nombre' => 'Renombrado', 'unidad_medida' => 'kg']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 09:15:00'));
        $calculadora = Mockery::mock(CalculadoraProduccionService::class);
        $calculadora->shouldNotReceive('calcular');
        $anulada = $this->servicioProduccion(null, $calculadora)->anularProduccion($this->produccion->id, 17);

        $this->assertSame('ANULADA', $anulada->estado_produccion);
        $this->assertTrue($anulada->inventario_aplicado);
        $this->assertSame($confirmada->fecha_confirmacion->toDateTimeString(), $anulada->fecha_confirmacion->toDateTimeString());
        $this->assertSame($confirmada->confirmado_por_id, $anulada->confirmado_por_id);
        $this->assertSame('2026-10-03 09:15:00', $anulada->fecha_anulacion->toDateTimeString());
        $this->assertSame(17, $anulada->anulado_por_id);
        $this->assertSame($consumos, $this->filas('consumos_produccion'));
        $this->assertSame($salidas, array_slice($this->filas('movimientos_inventario_compra'), 0, 2));
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame(4, MovimientoInventarioCompra::count());
        foreach (ConsumoProduccion::all() as $consumo) {
            $salida = MovimientoInventarioCompra::where('consumo_produccion_id', $consumo->id)->where('tipo_movimiento', 'SALIDA')->firstOrFail();
            $entrada = MovimientoInventarioCompra::where('consumo_produccion_id', $consumo->id)->where('tipo_movimiento', 'ENTRADA')->firstOrFail();
            $this->assertTrue($salida->estado);
            $this->assertTrue($entrada->estado);
            $this->assertSame($salida->cantidad, $entrada->cantidad);
            $this->assertSame($consumo->cantidad_consumida, $entrada->cantidad);
            $this->assertSame((int) $salida->inventario_compra_id, (int) $entrada->inventario_compra_id);
            $this->assertSame($anulada->id, (int) $entrada->produccion_id);
            $this->assertNull($entrada->detalle_compra_id);
            $this->assertTrue($entrada->fecha_movimiento->equalTo($anulada->fecha_anulacion));
            $this->assertStringContainsString('consumo #'.$consumo->id, $entrada->motivo);
        }
    }

    public function test_devolucion_permite_inventario_inactivo(): void
    {
        $this->confirmar();
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => false]);
        $this->anular();
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertFalse(InventarioCompra::findOrFail(7)->estado);
    }

    public function test_agrupa_consumos_del_mismo_inventario_y_bloquea_ids_distintos_en_orden(): void
    {
        $this->agregarMaterial();
        $this->confirmar();
        $this->duplicarConsumo('0.1000000000');
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '9.5296296330']);
        $grammar = new class extends SQLiteGrammar
        {
            public array $bloqueos = [];

            public function compileSelect(Builder $query)
            {
                if ($query->lock) {
                    $this->bloqueos[] = [$query->from, $query->getBindings(), $query->getConnection()->transactionLevel()];
                }

                return parent::compileSelect($query);
            }
        };
        DB::connection()->setQueryGrammar($grammar);
        $this->anular();
        $this->assertSame('producciones', $grammar->bloqueos[0][0]);
        $inventarios = array_values(array_filter($grammar->bloqueos, fn ($fila) => $fila[0] === 'inventarios_compra'));
        $this->assertSame([['inventarios_compra', [7], 1], ['inventarios_compra', [44], 1]], $inventarios);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(3, MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->count());
    }

    #[DataProvider('entradasPrevias')]
    public function test_entrada_preexistente_rechaza_antes_de_bloquear_stock(bool $activa, bool $vinculoAlterado): void
    {
        $this->confirmar();
        $salida = MovimientoInventarioCompra::firstOrFail();
        $entrada = $salida->replicate();
        $entrada->tipo_movimiento = 'ENTRADA';
        $entrada->estado = $activa;
        $entrada->produccion_id = $vinculoAlterado ? null : $salida->produccion_id;
        $entrada->save();
        $antes = $this->snapshot();
        $stock = Mockery::mock(InventarioCompraService::class);
        $stock->shouldNotReceive('conInventariosBloqueados');
        try {
            $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17);
            $this->fail('Debe rechazar la doble devolución.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Ya existe una ENTRADA', $e->errors()['produccion'][0]);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public static function entradasPrevias(): array
    {
        return [[true, false], [false, false], [true, true], [false, true]];
    }

    #[DataProvider('salidasInvalidas')]
    public function test_salida_faltante_o_alterada_rechaza_sin_modificar_stock(string $campo, mixed $valor): void
    {
        $this->confirmar();
        if ($campo === 'eliminar') {
            DB::table('movimientos_inventario_compra')->delete();
        } else {
            Schema::disableForeignKeyConstraints();
            DB::table('movimientos_inventario_compra')->update([$campo => $valor]);
            Schema::enableForeignKeyConstraints();
        }
        $this->rechazarSinCambios();
    }

    public static function salidasInvalidas(): array
    {
        return [['eliminar', null], ['estado', false], ['produccion_id', null], ['inventario_compra_id', 44],
            ['consumo_produccion_id', null], ['detalle_compra_id', 999], ['tipo_movimiento', 'AJUSTE'],
            ['cantidad', '0'], ['cantidad', '-0.3703703670'], ['cantidad', '0.3703703671'],
            ['cantidad', '0.37037036701'], ['cantidad', '10000000000']];
    }

    public function test_salidas_duplicadas_no_se_devuelven(): void
    {
        $this->confirmar();
        Schema::table('movimientos_inventario_compra', fn (Blueprint $t) => $t->dropUnique('movimientos_consumo_tipo_unique'));
        MovimientoInventarioCompra::firstOrFail()->replicate()->save();
        $this->rechazarSinCambios();
    }

    public function test_snapshot_alterado_no_se_recalcula_para_compensar(): void
    {
        $this->confirmar();
        DB::table('consumos_produccion')->update(['cantidad_consumida' => '0.37037036701']);
        $this->rechazarSinCambios();
        DB::table('consumos_produccion')->update(['cantidad_consumida' => '1.0000000000']);
        $this->rechazarSinCambios();
    }

    public function test_saldo_final_y_sumatoria_fuera_de_decimal_rechazan_antes_de_crear_entradas(): void
    {
        $this->confirmar();
        MovimientoInventarioCompra::creating(fn () => $this->fail('Debe validar todas las sumas antes de registrar ENTRADAS.'));
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '9999999999.9999999999']);
        $this->rechazarSinCambios();
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '0.0000000000']);
        DB::table('consumos_produccion')->update(['cantidad_consumida' => '9999999999.9999999999']);
        DB::table('movimientos_inventario_compra')->update(['cantidad' => '9999999999.9999999999']);
        // La restricción se relaja únicamente en esta base efímera para probar la agrupación defensiva.
        $this->duplicarConsumo('0.0000000001', false);
        $this->rechazarSinCambios();
    }

    public function test_devuelve_el_limite_decimal_exacto(): void
    {
        $this->produccion->update(['cantidad' => 1]);
        DB::table('materiales_producto')->update(['cantidad_requerida' => '9999999999.9999999999']);
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '9999999999.9999999999']);
        $this->confirmar();
        $this->assertSame('0.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->anular();
        $this->assertSame('9999999999.9999999999', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('9999999999.9999999999', MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->firstOrFail()->cantidad);
    }

    public function test_anulada_se_marca_solo_al_completar_todas_las_entradas_y_stock(): void
    {
        $this->agregarMaterial();
        $this->confirmar();
        $stock = new class extends InventarioCompraService
        {
            public array $estados = [];

            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $p = Produccion::firstOrFail();
                $this->estados[] = [$p->estado_produccion, $p->inventario_aplicado, $p->fecha_anulacion];

                return parent::entrada($inventarioId, $cantidad);
            }
        };
        Produccion::updating(function () {
            $this->assertSame(2, MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->count());
            $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
            $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
            $this->assertSame('CONFIRMADA', Produccion::firstOrFail()->estado_produccion);
        });
        $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17);
        $this->assertSame([['CONFIRMADA', true, null], ['CONFIRMADA', true, null]], $stock->estados);
    }

    public function test_fallo_intermedio_revierte_entradas_stock_y_auditoria(): void
    {
        $this->agregarMaterial();
        $this->confirmar();
        $antes = $this->snapshot();
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $inventario = parent::entrada($inventarioId, $cantidad);
                if (++$this->llamadas === 2) {
                    throw new RuntimeException('Fallo intermedio de devolución.');
                }

                return $inventario;
            }
        };
        $this->rechaza(fn () => $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame(2, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_final_o_usuario_inexistente_revierte_toda_devolucion(): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 999), QueryException::class);
        $this->assertSame($antes, $this->snapshot());
        Produccion::updating(fn () => throw new RuntimeException('Fallo al guardar la anulación.'));
        $this->rechaza(fn () => $this->anular(), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_deadlock_reintenta_sin_duplicar_devoluciones(): void
    {
        $this->agregarMaterial();
        $this->confirmar();
        $originales = $this->filas('movimientos_inventario_compra');
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public array $saldos = [];

            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $this->saldos[] = InventarioCompra::findOrFail($inventarioId)->cantidad;
                $inventario = parent::entrada($inventarioId, $cantidad);
                if (++$this->llamadas === 2) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }

                return $inventario;
            }
        };
        $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17);
        $this->assertSame(4, $stock->llamadas);
        $this->assertSame(['9.6296296330', '19.9999999997', '9.6296296330', '19.9999999997'], $stock->saldos);
        $this->assertSame(2, MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->count());
        $this->assertSame($originales, array_slice($this->filas('movimientos_inventario_compra'), 0, 2));
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('20.0000000000', InventarioCompra::findOrFail(44)->cantidad);
    }

    public function test_deadlock_agota_tres_intentos_y_revierte(): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        $stock = new class extends InventarioCompraService
        {
            public int $llamadas = 0;

            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::entrada($inventarioId, $cantidad);
                $this->llamadas++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $this->rechaza(fn () => $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_legada_segunda_cancelacion_y_segunda_anulacion_rechazadas_sin_reactivacion(): void
    {
        $this->produccion->forceFill(['estado_produccion' => 'LEGADA'])->save();
        $this->rechazarSinCambios();
        $this->produccion->forceFill(['estado_produccion' => 'BORRADOR'])->save();
        $this->anular();
        $this->rechazarSinCambios();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->produccion = Produccion::create(['producto_id' => 3, 'user_id' => 17, 'cantidad' => 1, 'fecha_produccion' => now()]);
        $this->confirmar();
        $this->anular();
        $this->rechazarSinCambios();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $antes = $this->snapshot();
        $this->produccion->estado_produccion = 'BORRADOR';
        $controller = new ProduccionController;
        $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', []), $this->produccion), ValidationException::class);
        $this->rechaza(fn () => $controller->cambiarEstado($this->produccion), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_confirmada_inconsistente_no_permite_devolucion(): void
    {
        $this->confirmar();
        $this->produccion->refresh();
        foreach (['inventario_aplicado' => false, 'fecha_confirmacion' => null, 'confirmado_por_id' => null] as $campo => $valor) {
            $original = $this->produccion->fresh()->getAttributes();
            $this->produccion->forceFill([$campo => $valor])->save();
            $this->rechazarSinCambios();
            $this->produccion->forceFill($original)->save();
        }
        DB::table('movimientos_inventario_compra')->delete();
        DB::table('consumos_produccion')->delete();
        $this->rechazarSinCambios();
    }

    public function test_salidas_y_entradas_automaticas_no_son_editables_ni_inactivables(): void
    {
        $this->confirmar();
        $this->anular();
        $antes = $this->snapshot();
        $controller = new MovimientoInventarioCompraController(new InventarioCompraService);
        foreach (MovimientoInventarioCompra::all() as $movimiento) {
            foreach (['edit', 'update', 'cambiarEstado'] as $metodo) {
                $movimiento->consumo_produccion_id = null;
                $this->rechaza(fn () => $metodo === 'update'
                    ? $controller->update(Request::create('/', 'PUT', []), $movimiento)
                    : $controller->$metodo($movimiento), ValidationException::class);
                $this->assertSame($antes, $this->snapshot());
            }
        }
    }

    public function test_controller_delega_y_ruta_patch_usa_eliminar_sin_ruta_booleana(): void
    {
        $servicio = Mockery::mock(ProduccionInventarioService::class);
        $servicio->shouldReceive('anularProduccion')->once()->with($this->produccion->id, 17)->andReturn($this->produccion);
        $respuesta = (new ProduccionController)->anular($this->produccion, $servicio);
        $this->assertTrue($respuesta->isRedirect());
        $ruta = Route::getRoutes()->getByName('producciones.anular');
        $this->assertSame(['PATCH'], $ruta->methods());
        $this->assertContains('auth', $ruta->gatherMiddleware());
        $this->assertContains('permiso', $ruta->gatherMiddleware());
        $this->assertSame('eliminar', VerificarPermiso::ACCIONES_POR_METODO['anular']);
        $this->assertNull(Route::getRoutes()->getByName('producciones.cambiar-estado'));
        $usuario = new class
        {
            public array $consultas = [];

            public function tienePermiso(string $opcion, string $accion): bool
            {
                $this->consultas[] = [$opcion, $accion];

                return $opcion === 'producciones' && $accion === 'eliminar';
            }
        };
        $request = Request::create('/producciones/'.$this->produccion->id.'/anular', 'PATCH');
        $request->setRouteResolver(fn () => $ruta);
        $request->setUserResolver(fn () => $usuario);
        $resultado = (new VerificarPermiso)->handle($request, fn () => new Response('permitido'));
        $this->assertSame(200, $resultado->getStatusCode());
        $this->assertSame([['producciones', 'eliminar']], $usuario->consultas);
    }

    private function anular(): Produccion
    {
        return $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
    }

    private function rechazarSinCambios(): void
    {
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->anular(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    private function filas(string $tabla): array
    {
        return DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function duplicarConsumo(string $cantidad, bool $conEventos = true): void
    {
        Schema::table('consumos_produccion', fn (Blueprint $t) => $t->dropUnique('consumos_produccion_inventario_unique'));
        $consumo = ConsumoProduccion::firstOrFail()->replicate();
        $consumo->cantidad_consumida = $cantidad;
        $consumo->save();
        $salida = MovimientoInventarioCompra::where('tipo_movimiento', 'SALIDA')->firstOrFail()->replicate();
        $salida->consumo_produccion_id = $consumo->id;
        $salida->cantidad = $cantidad;
        $conEventos ? $salida->save() : MovimientoInventarioCompra::withoutEvents(fn () => $salida->save());
    }
}
