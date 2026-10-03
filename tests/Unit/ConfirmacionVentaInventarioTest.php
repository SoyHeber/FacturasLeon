<?php

namespace Tests\Unit;

use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Services\CalculadoraVentaService;
use App\Services\FelXmlBuilderService;
use App\Services\InventarioService;
use App\Services\VentaInventarioService;
use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\VentasInventarioTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';

class ConfirmacionVentaInventarioTest extends VentasInventarioTestCase
{
    public function test_confirma_una_venta_y_congela_el_unico_documento_sin_intentos(): void
    {
        $venta = $this->crearVenta();
        $referencia = $venta->documentoFel->referencia;
        $documentoId = $venta->documentoFel->id;
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $this->assertSame('CONFIRMADA', $confirmada->estado_venta);
        $this->assertSame(7, $confirmada->usuario_modificador_id);
        $this->assertSame('3', Inventario::sole()->cantidad);
        $salida = MovimientoInventario::sole();
        $this->assertSame('SALIDA', $salida->tipo_movimiento);
        $this->assertSame('2', $salida->cantidad);
        $this->assertSame($confirmada->detalles->sole()->id, $salida->detalle_venta_id);
        $this->assertNull($salida->produccion_id);
        $this->assertTrue($salida->estado);
        $this->assertTrue($salida->esAutomatico());
        $this->assertSame($venta->id, $salida->detalleVenta->venta->id);
        $this->assertSame(1, $confirmada->detalles->sole()->movimientosInventario->count());
        $documento = $confirmada->documentoFel;
        $this->assertSame($documentoId, $documento->id);
        $this->assertSame($referencia, $documento->referencia);
        $this->assertSame('PENDIENTE', $documento->estado_fel);
        $this->assertSame(1, $documento->p_tipo_doc);
        $this->assertSame('D', $documento->p_tipo_respuesta);
        $this->assertSame(hash('sha256', $documento->xml_solicitud), $documento->hash_xml);
        $this->assertNotNull($documento->fecha_hora_emision);
        $this->assertNull($documento->xml_certificado);
        $this->assertSame(1, DB::table('documentos_fel')->count());
        $this->assertSame(0, DB::table('intentos_fel')->count());
    }

    public function test_varias_lineas_descuentan_cada_inventario_y_crean_una_salida_por_detalle(): void
    {
        DB::table('inventarios')->insert(['id' => 42, 'producto_id' => 32, 'cantidad' => 9]);
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['producto_id' => 32, 'cantidad' => '4'])]]);
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $this->assertSame('3', Inventario::where('producto_id', 31)->sole()->cantidad);
        $this->assertSame('5', Inventario::where('producto_id', 32)->sole()->cantidad);
        $this->assertSame(2, MovimientoInventario::count());
        foreach ($confirmada->detalles as $detalle) {
            $this->assertSame(1, $detalle->movimientosInventario()->where('tipo_movimiento', 'SALIDA')->count());
            $this->assertSame($detalle->cantidad, $detalle->movimientosInventario->sole()->cantidad);
        }
    }

    public function test_mismo_producto_se_agrupa_y_stock_exacto_llega_a_cero(): void
    {
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['cantidad' => '3'])]]);
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $this->assertSame('0', Inventario::sole()->cantidad);
        $this->assertSame(['2', '3'], MovimientoInventario::orderBy('id')->pluck('cantidad')->map(fn ($valor) => (string) $valor)->all());
        $this->assertSame(2, MovimientoInventario::distinct()->count('detalle_venta_id'));
    }

    public function test_stock_agrupado_insuficiente_rechaza_antes_de_crear_movimientos(): void
    {
        $venta = $this->crearVenta(['detalles' => [$this->linea(['cantidad' => '3']), $this->linea(['cantidad' => '3'])]]);
        $antes = $this->snapshot();
        $creaciones = 0;
        MovimientoInventario::creating(function () use (&$creaciones) {
            $creaciones++;
        });
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame(0, $creaciones);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_valida_todos_los_saldos_antes_de_crear_la_primera_salida(): void
    {
        DB::table('inventarios')->insert(['id' => 42, 'producto_id' => 32, 'cantidad' => 1]);
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['producto_id' => 32])]]);
        $creaciones = 0;
        MovimientoInventario::creating(function () use (&$creaciones) {
            $creaciones++;
        });
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame(0, $creaciones);
        $this->assertSame($antes, $this->snapshot());
    }

    /** @dataProvider inventariosInvalidos */
    public function test_inventario_inexistente_o_inactivo_revierte_todo(string $caso): void
    {
        $venta = $this->crearVenta();
        if ($caso === 'inexistente') {
            DB::table('inventarios')->delete();
        } else {
            DB::table('inventarios')->update(['estado' => false]);
        }
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function inventariosInvalidos(): array
    {
        return [['inexistente'], ['inactivo']];
    }

    public function test_venta_sin_detalles_no_se_confirma(): void
    {
        $venta = $this->crearVenta();
        $venta->detalles()->delete();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    /** @dataProvider cantidadesInvalidas */
    public function test_revalida_cantidad_persistida_positiva_y_entera(string $cantidad): void
    {
        $venta = $this->crearVenta();
        DB::table('detalles_ventas')->where('venta_id', $venta->id)->update(['cantidad' => $cantidad]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function cantidadesInvalidas(): array
    {
        return [['0'], ['-1'], ['1.5']];
    }

    public function test_recalcula_importes_corruptos_desde_los_inputs_persistidos(): void
    {
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(array_fill_keys(CalculadoraVentaService::IMPORTES, '999.99'));
        DB::table('detalles_ventas')->where('venta_id', $venta->id)->update(array_fill_keys(CalculadoraVentaService::IMPORTES, '999.99'));
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        foreach (['importe_bruto' => '224.00', 'importe_descuento' => '22.40', 'importe_exento' => '0.00', 'importe_neto' => '180.00', 'importe_iva' => '21.60', 'importe_total' => '201.60'] as $campo => $valor) {
            $this->assertSame($valor, $confirmada->$campo);
            $this->assertSame($valor, $confirmada->detalles->sole()->$campo);
        }
    }

    public function test_fallo_de_xml_revierte_stock_salidas_calculos_y_documento(): void
    {
        $builder = new class extends FelXmlBuilderService
        {
            public function generarFactura(\App\Models\Venta $venta, \App\Models\DocumentoFel $documento): string
            {
                throw new RuntimeException('Fallo de XML simulado');
            }
        };
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['cantidad' => '1'])]]);
        DB::table('detalles_ventas')->where('venta_id', $venta->id)->update(['importe_total' => '888.88']);
        $antes = $this->snapshot();
        $servicio = new VentaInventarioService(xmlBuilder: $builder);
        $this->rechaza(fn () => $servicio->confirmarVenta($venta->id, 7), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_fallo_al_finalizar_venta_revierte_incluso_xml_y_hash(): void
    {
        $venta = $this->crearVenta();
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 999), QueryException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_segunda_confirmacion_y_venta_anulada_se_rechazan(): void
    {
        $venta = $this->crearVenta();
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $otra = $this->crearVenta();
        DB::table('ventas')->where('id', $otra->id)->update(['estado_venta' => 'ANULADA']);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($otra->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_deadlock_despues_de_una_salida_reintenta_sin_duplicar_stock_ni_movimientos(): void
    {
        $stock = new class extends InventarioService
        {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): Inventario
            {
                $inventario = parent::salida($inventarioId, $cantidad);
                if (++$this->llamadas === 1) {
                    throw new RuntimeException('Deadlock found when trying to get lock');
                }

                return $inventario;
            }
        };
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['cantidad' => '1'])]]);
        $referencia = $venta->documentoFel->referencia;
        $confirmada = (new VentaInventarioService(inventarioService: $stock))->confirmarVenta($venta->id, 7);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame('2', Inventario::sole()->cantidad);
        $this->assertSame(2, MovimientoInventario::count());
        $this->assertSame(2, MovimientoInventario::distinct()->count('detalle_venta_id'));
        $this->assertSame('CONFIRMADA', $confirmada->estado_venta);
        $this->assertSame($referencia, $confirmada->documentoFel->referencia);
        $this->assertSame(hash('sha256', $confirmada->documentoFel->xml_solicitud), $confirmada->documentoFel->hash_xml);
    }

    public function test_deadlock_persistente_intenta_tres_veces_y_revierte_todo(): void
    {
        $stock = new class extends InventarioService
        {
            public int $llamadas = 0;

            public function salida(int $inventarioId, mixed $cantidad): Inventario
            {
                parent::salida($inventarioId, $cantidad);
                $this->llamadas++;
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        };
        $venta = $this->crearVenta();
        $antes = $this->snapshot();
        $servicio = new VentaInventarioService(inventarioService: $stock);
        $this->rechaza(fn () => $servicio->confirmarVenta($venta->id, 7), RuntimeException::class);
        $this->assertSame(3, $stock->llamadas);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_bloquea_venta_detalles_documento_y_inventarios_distintos_en_orden(): void
    {
        DB::table('inventarios')->insert(['id' => 42, 'producto_id' => 32, 'cantidad' => 9]);
        $venta = $this->crearVenta(['detalles' => [$this->linea(['producto_id' => 32]), $this->linea(), $this->linea()]]);
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
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $this->assertSame(['ventas', 'detalles_ventas', 'documentos_fel'], array_slice(array_column($grammar->bloqueos, 0), 0, 3));
        $inventarios = array_values(array_filter($grammar->bloqueos, fn ($fila) => $fila[0] === 'inventarios'));
        $this->assertSame([[1], [42]], array_column($inventarios, 1));
        foreach ($grammar->bloqueos as $bloqueo) {
            $this->assertSame(1, $bloqueo[2]);
        }
    }

    public function test_movimientos_automaticos_de_venta_no_pueden_editarse_inactivarse_ni_desvincularse(): void
    {
        $venta = $this->crearVenta();
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $movimiento = MovimientoInventario::sole();
        $controller = new MovimientoInventarioController;
        $datos = ['inventario_id' => $movimiento->inventario_id, 'tipo_movimiento' => 'SALIDA', 'cantidad' => '1', 'estado' => 0, 'fecha_movimiento' => '2026-10-03', 'detalle_venta_id' => null, 'produccion_id' => null];
        $antes = $this->snapshot();
        // El controlador vuelve a leer el vínculo aunque se altere el modelo enlazado a la ruta.
        $movimiento->detalle_venta_id = null;
        $this->rechaza(fn () => $controller->edit($movimiento), ValidationException::class);
        $this->rechaza(fn () => $controller->update($this->request($datos, 'PUT'), $movimiento), ValidationException::class);
        $this->rechaza(fn () => $controller->cambiarEstado($movimiento), ValidationException::class);
        foreach (['motivo' => 'Alterado', 'estado' => false, 'detalle_venta_id' => null, 'produccion_id' => 9] as $campo => $valor) {
            $this->rechaza(fn () => $movimiento->fresh()->forceFill([$campo => $valor])->save(), ValidationException::class);
        }
        $this->rechaza(fn () => $movimiento->fresh()->delete(), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_crud_manual_no_puede_falsificar_vinculos_al_crear_ni_editar(): void
    {
        $venta = $this->crearVenta();
        $controller = new MovimientoInventarioController;
        $datos = ['inventario_id' => 1, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => '1', 'estado' => 1, 'fecha_movimiento' => '2026-10-03', 'detalle_venta_id' => $venta->detalles->sole()->id, 'produccion_id' => 999];
        $controller->store($this->request($datos));
        $manual = MovimientoInventario::sole();
        $this->assertNull($manual->detalle_venta_id);
        $this->assertNull($manual->produccion_id);
        $this->assertFalse($manual->esAutomatico());
        $controller->update($this->request(array_merge($datos, ['cantidad' => '2']), 'PUT'), $manual);
        $this->assertNull($manual->fresh()->detalle_venta_id);
        $this->assertNull($manual->fresh()->produccion_id);
        $this->assertSame('7', Inventario::sole()->cantidad);
        $this->rechaza(fn () => $manual->fresh()->update(['detalle_venta_id' => $venta->detalles->sole()->id]), ValidationException::class);
    }

    public function test_xml_hash_referencia_y_detalles_confirmados_son_inmutables(): void
    {
        $confirmada = $this->confirmacion->confirmarVenta($this->crearVenta()->id, 7);
        $antes = $this->snapshot();
        foreach (['referencia' => 'OTRA', 'xml_solicitud' => '<Otro/>', 'hash_xml' => str_repeat('0', 64)] as $campo => $valor) {
            $this->rechaza(fn () => $confirmada->documentoFel->fresh()->forceFill([$campo => $valor])->save(), ValidationException::class);
        }
        $detalle = $confirmada->detalles->sole();
        $this->rechaza(fn () => $detalle->update(['descripcion' => 'Alterada']), ValidationException::class);
        $this->rechaza(fn () => $detalle->fresh()->delete(), ValidationException::class);
        $this->rechaza(fn () => $this->controller->update($this->request($this->datos(), 'PUT'), $confirmada), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_constraints_permiten_salida_y_entrada_y_restringen_duplicados_y_vinculos(): void
    {
        $confirmada = $this->confirmacion->confirmarVenta($this->crearVenta()->id, 7);
        $salida = MovimientoInventario::sole();
        $fila = $salida->getAttributes();
        unset($fila['id']);
        $this->rechaza(fn () => DB::table('movimientos_inventario')->insert($fila), QueryException::class);
        $entrada = array_replace($fila, ['tipo_movimiento' => 'ENTRADA']);
        DB::table('movimientos_inventario')->insert($entrada);
        $this->rechaza(fn () => DB::table('movimientos_inventario')->insert($entrada), QueryException::class);
        foreach ([['tipo_movimiento' => 'AJUSTE'], ['estado' => 0], ['cantidad' => 0], ['cantidad' => -1], ['produccion_id' => 19], ['detalle_venta_id' => 999]] as $invalido) {
            $this->rechaza(fn () => DB::table('movimientos_inventario')->insert(array_replace($fila, $invalido)), QueryException::class);
        }
        $this->rechaza(fn () => DB::table('detalles_ventas')->where('id', $confirmada->detalles->sole()->id)->delete(), QueryException::class);
        $manual = array_replace($fila, ['detalle_venta_id' => null]);
        DB::table('movimientos_inventario')->insert([$manual, $manual]);
        DB::table('producciones')->insert(['id' => 19]);
        $produccion = array_replace($manual, ['produccion_id' => 19]);
        DB::table('movimientos_inventario')->insert($produccion);
        $this->rechaza(fn () => DB::table('movimientos_inventario')->insert($produccion), QueryException::class);
        $this->rechaza(fn () => DB::table('movimientos_inventario')->insert(array_replace($produccion, ['tipo_movimiento' => 'AJUSTE'])), QueryException::class);
        $this->rechaza(fn () => DB::table('producciones')->where('id', 19)->delete(), QueryException::class);
        $this->assertSame(['ENTRADA', 'SALIDA'], $confirmada->detalles->sole()->movimientosInventario()->orderBy('tipo_movimiento')->pluck('tipo_movimiento')->all());
    }

    public function test_ruta_confirmar_requiere_modificar_e_ignora_importes_y_estado_del_navegador(): void
    {
        $venta = $this->crearVenta();
        $ruta = $this->router->getRoutes()->getByName('ventas.confirmar');
        $this->assertSame(['PATCH'], $ruta->methods());
        $this->assertContains('permiso', $ruta->gatherMiddleware());
        $request = $this->request(['importe_total' => '0.01', 'estado_venta' => 'ANULADA', 'referencia' => 'FALSA'], 'PATCH');
        $request->setRouteResolver(fn () => $ruta);
        $response = (new VerificarPermiso)->handle($request, fn ($request) => $this->controller->confirmar($request, $venta));
        $this->assertSame('http://localhost/ventas/'.$venta->id, $response->getTargetUrl());
        $this->assertSame('CONFIRMADA', $venta->fresh()->estado_venta);
        $this->assertSame('201.60', $venta->fresh()->importe_total);
        $this->assertNotSame('FALSA', $venta->fresh()->documentoFel->referencia);
        $opcion = DB::table('opciones')->where('ruta', 'ventas')->value('id');
        $accion = DB::table('acciones')->where('clave', 'modificar')->value('id');
        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion)->where('accion_id', $accion)->delete();
        $request->setUserResolver(fn () => User::findOrFail(7));
        $app = new class extends Container
        {
            public function abort($code, $message = '', array $headers = []): never
            {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
            }
        };
        Container::setInstance($app);
        $this->rechaza(fn () => (new VerificarPermiso)->handle($request, fn () => null), \Symfony\Component\HttpKernel\Exception\HttpException::class);
    }

    public function test_vistas_de_borrador_confirmada_y_movimientos_automaticos(): void
    {
        $venta = $this->crearVenta();
        $this->assertStringContainsString('Confirmar Venta', $this->controller->show($venta)->render());
        $this->assertStringContainsString('Confirmar Venta', $this->controller->index($this->request([], 'GET'))->render());
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $html = $this->controller->show($confirmada)->render();
        $this->assertStringNotContainsString('Confirmar Venta', $html);
        $this->assertStringNotContainsString('>Editar</a>', $html);
        $this->assertStringContainsString('PENDIENTE', $html);
        $this->assertStringContainsString($confirmada->documentoFel->referencia, $html);
        $this->assertStringContainsString('Certificar FEL', $html);
        $controller = new MovimientoInventarioController;
        $html = $controller->show(MovimientoInventario::sole())->render();
        $this->assertStringContainsString('Automático - Venta', $html);
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertStringNotContainsString('Inactivar', $html);
        $html = $controller->index($this->request([], 'GET'))->render();
        $this->assertStringContainsString('Automático - Venta', $html);
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertStringNotContainsString('Inactivar', $html);
    }
}
