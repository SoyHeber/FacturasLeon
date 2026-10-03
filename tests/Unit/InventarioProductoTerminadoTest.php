<?php

namespace Tests\Unit;

use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Produccion;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\Support\InventarioProductoTerminadoTestCase;

class InventarioProductoTerminadoTest extends InventarioProductoTerminadoTestCase
{
    public function test_producto_tiene_cero_o_un_inventario_y_relaciones_corresponden(): void
    {
        $producto = Producto::findOrFail(3);
        $this->assertInstanceOf(HasOne::class, $producto->inventarios());
        $this->assertInstanceOf(Inventario::class, $producto->inventarios);
        $this->assertSame(7, $producto->inventarios->id);
        $this->assertNull(Producto::findOrFail(5)->inventarios);
        $this->assertSame(3, Inventario::findOrFail(7)->producto->id);
        $this->rechaza(fn () => DB::table('inventarios')->insert(['producto_id' => 3]), QueryException::class);

        $movimiento = MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101]));
        $this->assertSame(101, $movimiento->produccion->id);
        $this->assertSame($movimiento->id, Produccion::findOrFail(101)->movimientosInventario->sole()->id);
        $this->assertSame($movimiento->id, Inventario::findOrFail(7)->movimientos->sole()->id);
    }

    public function test_inventario_nuevo_inicia_en_cero_e_ignora_cantidad_manipulada(): void
    {
        $antes = $this->snapshot();
        (new InventarioController)->store(Request::create('/inventarios', 'POST', [
            'producto_id' => 5, 'cantidad' => '999999999999999999999999999', 'stock_minimo' => 0, 'estado' => 1,
        ]));
        $this->assertSame('0', Producto::findOrFail(5)->inventarios->cantidad);
        $this->assertSame(0, MovimientoInventario::count());
        $this->assertSame($antes[0], array_slice($this->snapshot()[0], 0, 2));
        $this->assertNotContains('cantidad', (new Inventario)->getFillable());
    }

    public function test_editar_inventario_solo_modifica_metadatos(): void
    {
        (new InventarioController)->update(Request::create('/inventarios/7', 'PUT', [
            'producto_id' => 5, 'cantidad' => '-999', 'stock_minimo' => 2, 'stock_maximo' => 30,
            'ubicacion' => 'Vitrina A', 'estado' => 1,
        ]), Inventario::findOrFail(7));
        $inventario = Inventario::findOrFail(7);
        $this->assertSame(3, $inventario->producto_id);
        $this->assertSame('20', $inventario->cantidad);
        $this->assertSame('Vitrina A', $inventario->ubicacion);
        $this->assertSame(2, $inventario->stock_minimo);
        $this->assertSame('30', $this->saldo(44));
        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_servicio_exige_transaccion_existente(): void
    {
        foreach (['entrada', 'salida', 'ajustar'] as $metodo) {
            $this->rechaza(fn () => $this->stock->$metodo(7, '1'), LogicException::class);
        }
        $this->rechaza(fn () => $this->stock->conInventariosBloqueados([7], fn () => null), LogicException::class);
        $this->assertSame('20', $this->saldo());
    }

    public function test_bloqueos_se_ordenan_se_deduplican_y_se_liberan(): void
    {
        $consultas = [];
        $this->database->getDatabaseManager()->listen(function ($consulta) use (&$consultas) {
            if (str_contains($consulta->sql, 'select * from "inventarios"')) {
                $consultas[] = $consulta->bindings[0];
            }
        });
        DB::transaction(function () {
            $this->stock->conInventariosBloqueados([44, 7, 44], function ($inventarios) {
                $this->assertSame([7, 44], $inventarios->keys()->all());
                $this->stock->entrada(7, '1');
                $this->stock->salida(44, '2');
                $this->rechaza(fn () => $this->stock->entrada(999, '1'), LogicException::class);
                $this->rechaza(fn () => $this->stock->conInventariosBloqueados([7], fn () => null), LogicException::class);
            });
        });
        $this->assertSame([7, 44], $consultas);
        DB::transaction(fn () => $this->stock->entrada(7, '1'));
        $this->assertSame('22', $this->saldo());
        $this->assertSame('28', $this->saldo(44));
    }

    public function test_lote_se_limpia_tambien_si_falla_la_operacion(): void
    {
        $this->rechaza(fn () => DB::transaction(fn () => $this->stock->conInventariosBloqueados([7], function () {
            $this->stock->entrada(7, '1');
            throw new RuntimeException('Fallo controlado.');
        })), RuntimeException::class);
        $this->assertSame('20', $this->saldo());
        DB::transaction(fn () => $this->stock->conInventariosBloqueados([44], fn () => $this->stock->entrada(44, '1')));
        $this->assertSame('31', $this->saldo(44));
    }

    public function test_bigint_unsigned_completo_se_conserva_sin_float(): void
    {
        DB::table('inventarios')->where('id', 7)->update(['cantidad' => '9223372036854775807']);
        DB::transaction(fn () => $this->stock->entrada(7, '9223372036854775807'));
        DB::transaction(fn () => $this->stock->entrada(7, '1'));
        $this->assertSame(InventarioService::MAXIMO_SALDO, $this->saldo());
        DB::transaction(fn () => $this->stock->salida(7, InventarioService::MAXIMO_SALDO));
        $this->assertSame('0', $this->saldo());
        DB::transaction(fn () => $this->stock->entrada(7, InventarioService::MAXIMO_SALDO));
        $this->assertSame(InventarioService::MAXIMO_SALDO, $this->saldo());
    }

    public function test_saldo_negativo_y_overflow_rechazados_sin_escrituras(): void
    {
        $antes = $this->snapshot();
        $this->rechaza(fn () => DB::transaction(fn () => $this->stock->salida(7, '21')), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('inventarios')->where('id', 7)->update(['cantidad' => InventarioService::MAXIMO_SALDO]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => DB::transaction(fn () => $this->stock->entrada(7, '1')), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_cantidades_deben_ser_enteros_exactos(): void
    {
        foreach ([1.0, 1.5, '1.0', '1e3', true, [], ' 1', '', '18446744073709551616'] as $cantidad) {
            $this->rechaza(fn () => DB::transaction(fn () => $this->stock->ajustar(7, $cantidad)), ValidationException::class);
        }
        foreach (['0', '-1'] as $cantidad) {
            $this->rechaza(fn () => DB::transaction(fn () => $this->stock->entrada(7, $cantidad)), ValidationException::class);
            $this->rechaza(fn () => DB::transaction(fn () => $this->stock->salida(7, $cantidad)), ValidationException::class);
        }
        $this->assertSame('20', $this->saldo());
    }

    public function test_entrada_salida_y_ajustes_manuales_fuerzan_produccion_null(): void
    {
        $controller = new MovimientoInventarioController;
        foreach ([['ENTRADA', '10', '30'], ['SALIDA', '5', '25'], ['AJUSTE', '-3', '22'], ['AJUSTE', '2', '24']] as [$tipo, $cantidad, $saldo]) {
            $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento([
                'tipo_movimiento' => $tipo, 'cantidad' => $cantidad, 'produccion_id' => 101,
            ])));
            $this->assertSame($saldo, $this->saldo());
            $this->assertNull(MovimientoInventario::latest('id')->firstOrFail()->produccion_id);
        }
        $this->assertSame(4, MovimientoInventario::count());
    }

    public function test_movimiento_nuevo_inactivo_no_aplica_saldo(): void
    {
        $controller = new MovimientoInventarioController;
        foreach ([['ENTRADA', '10'], ['SALIDA', '100'], ['AJUSTE', '-100']] as [$tipo, $cantidad]) {
            $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento([
                'tipo_movimiento' => $tipo, 'cantidad' => $cantidad, 'estado' => 0,
            ])));
            $this->assertFalse(MovimientoInventario::latest('id')->firstOrFail()->estado);
            $this->assertSame('20', $this->saldo());
        }
        $datos = $this->datosMovimiento();
        unset($datos['estado']);
        $controller->store(Request::create('/movimientos-inventario', 'POST', $datos));
        $this->assertSame('20', $this->saldo());
    }

    public function test_crud_rechaza_saldo_negativo_overflow_y_cantidades_invalidas(): void
    {
        $controller = new MovimientoInventarioController;
        foreach ([['SALIDA', '21'], ['ENTRADA', '-1'], ['SALIDA', '-1'], ['AJUSTE', '0'],
            ['ENTRADA', '9223372036854775808'], ['AJUSTE', '-9223372036854775809'], ['ENTRADA', 1.0], ['AJUSTE', '1.5']] as [$tipo, $cantidad]) {
            $antes = $this->snapshot();
            $this->rechaza(fn () => $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento([
                'tipo_movimiento' => $tipo, 'cantidad' => $cantidad,
            ]))), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
        }
        DB::table('inventarios')->where('id', 7)->update(['cantidad' => InventarioService::MAXIMO_SALDO]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento())), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_movimientos_bigint_y_reversion_del_minimo_firmado_son_exactos(): void
    {
        $controller = new MovimientoInventarioController;
        DB::table('inventarios')->where('id', 7)->update(['cantidad' => '0']);
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento(['cantidad' => InventarioService::MAXIMO_MOVIMIENTO])));
        $this->assertSame(InventarioService::MAXIMO_MOVIMIENTO, $this->saldo());
        $this->assertSame(InventarioService::MAXIMO_MOVIMIENTO, MovimientoInventario::firstOrFail()->cantidad);

        DB::table('inventarios')->where('id', 7)->update(['cantidad' => '9223372036854775808']);
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento([
            'tipo_movimiento' => 'AJUSTE', 'cantidad' => InventarioService::MINIMO_MOVIMIENTO,
        ])));
        $movimiento = MovimientoInventario::latest('id')->firstOrFail();
        $this->assertSame(InventarioService::MINIMO_MOVIMIENTO, $movimiento->cantidad);
        $this->assertSame('0', $this->saldo());
        $controller->cambiarEstado($movimiento);
        $this->assertSame('9223372036854775808', $this->saldo());
    }

    public function test_editar_movimiento_aplica_diferencia_y_estado_correctamente(): void
    {
        $controller = new MovimientoInventarioController;
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento()));
        $movimiento = MovimientoInventario::firstOrFail();
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['cantidad' => '15', 'produccion_id' => 101])), $movimiento);
        $this->assertSame('35', $this->saldo());
        $this->assertNull($movimiento->refresh()->produccion_id);
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['estado' => 0])), $movimiento);
        $this->assertSame('20', $this->saldo());
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['tipo_movimiento' => 'AJUSTE', 'cantidad' => '-2'])), $movimiento);
        $this->assertSame('18', $this->saldo());
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['tipo_movimiento' => 'SALIDA', 'cantidad' => '3'])), $movimiento);
        $this->assertSame('17', $this->saldo());
    }

    public function test_editar_sin_cambiar_efecto_no_exige_stock_temporal_para_revertir(): void
    {
        $controller = new MovimientoInventarioController;
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento()));
        $movimiento = MovimientoInventario::firstOrFail();
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento(['tipo_movimiento' => 'SALIDA', 'cantidad' => '30'])));
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['observacion' => 'Detalle corregido'])), $movimiento);
        $this->assertSame('0', $this->saldo());
        $this->assertSame('Detalle corregido', $movimiento->refresh()->observacion);
    }

    public function test_edicion_entre_inventarios_es_atomica(): void
    {
        $controller = new MovimientoInventarioController;
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento()));
        $movimiento = MovimientoInventario::firstOrFail();
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['inventario_id' => 44, 'cantidad' => '5'])), $movimiento);
        $this->assertSame('20', $this->saldo());
        $this->assertSame('35', $this->saldo(44));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento([
            'tipo_movimiento' => 'SALIDA', 'cantidad' => '100',
        ])), $movimiento), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_cambiar_estado_recarga_movimiento_y_conserva_ajustes(): void
    {
        $controller = new MovimientoInventarioController;
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento(['tipo_movimiento' => 'AJUSTE', 'cantidad' => '-3'])));
        $desactualizado = MovimientoInventario::firstOrFail();
        $controller->cambiarEstado($desactualizado);
        $this->assertSame('20', $this->saldo());
        $controller->cambiarEstado($desactualizado);
        $this->assertSame('17', $this->saldo());
        $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['cantidad' => '2'])), $desactualizado);
        $this->assertSame('22', $this->saldo());
    }

    public function test_no_se_puede_revertir_entrada_ya_consumida(): void
    {
        $controller = new MovimientoInventarioController;
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento()));
        $entrada = MovimientoInventario::firstOrFail();
        $controller->store(Request::create('/movimientos-inventario', 'POST', $this->datosMovimiento(['tipo_movimiento' => 'SALIDA', 'cantidad' => '30'])));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->cambiarEstado($entrada), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->rechaza(fn () => $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['estado' => 0])), $entrada), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_automaticos_no_son_editables_inactivables_desvinculables_ni_eliminables(): void
    {
        $controller = new MovimientoInventarioController;
        $automatico = MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101]));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->edit($automatico), ValidationException::class);
        $this->rechaza(fn () => $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento(['produccion_id' => null])), $automatico), ValidationException::class);
        $this->rechaza(fn () => $controller->cambiarEstado($automatico), ValidationException::class);
        $this->rechaza(fn () => $automatico->update(['observacion' => 'Manipulada']), ValidationException::class);
        $automatico->refresh();
        $this->rechaza(fn () => $automatico->update(['produccion_id' => null]), ValidationException::class);
        $automatico->refresh();
        $this->rechaza(fn () => $automatico->delete(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_escrituras_recargan_origen_antes_de_modificar(): void
    {
        $controller = new MovimientoInventarioController;
        $manual = MovimientoInventario::create($this->datosMovimiento());
        DB::table('movimientos_inventario')->where('id', $manual->id)->update(['produccion_id' => 101]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $controller->cambiarEstado($manual), ValidationException::class);
        $this->rechaza(fn () => $controller->update(Request::create('/movimientos-inventario/1', 'PUT', $this->datosMovimiento()), $manual), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_unique_por_produccion_tipo_permite_manuales_y_fk_restringe_borrados(): void
    {
        MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101]));
        $this->rechaza(fn () => MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101])), QueryException::class);
        MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101, 'tipo_movimiento' => 'SALIDA']));
        $this->rechaza(fn () => MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 101, 'tipo_movimiento' => 'SALIDA'])), QueryException::class);
        MovimientoInventario::create($this->datosMovimiento());
        MovimientoInventario::create($this->datosMovimiento());
        $this->assertSame(4, MovimientoInventario::count());
        $this->rechaza(fn () => DB::table('producciones')->where('id', 101)->delete(), QueryException::class);
        $this->rechaza(fn () => MovimientoInventario::create($this->datosMovimiento(['produccion_id' => 999])), QueryException::class);
    }

    public function test_indicador_false_conserva_confirmadas_historicas_y_no_crea_movimientos(): void
    {
        $migracion = $this->migracion('000003_agregar_producto_terminado_aplicado_a_producciones');
        $migracion->down();
        MovimientoInventario::create($this->datosMovimiento(['tipo_movimiento' => 'AJUSTE', 'cantidad' => '-2', 'estado' => false]));
        $antes = $this->snapshot();
        $produccion = (array) DB::table('producciones')->where('id', 101)->first();
        $migracion->up();
        $despues = (array) DB::table('producciones')->where('id', 101)->first();
        $this->assertSame(0, $despues['producto_terminado_aplicado']);
        unset($despues['producto_terminado_aplicado']);
        $this->assertSame($produccion, $despues);
        $this->assertSame($antes, $this->snapshot());
        $this->assertFalse(Produccion::findOrFail(101)->producto_terminado_aplicado);
        DB::table('producciones')->insert(['producto_id' => 5, 'cantidad' => 1]);
        $this->assertFalse(Produccion::latest('id')->firstOrFail()->producto_terminado_aplicado);
        DB::table('producciones')->where('id', 101)->update(['producto_terminado_aplicado' => true]);
        $this->rechaza(fn () => $migracion->down(), RuntimeException::class);
        $this->assertTrue(Schema::hasColumn('producciones', 'producto_terminado_aplicado'));
    }

    public function test_migracion_bigint_valida_antes_de_alterar_y_conserva_historicos(): void
    {
        MovimientoInventario::create($this->datosMovimiento(['tipo_movimiento' => 'AJUSTE', 'cantidad' => '-2', 'estado' => false]));
        $antes = $this->snapshot();
        $ddl = [];
        $this->simularMySql($ddl);
        $this->migracion('000001_ampliar_cantidades_inventario_producto_terminado')->up();
        $this->assertCount(2, $ddl);
        $this->assertStringContainsString('`inventarios` MODIFY COLUMN cantidad BIGINT UNSIGNED NOT NULL DEFAULT 0', $ddl[0]);
        $this->assertStringContainsString('`movimientos_inventario` MODIFY COLUMN cantidad BIGINT NOT NULL', $ddl[1]);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_migracion_rechaza_datos_invalidos_antes_de_cualquier_ddl(): void
    {
        MovimientoInventario::create($this->datosMovimiento());
        $ddl = [];
        $this->simularMySql($ddl);
        foreach ([['inventarios', 7, '-1'], ['inventarios', 7, '1.5'], ['inventarios', 7, '18446744073709551616'],
            ['movimientos_inventario', 1, '9223372036854775808'], ['movimientos_inventario', 1, '-9223372036854775809'],
            ['movimientos_inventario', 1, '1e3']] as [$tabla, $id, $cantidad]) {
            $original = $this->database->table($tabla)->where('id', $id)->value('cantidad');
            $this->database->table($tabla)->where('id', $id)->update(['cantidad' => $cantidad]);
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->migracion('000001_ampliar_cantidades_inventario_producto_terminado')->up(), RuntimeException::class);
            $this->assertSame([], $ddl);
            $this->assertSame($antes, $this->snapshot());
            $this->database->table($tabla)->where('id', $id)->update(['cantidad' => $original]);
        }
    }

    public function test_rollback_no_reduce_precision_si_perderia_datos(): void
    {
        $ddl = [];
        $this->simularMySql($ddl);
        $this->database->table('inventarios')->where('id', 7)->update(['cantidad' => '4294967296']);
        $this->rechaza(fn () => $this->migracion('000001_ampliar_cantidades_inventario_producto_terminado')->down(), RuntimeException::class);
        $this->assertSame([], $ddl);
        $this->database->table('inventarios')->where('id', 7)->update(['cantidad' => '20']);
        $this->database->table('movimientos_inventario')->insert($this->datosMovimiento(['cantidad' => '2147483648']));
        $this->rechaza(fn () => $this->migracion('000001_ampliar_cantidades_inventario_producto_terminado')->down(), RuntimeException::class);
        $this->assertSame([], $ddl);
    }

    public function test_ddl_vincula_produccion_nullable_unique_fk_indice_y_check_sin_reclasificar(): void
    {
        $antes = $this->snapshot();
        $ddl = [];
        $this->simularMySql($ddl);
        $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->up();
        $this->assertCount(1, $ddl);
        $this->assertStringContainsString('ADD COLUMN produccion_id BIGINT UNSIGNED NULL', $ddl[0]);
        $this->assertStringContainsString('ADD INDEX movimientos_inventario_produccion_id_index (produccion_id)', $ddl[0]);
        $this->assertStringContainsString('REFERENCES `producciones` (id) ON DELETE RESTRICT', $ddl[0]);
        $this->assertStringContainsString('UNIQUE (produccion_id, tipo_movimiento)', $ddl[0]);
        $this->assertStringContainsString("produccion_id IS NULL OR (tipo_movimiento IN ('ENTRADA', 'SALIDA') AND cantidad > 0 AND estado = 1)", $ddl[0]);
        $this->assertSame($antes, $this->snapshot());
        $this->database->table('movimientos_inventario')->insert($this->datosMovimiento(['produccion_id' => 101]));
        $this->rechaza(fn () => $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->down(), RuntimeException::class);
        $this->assertCount(1, $ddl);
    }

    public function test_check_real_de_migracion_rechaza_automaticos_invalidos_y_permite_ajuste_manual(): void
    {
        $ddl = [];
        $this->simularMySql($ddl);
        $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->up();
        DB::swap($this->database->getDatabaseManager());
        preg_match('/CHECK\s*\((.*)\)\s*$/s', $ddl[0], $coincidencia);
        $this->assertNotEmpty($coincidencia[1]);

        // Ejecuta el CHECK de la migración con cantidades INTEGER en una tabla aislada.
        DB::statement('CREATE TABLE comprobacion_movimientos (
            id INTEGER PRIMARY KEY, inventario_id INTEGER, produccion_id BIGINT NULL,
            tipo_movimiento TEXT NOT NULL, cantidad BIGINT NOT NULL,
            fecha_movimiento TEXT, estado BOOLEAN NOT NULL, CHECK ('.$coincidencia[1].')
        )');
        foreach ([['tipo_movimiento' => 'AJUSTE'], ['cantidad' => '0'], ['cantidad' => '-1'], ['estado' => 0]] as $cambio) {
            $this->rechaza(fn () => DB::table('comprobacion_movimientos')->insert($this->datosMovimiento($cambio + ['produccion_id' => 101])), QueryException::class);
        }
        DB::table('comprobacion_movimientos')->insert($this->datosMovimiento(['produccion_id' => 101]));
        DB::table('comprobacion_movimientos')->insert($this->datosMovimiento(['produccion_id' => 101, 'tipo_movimiento' => 'SALIDA']));
        DB::table('comprobacion_movimientos')->insert($this->datosMovimiento(['tipo_movimiento' => 'AJUSTE', 'cantidad' => '-3', 'estado' => false]));
        $this->rechaza(fn () => DB::table('comprobacion_movimientos')->whereNotNull('produccion_id')->update(['estado' => 0]), QueryException::class);
        $this->assertSame(3, DB::table('comprobacion_movimientos')->count());
    }

    public function test_servidor_sin_check_efectivo_se_rechaza_antes_del_ddl(): void
    {
        $ddl = [];
        $this->simularMySql($ddl, '8.0.15');
        $this->rechaza(fn () => $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->up(), RuntimeException::class);
        $this->assertSame([], $ddl);
    }

    public function test_mysql_y_mariadb_retiran_check_con_su_sintaxis_y_sin_modificar_datos(): void
    {
        $ddl = [];
        $this->simularMySql($ddl);
        $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->down();
        $this->assertStringContainsString('DROP CHECK movimientos_inventario_produccion_check', $ddl[0]);
        DB::swap($this->database->getDatabaseManager());
        $ddl = [];
        $this->simularMySql($ddl, '5.5.5-10.11.6-MariaDB');
        $this->migracion('000002_vincular_movimientos_inventario_a_producciones')->down();
        $this->assertStringContainsString('DROP CONSTRAINT movimientos_inventario_produccion_check', $ddl[0]);
    }

    public function test_formularios_y_botones_respetan_cantidad_producto_y_solo_lectura(): void
    {
        $raiz = dirname(__DIR__, 2).'/resources/views/';
        foreach (['inventarios/create.blade.php', 'inventarios/edit.blade.php'] as $vista) {
            $contenido = file_get_contents($raiz.$vista);
            $this->assertStringNotContainsString('name="cantidad"', $contenido);
        }
        $this->assertStringNotContainsString('name="producto_id"', file_get_contents($raiz.'inventarios/edit.blade.php'));

        $container = Container::getInstance();
        $url = $container->make('redirect')->getUrlGenerator();
        $rutas = new \Illuminate\Routing\RouteCollection;
        $rutas->add((new Route('GET', '/movimientos-inventario', fn () => null))->name('movimientos_inventario.index'));
        foreach (['show', 'edit', 'cambiar-estado'] as $nombre) {
            $rutas->add((new Route('GET', '/movimientos-inventario/{movimientoInventario}/'.$nombre, fn () => null))->name('movimientos_inventario.'.$nombre));
        }
        $url->setRoutes($rutas);
        $container->instance('url', $url);
        $container->instance('session', new \Illuminate\Session\Store('vista', new \Illuminate\Session\ArraySessionHandler(120)));
        $gate = new Gate($container, fn () => (object) ['id' => 17]);
        $gate->before(fn () => true);
        $container->instance(\Illuminate\Contracts\Auth\Access\Gate::class, $gate);
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());

        foreach (['movimientos_inventario/index.blade.php', 'movimientos_inventario/show.blade.php'] as $vista) {
            $contenido = file_get_contents($raiz.$vista);
            preg_match_all('/<div class="d-flex gap-2(?: flex-wrap)?">(.*?)<\/div>/s', $contenido, $coincidencias);
            $acciones = array_values(array_filter($coincidencias[1], fn ($html) => str_contains($html, 'movimientos_inventario.edit')));
            $this->assertCount(1, $acciones);
            $renderizar = function ($movimiento) use ($compiler, $acciones) {
                ob_start();
                try {
                    eval('?>'.$compiler->compileString($acciones[0]));

                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            };
            $manual = (new MovimientoInventario)->forceFill($this->datosMovimiento(['id' => 1, 'produccion_id' => null]));
            $html = $renderizar($manual);
            $this->assertStringContainsString('/1/edit', $html);
            $this->assertStringContainsString('/1/cambiar-estado', $html);
            $automatico = (new MovimientoInventario)->forceFill($this->datosMovimiento(['id' => 2, 'produccion_id' => 101]));
            $html = $renderizar($automatico);
            $this->assertStringNotContainsString('/2/edit', $html);
            $this->assertStringNotContainsString('/2/cambiar-estado', $html);
            $this->assertStringContainsString('Solo lectura', $html);
        }
    }

    private function simularMySql(array &$ddl, string $version = '8.0.36'): void
    {
        $conexion = Mockery::mock(MySqlConnection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getQueryGrammar')->andReturn(new MySqlGrammar);
        DB::shouldReceive('connection')->andReturn($conexion);
        DB::shouldReceive('table')->andReturnUsing(fn ($tabla) => $this->database->table($tabla));
        DB::shouldReceive('selectOne')->with('SELECT VERSION() AS version')->andReturn((object) ['version' => $version]);
        DB::shouldReceive('statement')->andReturnUsing(function ($sql) use (&$ddl) {
            $ddl[] = $sql;

            return true;
        });
    }
}
