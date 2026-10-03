<?php

namespace Tests\Unit;

use App\Http\Controllers\CompraController;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Services\CompraInventarioService;
use App\Services\InventarioCompraService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\Support\CompraInventarioTestCase;

class RegistroCompraInventarioTest extends CompraInventarioTestCase
{
    public function test_una_linea_crea_compra_detalle_entrada_y_stock_exactos(): void
    {
        $datos = $this->datosCompra([$this->linea(7, '1.23456')]);
        $compra = $this->servicio()->registrarCompra($datos, 17);
        $detalle = DetalleCompra::firstOrFail();
        $movimiento = MovimientoInventarioCompra::firstOrFail();
        $this->assertTrue($compra->estado);
        $this->assertTrue($compra->fresh()->inventario_aplicado);
        $this->assertSame(77, (int) $compra->tipo_documento_id);
        $this->assertSame('1.2345600000', $detalle->cantidad);
        $this->assertSame($detalle->cantidad, $movimiento->cantidad);
        $this->assertSame($detalle->id, (int) $movimiento->detalle_compra_id);
        $this->assertNull($movimiento->produccion_id);
        $this->assertSame('ENTRADA', $movimiento->tipo_movimiento);
        $this->assertTrue($movimiento->estado);
        $this->assertSame($compra->created_at->format('Y-m-d H:i:s'), $movimiento->fecha_movimiento->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Ingreso por compra #' . $compra->id, $movimiento->motivo);
        $this->assertSame('11.2345600000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(1, Compra::count());
        $this->assertSame(1, DetalleCompra::count());
        $this->assertSame(1, MovimientoInventarioCompra::count());
    }

    public function test_varias_lineas_bloquean_inventarios_distintos_en_orden_y_una_sola_vez(): void
    {
        $bloqueos = [];
        DB::listen(function ($query) use (&$bloqueos) {
            if (str_starts_with($query->sql, 'select * from "inventarios_compra"')) {
                $bloqueos[] = (int) $query->bindings[0];
            }
        });
        $datos = $this->datosCompra([$this->linea(44, '0.2'), $this->linea(7, '0.1'), $this->linea(44, '0.00001'), $this->linea(7, '0.2')]);
        $compra = $this->servicio()->registrarCompra($datos, 17);
        $this->assertSame([7, 44], $bloqueos);
        $this->assertSame([1, 2, 3, 4], $compra->detalles()->orderBy('numero_linea')->pluck('numero_linea')->all());
        $this->assertSame([44, 7, 44, 7], $compra->detalles()->orderBy('numero_linea')->pluck('inventario_compra_id')->all());
        $this->assertSame('10.3000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame('20.2000100000', InventarioCompra::findOrFail(44)->cantidad);
        $this->assertSame(4, MovimientoInventarioCompra::count());
        foreach (MovimientoInventarioCompra::all() as $movimiento) {
            $this->assertSame($movimiento->detalleCompra->cantidad, $movimiento->cantidad);
        }
    }

    public function test_diez_decimales_no_pasan_por_la_cantidad_float_de_la_calculadora(): void
    {
        DB::table('inventarios_compra')->where('id', 7)->update(['cantidad' => '0.0000000000']);
        $cantidad = '9999999999.9999999998';
        $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7, $cantidad)]), 17);
        $this->assertSame($cantidad, DetalleCompra::firstOrFail()->cantidad);
        $this->assertSame($cantidad, MovimientoInventarioCompra::firstOrFail()->cantidad);
        $this->assertSame($cantidad, InventarioCompra::findOrFail(7)->cantidad);
    }

    public function test_decimales_minimos_se_acumulan_sin_redondeo(): void
    {
        $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7, '0.0000000001'), $this->linea(7, '0.0000000002')]), 17);
        $this->assertSame('10.0000000003', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(['0.0000000001', '0.0000000002'], MovimientoInventarioCompra::orderBy('id')->pluck('cantidad')->all());
    }

    public function test_campos_tecnicos_y_movimientos_enviados_por_navegador_se_ignoran(): void
    {
        $linea = array_replace($this->linea(7, '2'), ['numero_linea' => 999, 'estado' => false, 'importe_bruto' => 999999, 'importe_total' => 999999]);
        $datos = array_replace($this->datosCompra([$linea]), [
            'estado' => false, 'inventario_aplicado' => true, 'fecha_anulacion' => '2020-01-01 12:00:00',
            'anulado_por_id' => 17, 'marca_activa' => null, 'user_id' => 999,
            'importe_total' => 999999, 'movimientos' => [['tipo_movimiento' => 'SALIDA', 'cantidad' => '999']],
        ]);
        $compra = $this->servicio()->registrarCompra($datos, 17);
        $this->assertTrue($compra->estado);
        $this->assertTrue($compra->inventario_aplicado);
        $this->assertNull($compra->fecha_anulacion);
        $this->assertNull($compra->anulado_por_id);
        $this->assertSame(17, (int) $compra->user_id);
        $this->assertSame(1, (int) $compra->fresh()->marca_activa);
        $this->assertSame('20.00', $compra->importe_total);
        $this->assertSame('20.00', DetalleCompra::firstOrFail()->importe_total);
        $this->assertSame(1, DetalleCompra::firstOrFail()->numero_linea);
        $this->assertTrue(DetalleCompra::firstOrFail()->estado);
        $this->assertSame('ENTRADA', MovimientoInventarioCompra::firstOrFail()->tipo_movimiento);
    }

    public function test_inventario_inactivo_o_inexistente_impide_crear_toda_la_compra(): void
    {
        DB::table('inventarios_compra')->where('id', 44)->update(['estado' => 0]);
        $this->saldosIniciales = $this->snapshotInventarios();
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(44)]), 17), ValidationException::class);
        $this->sinComprasParciales();
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(999)]), 17), ValidationException::class);
        $this->sinComprasParciales();
    }

    public function test_fact_debe_existir_seguir_activo_y_conservar_su_codigo(): void
    {
        foreach ([['estado' => 0], ['estado' => 1, 'codigo' => 'OTRO']] as $cambio) {
            DB::table('tipos_documento')->where('id', 77)->update($cambio);
            $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7)]), 17), ValidationException::class);
            $this->sinComprasParciales();
        }
        DB::table('tipos_documento')->delete();
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7)]), 17), ValidationException::class);
        $this->sinComprasParciales();
    }

    public function test_cantidades_invalidas_y_compra_sin_lineas_no_dejan_datos(): void
    {
        foreach (['0', '-1', 0.1, '0.00000000001'] as $cantidad) {
            $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7, $cantidad)]), 17), ValidationException::class);
            $this->sinComprasParciales();
        }
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([]), 17), ValidationException::class);
        $this->sinComprasParciales();
    }

    public function test_error_en_segunda_linea_revierte_cabecera_y_primer_detalle(): void
    {
        $intentos = 0;
        DetalleCompra::creating(function () use (&$intentos) {
            if (++$intentos === 2) {
                $this->assertSame(1, Compra::count());
                $this->assertSame(1, DetalleCompra::count());
                $this->assertSame(0, MovimientoInventarioCompra::count());
                throw new RuntimeException('Error al crear la segunda línea.');
            }
        });
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(44)]), 17), RuntimeException::class);
        $this->assertSame(2, $intentos);
        $this->sinComprasParciales();
    }

    public function test_error_de_movimiento_revierte_el_stock_ya_aplicado(): void
    {
        $intentos = 0;
        MovimientoInventarioCompra::creating(function () use (&$intentos) {
            if (++$intentos === 2) {
                $this->assertSame('11.0000000000', InventarioCompra::findOrFail(7)->cantidad);
                $this->assertSame(1, MovimientoInventarioCompra::count());
                $this->assertFalse(Compra::firstOrFail()->inventario_aplicado);
                throw new RuntimeException('Error al crear el segundo movimiento.');
            }
        });
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(44)]), 17), RuntimeException::class);
        $this->sinComprasParciales();
    }

    public function test_unique_de_entrada_revierte_stock_compra_detalles_y_movimientos(): void
    {
        $intentos = 0;
        MovimientoInventarioCompra::creating(function ($movimiento) use (&$intentos) {
            if (++$intentos === 2) {
                $movimiento->detalle_compra_id = MovimientoInventarioCompra::firstOrFail()->detalle_compra_id;
            }
        });
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(7)]), 17), QueryException::class);
        $this->assertSame(2, $intentos);
        $this->sinComprasParciales();
    }

    public function test_error_de_stock_en_segundo_inventario_revierte_el_primero(): void
    {
        DB::table('inventarios_compra')->where('id', 44)->update(['cantidad' => '9999999999.9999999999']);
        $this->saldosIniciales = $this->snapshotInventarios();
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(44, '0.0000000001')]), 17), ValidationException::class);
        $this->sinComprasParciales();
    }

    public function test_bandera_solo_se_guarda_al_final_y_todo_usa_una_transaccion(): void
    {
        Compra::creating(function ($compra) {
            $this->assertFalse($compra->inventario_aplicado);
            $this->assertTrue($compra->estado);
            $this->assertSame(1, DB::connection()->transactionLevel());
        });
        MovimientoInventarioCompra::creating(function () {
            $this->assertFalse(Compra::firstOrFail()->inventario_aplicado);
            $this->assertSame(2, DetalleCompra::count());
            $this->assertSame(1, DB::connection()->transactionLevel());
        });
        Compra::updating(function ($compra) {
            $this->assertTrue($compra->inventario_aplicado);
            $this->assertFalse(Compra::findOrFail($compra->id)->inventario_aplicado);
            $this->assertSame(2, MovimientoInventarioCompra::count());
            $this->assertSame('12.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        });
        $compra = $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(7)]), 17);
        $this->assertTrue($compra->fresh()->inventario_aplicado);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_error_al_guardar_bandera_final_revierte_toda_la_operacion(): void
    {
        Compra::updating(function ($compra) {
            if ($compra->inventario_aplicado) {
                $this->assertSame(2, MovimientoInventarioCompra::count());
                throw new RuntimeException('No se pudo completar la compra.');
            }
        });
        $this->rechaza(fn () => $this->servicio()->registrarCompra($this->datosCompra([$this->linea(7), $this->linea(44)]), 17), RuntimeException::class);
        $this->sinComprasParciales();
    }

    public function test_deadlock_reintenta_con_saldos_nuevos_sin_duplicar_entradas(): void
    {
        $stock = new class extends InventarioCompraService {
            public int $llamadas = 0;
            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                $inventario = parent::entrada($inventarioId, $cantidad);
                if (++$this->llamadas === 1) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }
                return $inventario;
            }
        };
        $this->servicio($stock)->registrarCompra($this->datosCompra([$this->linea(7, '0.1'), $this->linea(7, '0.2')]), 17);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame('10.3000000000', InventarioCompra::findOrFail(7)->cantidad);
        $this->assertSame(1, Compra::count());
        $this->assertSame(2, DetalleCompra::count());
        $this->assertSame(2, MovimientoInventarioCompra::count());
        $this->assertTrue(Compra::firstOrFail()->inventario_aplicado);
    }

    public function test_reintentos_de_deadlock_estan_limitados_a_tres(): void
    {
        $stock = new class extends InventarioCompraService {
            public int $llamadas = 0;
            public function entrada(int $inventarioId, mixed $cantidad): InventarioCompra
            {
                parent::entrada($inventarioId, $cantidad);
                $this->llamadas++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $this->rechaza(fn () => $this->servicio($stock)->registrarCompra($this->datosCompra([$this->linea(7)]), 17), RuntimeException::class);
        $this->assertSame(3, $stock->llamadas);
        $this->sinComprasParciales();
    }

    public function test_error_de_unique_de_compra_no_modifica_la_compra_previa(): void
    {
        $datos = $this->datosCompra([$this->linea(7)]);
        $this->servicio()->registrarCompra($datos, 17);
        foreach ([$datos, array_replace($datos, ['numero' => 2])] as $duplicada) {
            $this->rechaza(fn () => $this->servicio()->registrarCompra($duplicada, 17), QueryException::class);
            $this->assertSame(1, Compra::count());
            $this->assertSame(1, DetalleCompra::count());
            $this->assertSame(1, MovimientoInventarioCompra::count());
            $this->assertSame('11.0000000000', InventarioCompra::findOrFail(7)->cantidad);
        }
    }

    public function test_store_delega_y_excluye_campos_tecnicos_del_navegador(): void
    {
        $datos = array_replace($this->datosCompra([$this->linea(7)]), ['estado' => 0, 'inventario_aplicado' => true, 'fecha_anulacion' => '2020-01-01', 'anulado_por_id' => 17, 'marca_activa' => 0]);
        $servicio = Mockery::mock(CompraInventarioService::class);
        $servicio->shouldReceive('registrarCompra')->once()->withArgs(function ($validated, $usuarioId) {
            foreach (['estado', 'inventario_aplicado', 'fecha_anulacion', 'anulado_por_id', 'marca_activa'] as $campo) {
                $this->assertArrayNotHasKey($campo, $validated);
            }
            $this->assertSame(17, $usuarioId);
            return true;
        })->andReturn(new Compra);
        $response = (new CompraController)->store(Request::create('/', 'POST', $datos), $servicio);
        $this->assertSame('http://localhost/compras', $response->getTargetUrl());
        $this->sinComprasParciales();
    }

    public function test_store_valida_activas_y_permite_reutilizar_documento_historico_inactivo(): void
    {
        DB::table('compras')->insert([
            'proveedor_id' => 13, 'user_id' => 17, 'tipo_documento_id' => 77, 'serie' => 'A', 'numero' => 1,
            'numero_autorizacion' => '00000000-0000-0000-0000-000000000001', 'fecha_emision' => '2020-01-01 12:00:00', 'moneda' => 'GTQ', 'estado' => 0,
        ]);
        $datos = $this->datosCompra([$this->linea(7)]);
        $datos['serie'] = 'a';
        $controller = new CompraController;
        $controller->store(Request::create('/', 'POST', $datos), $this->servicio());
        $this->assertSame(2, Compra::count());
        $this->assertFalse(Compra::orderBy('id')->firstOrFail()->inventario_aplicado);
        $this->assertTrue(Compra::orderByDesc('id')->firstOrFail()->inventario_aplicado);
        $this->rechaza(fn () => $controller->store(Request::create('/', 'POST', $datos), $this->servicio()), ValidationException::class);
        $this->assertSame(2, Compra::count());
        $this->assertSame(1, MovimientoInventarioCompra::count());
    }

    public function test_store_rechaza_inventario_inactivo_antes_de_delegar(): void
    {
        DB::table('inventarios_compra')->where('id', 7)->update(['estado' => 0]);
        $this->saldosIniciales = $this->snapshotInventarios();
        $servicio = Mockery::mock(CompraInventarioService::class);
        $servicio->shouldNotReceive('registrarCompra');
        $this->rechaza(fn () => (new CompraController)->store(Request::create('/', 'POST', $this->datosCompra([$this->linea(7)])), $servicio), ValidationException::class);
        $this->sinComprasParciales();
    }

}
