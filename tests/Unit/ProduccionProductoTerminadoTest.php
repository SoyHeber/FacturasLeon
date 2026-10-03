<?php

namespace Tests\Unit;

use App\Http\Controllers\MovimientoInventarioController;
use App\Models\ConsumoProduccion;
use App\Models\Inventario;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Services\InventarioCompraService;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ProduccionInventarioTestCase;

class ProduccionProductoTerminadoTest extends ProduccionInventarioTestCase
{
    public function test_confirmar_crea_inventario_en_cero_y_una_entrada_y_actualiza_todo(): void
    {
        Inventario::created(function ($inventario) {
            $this->assertSame('0', $inventario->cantidad);
            $this->assertTrue($inventario->estado);
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertFalse($this->produccion->fresh()->producto_terminado_aplicado);
        });
        MovimientoInventario::created(function ($movimiento) {
            $this->assertFalse($this->produccion->fresh()->producto_terminado_aplicado);
            $this->assertSame('BORRADOR', $this->produccion->fresh()->estado_produccion);
        });
        $confirmada = $this->confirmar();
        $inventario = Inventario::sole();
        $entrada = MovimientoInventario::sole();
        $this->assertSame(3, $inventario->producto_id);
        $this->assertSame('3', $inventario->cantidad);
        $this->assertSame(0, $inventario->stock_minimo);
        $this->assertNull($inventario->stock_maximo);
        $this->assertNull($inventario->ubicacion);
        $this->assertSame('ENTRADA', $entrada->tipo_movimiento);
        $this->assertSame('3', $entrada->cantidad);
        $this->assertSame($confirmada->id, $entrada->produccion_id);
        $this->assertSame($inventario->id, $entrada->inventario_id);
        $this->assertTrue($entrada->estado);
        $this->assertTrue($entrada->fecha_movimiento->equalTo($confirmada->fecha_confirmacion));
        $this->assertStringContainsString('producción #'.$confirmada->id, $entrada->motivo);
        $this->assertTrue($confirmada->producto_terminado_aplicado);
        $this->assertTrue($confirmada->fresh()->producto_terminado_aplicado);
        $this->assertSame('9.6296296330', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(1, ConsumoProduccion::count());
        $this->assertSame(1, MovimientoInventarioCompra::count());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_reutiliza_inventario_y_dos_producciones_del_mismo_producto_no_lo_duplican(): void
    {
        $inventario = $this->crearInventario('20');
        $this->confirmar();
        $otra = Produccion::create(['producto_id' => 3, 'user_id' => 17, 'cantidad' => 10, 'fecha_produccion' => now(), 'estado' => true]);
        $this->servicioProduccion()->confirmarProduccion($otra->id, 17);
        $this->assertSame(1, Inventario::count());
        $this->assertSame('33', $inventario->fresh()->cantidad);
        $this->assertSame(2, MovimientoInventario::where('tipo_movimiento', 'ENTRADA')->count());
        $this->assertSame([$inventario->id], MovimientoInventario::pluck('inventario_id')->unique()->values()->all());
    }

    public function test_inventario_inactivo_rechaza_confirmacion_sin_reactivarlo(): void
    {
        $this->crearInventario('20', false);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_segunda_confirmacion_no_duplica_entrada_ni_consumos(): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_borrador_con_indicador_o_movimiento_previo_no_se_confirma(): void
    {
        $this->produccion->forceFill(['producto_terminado_aplicado' => true])->save();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->produccion->forceFill(['producto_terminado_aplicado' => false])->save();
        $inventario = $this->crearInventario();
        foreach (['ENTRADA', 'SALIDA'] as $tipo) {
            MovimientoInventario::create($this->datosMovimiento($inventario->id, $tipo));
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
        }
    }

    public function test_cantidad_por_encima_del_int_firmado_se_registra_como_bigint_exacta(): void
    {
        $this->produccion->update(['cantidad' => '4294967295']);
        DB::table('materiales_producto')->update(['cantidad_requerida' => '0.0000000001']);
        $this->confirmar();
        $this->assertSame('4294967295', Inventario::sole()->cantidad);
        $this->assertSame('4294967295', MovimientoInventario::sole()->cantidad);
        $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertSame('0', Inventario::sole()->cantidad);
        $this->assertSame('4294967295', MovimientoInventario::where('tipo_movimiento', 'SALIDA')->sole()->cantidad);
    }

    public function test_cantidad_invalida_o_fuera_de_bigint_se_rechaza_sin_escrituras(): void
    {
        foreach (['0', '-1', '1.5', '9223372036854775808'] as $cantidad) {
            DB::table('producciones')->where('id', $this->produccion->id)->update(['cantidad' => $cantidad]);
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
        }
    }

    public function test_overflow_se_rechaza_antes_de_consumir_materias_primas(): void
    {
        $this->crearInventario('18446744073709551614');
        ConsumoProduccion::creating(fn () => $this->fail('No debe consumirse materia prima.'));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmar(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_saldo_bigint_unsigned_llega_exactamente_al_limite_y_se_resta(): void
    {
        $inventario = $this->crearInventario('18446744073709551612');
        $this->confirmar();
        $this->assertSame(InventarioService::MAXIMO_SALDO, $inventario->fresh()->cantidad);
        $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertSame('18446744073709551612', $inventario->fresh()->cantidad);
    }

    public function test_fallo_del_producto_terminado_revierte_tambien_materia_prima_y_creacion(): void
    {
        $stock = new class extends InventarioService
        {
            public function entrada(int $inventarioId, mixed $cantidad): Inventario
            {
                $inventario = parent::entrada($inventarioId, $cantidad);
                if (ConsumoProduccion::count() !== 1 || MovimientoInventarioCompra::count() !== 1 || MovimientoInventario::count() !== 1) {
                    throw new \LogicException('La prueba debe fallar después de los movimientos.');
                }
                throw new RuntimeException('Fallo después de incrementar el producto terminado.');
            }
        };
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion(terminado: $stock)->confirmarProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_de_materia_prima_no_deja_inventario_ni_movimientos_terminados(): void
    {
        $stock = new class extends InventarioCompraService
        {
            public function salida(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::salida($inventarioId, $cantidad);
                throw new RuntimeException('Fallo después de descontar materia prima.');
            }
        };
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion($stock)->confirmarProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(0, Inventario::count());
        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_fallo_al_crear_entrada_o_guardar_confirmacion_revierte_todo_y_deja_indicador_false(): void
    {
        $antes = $this->snapshot();
        MovimientoInventario::creating(fn () => throw new RuntimeException('No se pudo guardar la ENTRADA.'));
        $this->rechaza(fn () => $this->confirmar(), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
        MovimientoInventario::flushEventListeners();
        Produccion::updating(function ($produccion) {
            $this->assertTrue($produccion->producto_terminado_aplicado);
            $this->assertFalse($produccion->fresh()->producto_terminado_aplicado);
            $this->assertSame('3', Inventario::sole()->cantidad);
            throw new RuntimeException('No se pudo finalizar la confirmación.');
        });
        $this->rechaza(fn () => $this->confirmar(), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_anular_resta_cantidad_original_y_devuelve_materias_primas(): void
    {
        $inventario = $this->crearInventario('20');
        $this->confirmar();
        $original = (array) DB::table('movimientos_inventario')->sole();
        $anulada = $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $salida = MovimientoInventario::where('tipo_movimiento', 'SALIDA')->sole();
        $this->assertSame('20', $inventario->fresh()->cantidad);
        $this->assertSame('3', $salida->cantidad);
        $this->assertSame($anulada->id, $salida->produccion_id);
        $this->assertSame($inventario->id, $salida->inventario_id);
        $this->assertTrue($salida->estado);
        $this->assertTrue($salida->fecha_movimiento->equalTo($anulada->fecha_anulacion));
        $this->assertStringContainsString('Anulación de producción', $salida->motivo);
        $this->assertSame($original, (array) DB::table('movimientos_inventario')->where('id', $original['id'])->first());
        $this->assertSame('ANULADA', $anulada->estado_produccion);
        $this->assertTrue($anulada->producto_terminado_aplicado);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(1, MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->count());
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_stock_insuficiente_impide_toda_la_anulacion_y_devolucion(): void
    {
        $this->produccion->update(['cantidad' => 10]);
        $this->confirmar();
        $inventario = Inventario::sole();
        (new MovimientoInventarioController)->store(Request::create('/movimientos-inventario', 'POST', [
            'inventario_id' => $inventario->id, 'tipo_movimiento' => 'SALIDA', 'cantidad' => 7,
            'fecha_movimiento' => now()->format('Y-m-d H:i:s'), 'estado' => 1,
        ]));
        MovimientoInventarioCompra::creating(fn () => $this->fail('No deben devolverse materias primas.'));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('3', $inventario->fresh()->cantidad);
        $this->assertSame('CONFIRMADA', $this->produccion->fresh()->estado_produccion);
        $this->assertSame(0, MovimientoInventarioCompra::where('tipo_movimiento', 'ENTRADA')->count());
    }

    public function test_salida_compensatoria_preexistente_rechaza_antes_de_tocar_stock(): void
    {
        $this->confirmar();
        MovimientoInventario::create($this->datosMovimiento(Inventario::sole()->id, 'SALIDA'));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('alteracionesEntrada')]
    public function test_entrada_original_alterada_rechaza_sin_devolver_materia_prima(array $alteracion): void
    {
        $this->confirmar();
        DB::table('movimientos_inventario')->update($alteracion);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function alteracionesEntrada(): array
    {
        return [
            'inactiva' => [['estado' => false]], 'cantidad distinta' => [['cantidad' => 4]],
            'cantidad cero' => [['cantidad' => 0]], 'cantidad negativa' => [['cantidad' => -1]],
            'cantidad fraccionaria' => [['cantidad' => '1.5']], 'desvinculada' => [['produccion_id' => null]],
            'ajuste incompatible' => [['tipo_movimiento' => 'AJUSTE']],
        ];
    }

    public function test_entrada_faltante_o_duplicada_rechaza_anulacion(): void
    {
        $this->confirmar();
        $original = (array) DB::table('movimientos_inventario')->sole();
        DB::table('movimientos_inventario')->delete();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('movimientos_inventario')->insert($original);
        Schema::table('movimientos_inventario', fn ($table) => $table->dropUnique('movimientos_inventario_produccion_tipo_unique'));
        unset($original['id']);
        DB::table('movimientos_inventario')->insert($original);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_entrada_vinculada_a_otro_inventario_o_produccion_rechaza_anulacion(): void
    {
        $this->confirmar();
        DB::table('productos')->insert(['id' => 5, 'nombre' => 'Otro producto']);
        $otroInventario = Inventario::create(['producto_id' => 5, 'estado' => true]);
        DB::table('movimientos_inventario')->update(['inventario_id' => $otroInventario->id]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $otraProduccion = Produccion::create(['producto_id' => 5, 'user_id' => 17, 'cantidad' => 3, 'fecha_produccion' => now(), 'estado' => true]);
        DB::table('movimientos_inventario')->update(['produccion_id' => $otraProduccion->id]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_inventario_terminado_inactivo_acepta_reversion_sin_reactivarse(): void
    {
        $this->confirmar();
        $inventario = Inventario::sole();
        $inventario->update(['estado' => false]);
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => false]);
        $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertSame('0', $inventario->fresh()->cantidad);
        $this->assertFalse($inventario->fresh()->estado);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertFalse(InventarioCompra::findOrFail(7)->estado);
    }

    public function test_confirmada_historica_se_anula_sin_exigir_ni_crear_producto_terminado(): void
    {
        $this->confirmar();
        DB::table('movimientos_inventario')->delete();
        DB::table('inventarios')->delete();
        DB::table('producciones')->where('id', $this->produccion->id)->update(['producto_terminado_aplicado' => false]);
        $anulada = $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertSame('ANULADA', $anulada->estado_produccion);
        $this->assertFalse($anulada->producto_terminado_aplicado);
        $this->assertSame(0, Inventario::count());
        $this->assertSame(0, MovimientoInventario::count());
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_historica_no_toca_saldo_terminado_existente(): void
    {
        $this->confirmar();
        DB::table('producciones')->where('id', $this->produccion->id)->update(['producto_terminado_aplicado' => false]);
        DB::table('inventarios')->update(['cantidad' => '0', 'estado' => false]);
        $originales = DB::table('movimientos_inventario')->get()->map(fn ($fila) => (array) $fila)->all();
        $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertSame('0', Inventario::sole()->cantidad);
        $this->assertSame($originales, DB::table('movimientos_inventario')->get()->map(fn ($fila) => (array) $fila)->all());
    }

    public function test_borrador_cancelado_y_legada_no_generan_movimientos_terminados(): void
    {
        $anulada = $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $this->assertFalse($anulada->producto_terminado_aplicado);
        $this->assertSame(0, MovimientoInventario::count());
        $this->assertSame(0, Inventario::count());
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->produccion->forceFill(['estado_produccion' => Produccion::LEGADA])->save();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_despues_de_salida_terminada_revierte_saldo_y_movimiento(): void
    {
        $this->confirmar();
        $stock = new class extends InventarioService
        {
            public function salida(int $inventarioId, mixed $cantidad): Inventario
            {
                parent::salida($inventarioId, $cantidad);
                throw new RuntimeException('Fallo tras descontar producto terminado.');
            }
        };
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion(terminado: $stock)->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_de_devolucion_mp_revierte_la_salida_terminada_ya_aplicada(): void
    {
        $this->confirmar();
        $stock = new class extends InventarioCompraService
        {
            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::entrada($inventarioId, $cantidad);
                if (Inventario::sole()->cantidad !== '0' || MovimientoInventario::where('tipo_movimiento', 'SALIDA')->count() !== 1) {
                    throw new \LogicException('La salida terminada debe estar aplicada antes de devolver materia prima.');
                }
                throw new RuntimeException('Fallo tras devolver materia prima.');
            }
        };
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion($stock)->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_al_guardar_anulacion_revierte_ambos_inventarios(): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        Produccion::updating(fn () => throw new RuntimeException('No se pudo finalizar la anulación.'));
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_deadlock_terminado_reintenta_confirmacion_completa_sin_duplicados(): void
    {
        $stock = new class extends InventarioService
        {
            public int $intentos = 0;

            public array $saldos = [];

            public function entrada(int $inventarioId, mixed $cantidad): Inventario
            {
                $this->saldos[] = Inventario::findOrFail($inventarioId)->cantidad;
                $inventario = parent::entrada($inventarioId, $cantidad);
                if (++$this->intentos === 1) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }

                return $inventario;
            }
        };
        $this->servicioProduccion(terminado: $stock)->confirmarProduccion($this->produccion->id, 17);
        $this->assertSame(2, $stock->intentos);
        $this->assertSame(['0', '0'], $stock->saldos);
        $this->assertSame(1, Inventario::count());
        $this->assertSame(1, MovimientoInventario::count());
        $this->assertSame('3', Inventario::sole()->cantidad);
        $this->assertSame('9.6296296330', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertTrue($this->produccion->fresh()->producto_terminado_aplicado);
    }

    public function test_deadlock_terminado_reintenta_anulacion_sin_duplicar_salida(): void
    {
        $this->confirmar();
        $stock = new class extends InventarioService
        {
            public int $intentos = 0;

            public array $saldos = [];

            public function salida(int $inventarioId, mixed $cantidad): Inventario
            {
                $this->saldos[] = Inventario::findOrFail($inventarioId)->cantidad;
                $inventario = parent::salida($inventarioId, $cantidad);
                if (++$this->intentos === 1) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }

                return $inventario;
            }
        };
        $this->servicioProduccion(terminado: $stock)->anularProduccion($this->produccion->id, 17);
        $this->assertSame(2, $stock->intentos);
        $this->assertSame(['3', '3'], $stock->saldos);
        $this->assertSame(1, MovimientoInventario::where('tipo_movimiento', 'SALIDA')->count());
        $this->assertSame('0', Inventario::sole()->cantidad);
        $this->assertSame('10.0000000000', InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_deadlock_agota_tres_intentos_y_revierte_ambos_inventarios(): void
    {
        $this->confirmar();
        $stock = new class extends InventarioService
        {
            public int $intentos = 0;

            public function salida(int $inventarioId, mixed $cantidad): Inventario
            {
                parent::salida($inventarioId, $cantidad);
                $this->intentos++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioProduccion(terminado: $stock)->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame(3, $stock->intentos);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_movimientos_automaticos_creados_por_produccion_siguen_inmutables(): void
    {
        $this->confirmar();
        $this->servicioProduccion()->anularProduccion($this->produccion->id, 17);
        $antes = $this->snapshot();
        $controller = new MovimientoInventarioController;
        foreach (MovimientoInventario::all() as $movimiento) {
            $this->rechaza(fn () => $controller->edit($movimiento), ValidationException::class);
            $this->rechaza(fn () => $controller->cambiarEstado($movimiento), ValidationException::class);
            $this->rechaza(fn () => $controller->update(Request::create('/', 'PUT', []), $movimiento), ValidationException::class);
            $this->rechaza(fn () => $movimiento->delete(), ValidationException::class);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_confirmar_y_anular_mantienen_orden_de_bloqueos_y_una_sola_transaccion(): void
    {
        $this->agregarMaterial();
        $grammar = new class extends \Illuminate\Database\Query\Grammars\SQLiteGrammar
        {
            public array $bloqueos = [];

            public function compileSelect(\Illuminate\Database\Query\Builder $query)
            {
                if ($query->lock) {
                    $this->bloqueos[] = ['tabla' => $query->from, 'parametros' => $query->getBindings(),
                        'nivel' => $query->getConnection()->transactionLevel()];
                }

                return parent::compileSelect($query);
            }
        };
        DB::connection()->setQueryGrammar($grammar);
        foreach (['confirmarProduccion', 'anularProduccion'] as $metodo) {
            $grammar->bloqueos = [];
            $this->servicioProduccion()->$metodo($this->produccion->id, 17);
            $tablas = array_column($grammar->bloqueos, 'tabla');
            $this->assertSame(['producciones', 'productos'], array_slice($tablas, 0, 2));
            $this->assertGreaterThan(array_search('productos', $tablas, true), array_search('inventarios', $tablas, true));
            $this->assertGreaterThan(array_search('inventarios', $tablas, true), array_search('inventarios_compra', $tablas, true));
            $this->assertGreaterThan(array_search('movimientos_inventario', $tablas, true), array_search('inventarios', $tablas, true));
            $materiales = array_values(array_filter($grammar->bloqueos, fn ($bloqueo) => $bloqueo['tabla'] === 'inventarios_compra'));
            $this->assertSame([[7], [44]], array_column($materiales, 'parametros'));
            $this->assertSame([1], array_values(array_unique(array_column($grammar->bloqueos, 'nivel'))));
        }
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    #[DataProvider('guardadosCanceladosConfirmacion')]
    public function test_guardado_cancelado_sin_excepcion_revierte_confirmacion_completa(string $modelo, string $evento): void
    {
        $antes = $this->snapshot();
        $modelo::$evento(fn () => false);
        $this->rechaza(fn () => $this->confirmar(), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function guardadosCanceladosConfirmacion(): array
    {
        return [
            [Inventario::class, 'updating'], [Produccion::class, 'updating'], [ConsumoProduccion::class, 'creating'],
            [MovimientoInventarioCompra::class, 'creating'], [MovimientoInventario::class, 'creating'],
            [InventarioCompra::class, 'updating'],
        ];
    }

    #[DataProvider('guardadosCanceladosAnulacion')]
    public function test_guardado_cancelado_sin_excepcion_revierte_anulacion_completa(string $modelo, string $evento): void
    {
        $this->confirmar();
        $antes = $this->snapshot();
        $modelo::$evento(fn () => false);
        $this->rechaza(fn () => $this->servicioProduccion()->anularProduccion($this->produccion->id, 17), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function guardadosCanceladosAnulacion(): array
    {
        return [
            [Inventario::class, 'updating'], [Produccion::class, 'updating'], [MovimientoInventarioCompra::class, 'creating'],
            [MovimientoInventario::class, 'creating'], [InventarioCompra::class, 'updating'],
        ];
    }

    private function crearInventario(string $cantidad = '0', bool $activo = true): Inventario
    {
        $inventario = Inventario::create(['producto_id' => 3, 'estado' => $activo]);
        // Los saldos previos se preparan directamente solo en la conexión aislada.
        DB::table('inventarios')->where('id', $inventario->id)->update(['cantidad' => $cantidad]);

        return $inventario->refresh();
    }

    private function datosMovimiento(int $inventarioId, string $tipo): array
    {
        return ['produccion_id' => $this->produccion->id, 'inventario_id' => $inventarioId,
            'tipo_movimiento' => $tipo, 'cantidad' => '3', 'fecha_movimiento' => now(), 'estado' => true];
    }
}
