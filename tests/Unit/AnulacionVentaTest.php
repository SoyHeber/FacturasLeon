<?php

namespace Tests\Unit;

use App\Data\RespuestaAinnova;
use App\Http\Middleware\VerificarPermiso;
use App\Models\AnulacionVenta;
use App\Models\DocumentoFel;
use App\Models\IntentoAnulacionVenta;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use App\Services\AinnovaFelClient;
use App\Services\AnulacionVentaService;
use DOMDocument;
use DOMXPath;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FelTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';
require_once dirname(__DIR__).'/Support/FelTestCase.php';

class AnulacionVentaTest extends FelTestCase
{
    protected function setUp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'America/Guatemala'));
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_borrador_se_cancela_sin_stock_ni_llamadas_y_conserva_el_documento_no_emitido(): void
    {
        $venta = $this->crearVenta();
        $cliente = $this->clienteSinEnvio();
        $original = $venta->documentoFel->getAttributes();
        $anulacion = $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame('LOCAL', $anulacion->tipo);
        $this->assertSame('ANULADA', $venta->fresh()->estado_venta);
        $this->assertSame($original, $venta->fresh()->documentoFel->getAttributes());
        $this->assertSame(0, MovimientoInventario::count());
        $this->assertSame(0, IntentoAnulacionVenta::count());
        $this->assertStock('5');
        $html = $this->controller->show($venta->fresh())->render();
        $this->assertStringContainsString('Cancelada sin emisión', $html);
        $this->assertStringNotContainsString('Solicitar anulación FEL', $html);
    }

    public function test_confirmada_sin_envio_reintegra_solo_una_vez_y_preserva_xml_y_salidas(): void
    {
        $venta = $this->ventaConfirmada();
        $congelados = $this->congelados($venta);
        $salida = MovimientoInventario::sole()->getAttributes();
        $servicio = $this->anulador($this->clienteSinEnvio());
        $anulacion = $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($salida, MovimientoInventario::findOrFail($salida['id'])->getAttributes());
        $this->assertSame('PENDIENTE', $venta->fresh()->documentoFel->estado_fel);
        $servicio->aplicarConfirmada($venta->id, 7);
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertSame(1, MovimientoInventario::where('tipo_movimiento', 'ENTRADA')->count());
        $this->assertStock('5');
    }

    public function test_rechazo_definitivo_de_emision_permite_cancelar_sin_una_anulacion_remota(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'ERROR: NIT inválido'));
        $this->assertSame('ERROR', $this->servicio($cliente)->certificarVenta($venta->id, 7)->estado_fel);
        $anulacion = $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('LOCAL', $anulacion->tipo);
        $this->assertSame('ERROR', $venta->fresh()->documentoFel->estado_fel);
        $this->assertStock('5');
    }

    #[DataProvider('estadosEmisionNoCancelables')]
    public function test_emision_activa_incierta_o_con_historial_incompatible_bloquea_cancelacion_local(string $estado): void
    {
        $venta = $this->ventaConfirmada();
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => $estado]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(0, AnulacionVenta::count());
    }

    public static function estadosEmisionNoCancelables(): array
    {
        return [['EN_PROCESO'], ['INCIERTA'], ['ERROR'], ['ANULADA']];
    }

    public function test_error_de_emision_con_un_timeout_historico_no_acredita_rechazo_definitivo(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TIMEOUT));
        $this->servicio($cliente)->certificarVenta($venta->id, 7);
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => 'ERROR']);
        $this->rechaza(fn () => $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertStock('3');
    }

    public function test_certificada_envia_identidad_original_fuera_de_transaccion_y_aplica_acuse_guardado(): void
    {
        $venta = $this->certificada();
        $original = $venta->documentoFel;
        $congelados = $this->congelados($venta);
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->with('A001', '000123', '1234567K', '20261030', 'Error de captura')
            ->andReturnUsing(function () use ($venta) {
                $this->assertSame(0, DB::connection()->transactionLevel());
                $this->assertSame('EN_PROCESO', AnulacionVenta::sole()->estado);
                $this->assertSame('EN_PROCESO', IntentoAnulacionVenta::sole()->resultado);
                $this->assertSame('CERTIFICADA', $venta->fresh()->documentoFel->estado_fel);
                $this->assertStock('3');

                return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse());
            });
        $servicio = $this->anulador($cliente);
        $anulacion = $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame('RESPUESTA', $anulacion->origen_confirmacion);
        $this->assertSame(7, $anulacion->confirmado_por_id);
        $this->assertSame(hash('sha256', $this->acuse()), $anulacion->confirmacion_hash);
        $this->assertSame('CONFIRMADA', IntentoAnulacionVenta::sole()->resultado);
        $this->assertSame('ANULADA', $venta->fresh()->estado_venta);
        $this->assertSame('ANULADA', $venta->fresh()->documentoFel->estado_fel);
        $this->assertSame($original->xml_certificado, $venta->fresh()->documentoFel->xml_certificado);
        $this->assertSame($congelados, $this->congelados($venta));
        $servicio->aplicarConfirmada($venta->id, 7);
        $this->assertStock('5');
        $this->assertSame(2, MovimientoInventario::count());
        $html = $this->controller->show($venta->fresh())->render();
        $this->assertStringContainsString($original->fel_uuid, $html);
        $this->assertStringContainsString('Anulación confirmada por Ainnova y reintegro aplicado.', $html);
        $this->assertStringContainsString(route('ventas.consultar_representacion', [$venta, 'tipo' => 'pdf']), $html);
        $this->assertStringContainsString(route('ventas.consultar_representacion', [$venta, 'tipo' => 'xml']), $html);
    }

    #[DataProvider('respuestasSinReintegro')]
    public function test_solo_una_confirmacion_positiva_permite_reintegrar(RespuestaAinnova $respuesta, string $estado): void
    {
        $venta = $this->certificada();
        $antes = $this->snapshot();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn($respuesta);
        $servicio = $this->anulador($cliente);
        $anulacion = $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame($estado, $anulacion->estado);
        $this->assertSame($estado, IntentoAnulacionVenta::sole()->resultado);
        $this->assertSame($antes, $this->snapshot());
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertSame(1, IntentoAnulacionVenta::count());
    }

    public static function respuestasSinReintegro(): array
    {
        return [
            [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, '<RESULTADO>ERROR en los datos enviados, campo FECHA_ANULACION</RESULTADO>'), 'RECHAZADA'],
            [new RespuestaAinnova(RespuestaAinnova::TIMEOUT), 'INCIERTA'],
            [new RespuestaAinnova(RespuestaAinnova::TRANSPORTE), 'INCIERTA'],
            [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'ERROR 94 DOCUMENTO ANULADO PREVIAMENTE'), 'INCIERTA'],
            [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, '<RESULTADO><ESTADO>ANULADO</ESTADO></RESULTADO>'), 'INCIERTA'],
        ];
    }

    public function test_rechazo_admite_otro_intento_explicito_sin_sobrescribir_la_solicitud_anterior(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'ERROR: fecha inválida'));
        $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion());
        $primero = IntentoAnulacionVenta::sole();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->with('A001', '000123', '1234567K', '20261030', 'Motivo corregido')
            ->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion(['motivo' => 'Motivo corregido', 'ultimo_intento_anulacion_id' => $primero->id]));
        $this->assertSame($primero->getAttributes(), $primero->fresh()->getAttributes());
        $this->assertSame('Error de captura', $primero->fresh()->solicitud['motivo']);
        $this->assertSame(2, IntentoAnulacionVenta::count());
        $this->assertStock('5');
    }

    public function test_doble_clic_y_bloqueo_compartido_con_emision_impiden_segundo_envio(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $servicio = $this->anulador($cliente);
        $cliente->shouldReceive('anularDocumento')->once()->andReturnUsing(function () use ($servicio, $venta) {
            $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse());
        });
        $lock = Cache::lock('fel-documento-'.$venta->documentoFel->id, 600);
        $this->assertTrue($lock->get());
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $lock->release();
        $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame(1, IntentoAnulacionVenta::count());
        $this->assertStock('5');
    }

    public function test_fallo_local_despues_del_acuse_revierte_stock_y_se_recupera_sin_reenviar(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        $fallar = true;
        AnulacionVenta::updating(function ($anulacion) use (&$fallar) {
            if ($fallar && $anulacion->estado === 'APLICADA') {
                throw new RuntimeException('Fallo simulado al finalizar');
            }
        });
        $servicio = $this->anulador($cliente);
        $anulacion = $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('CONFIRMADA', $anulacion->estado);
        $this->assertSame($this->acuse(), $anulacion->confirmacion_raw);
        $this->assertNotNull($anulacion->ultimo_error);
        $this->assertSame('CONFIRMADA', IntentoAnulacionVenta::sole()->resultado);
        $this->assertSame('CERTIFICADA', $venta->fresh()->documentoFel->estado_fel);
        $this->assertSame('CONFIRMADA', $venta->fresh()->estado_venta);
        $this->assertStock('3');
        $this->assertSame(1, MovimientoInventario::count());
        $html = $this->controller->show($venta->fresh())->render();
        $this->assertStringContainsString('Aplicar reintegro pendiente', $html);
        $this->assertStringNotContainsString('Solicitar anulación FEL', $html);
        $fallar = false;
        $this->assertSame('APLICADA', $servicio->aplicarConfirmada($venta->id, 7)->estado);
        $servicio->aplicarConfirmada($venta->id, 7);
        $this->assertStock('5');
        $this->assertSame(2, MovimientoInventario::count());
    }

    public function test_fallo_al_reservar_no_llama_y_fallo_al_guardar_acuse_deja_envio_abierto_sin_stock(): void
    {
        $venta = $this->certificada();
        $fallarReserva = true;
        IntentoAnulacionVenta::creating(function () use (&$fallarReserva) {
            if ($fallarReserva) {
                throw new RuntimeException('Reserva no persistida');
            }
        });
        $this->rechaza(fn () => $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion()), RuntimeException::class);
        $this->assertSame(0, AnulacionVenta::count());
        $fallarReserva = false;
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        IntentoAnulacionVenta::updating(fn () => throw new RuntimeException('Acuse no persistido'));
        $this->rechaza(fn () => $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion()), RuntimeException::class);
        $this->assertSame('EN_PROCESO', AnulacionVenta::sole()->estado);
        $this->assertSame('EN_PROCESO', IntentoAnulacionVenta::sole()->resultado);
        $this->assertNull(IntentoAnulacionVenta::sole()->fecha_fin);
        $this->assertStock('3');
    }

    public function test_deadlocks_reintentan_solo_la_base_y_envian_una_sola_solicitud_soap(): void
    {
        $venta = $this->certificada();
        $reservas = 0;
        $guardados = 0;
        IntentoAnulacionVenta::created(function () use (&$reservas) {
            if (++$reservas === 1) {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        });
        IntentoAnulacionVenta::updating(function () use (&$guardados) {
            if (++$guardados < 3) {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        });
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        $this->assertSame('APLICADA', $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion())->estado);
        $this->assertSame(2, $reservas);
        $this->assertSame(3, $guardados);
        $this->assertSame(1, IntentoAnulacionVenta::count());
        $this->assertStock('5');
    }

    public function test_modelos_y_rollback_preservan_la_auditoria_y_sus_usuarios(): void
    {
        DB::table('users')->insert(['id' => 15, 'name' => 'Otro operador', 'email' => 'operador@example.test', 'password' => 'sin-uso']);
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        $anulacion = $this->anulador($cliente)->anular($venta->id, 15, $this->datosAnulacion());
        $this->rechaza(fn () => $anulacion->delete(), ValidationException::class);
        $this->rechaza(fn () => IntentoAnulacionVenta::sole()->delete(), ValidationException::class);
        $this->rechaza(fn () => DB::table('users')->where('id', 15)->delete(), \Illuminate\Database\QueryException::class);
        $this->rechaza(fn () => (require dirname(__DIR__, 2).'/database/migrations/2026_10_30_000001_create_anulaciones_venta_table.php')->down(), RuntimeException::class);
        $this->assertSame(1, AnulacionVenta::count());
        $this->assertSame(1, IntentoAnulacionVenta::count());
        $this->assertSame(15, $anulacion->aplicado_por_id);
    }

    public function test_migracion_genera_restricciones_mysql_sin_conectarse_a_mysql(): void
    {
        $mysql = new \Illuminate\Database\MySqlConnection(fn () => throw new RuntimeException('Conexión real prohibida.'), 'aislada');
        $mysql->setSchemaGrammar(new \Illuminate\Database\Schema\Grammars\MySqlGrammar);
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        \Illuminate\Support\Facades\Schema::swap($mysql->getSchemaBuilder());
        try {
            $queries = $mysql->pretend(function () {
                (require dirname(__DIR__, 2).'/database/migrations/2026_10_30_000001_create_anulaciones_venta_table.php')->up();
            });
        } finally {
            \Illuminate\Support\Facades\Schema::swap($schema);
        }
        $sql = implode("\n", array_column($queries, 'query'));
        $this->assertStringContainsString('add unique `anulaciones_venta_venta_id_unique`(`venta_id`)', $sql);
        $this->assertStringContainsString('add unique `anulaciones_venta_documento_fel_id_unique`(`documento_fel_id`)', $sql);
        $this->assertStringContainsString('references `users` (`id`) on delete restrict', $sql);
        $this->assertStringContainsString('references `anulaciones_venta` (`id`) on delete restrict', $sql);
        $this->assertStringContainsString('`solicitud` json not null', $sql);
    }

    public function test_evidencia_manual_exige_procedencia_referencia_y_coincidencia_y_no_reenvia(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TIMEOUT));
        $servicio = $this->anulador($cliente);
        $servicio->anular($venta->id, 7, $this->datosAnulacion());
        $datos = $this->datosEvidencia();
        foreach ([['confirmacion_ainnova' => false], ['referencia_soporte' => ''],
            ['respuesta_ainnova' => str_replace('000123', '000124', $this->acuse())],
            ['respuesta_ainnova' => 'ERROR 94 DOCUMENTO ANULADO PREVIAMENTE'],
            ['ultimo_intento_anulacion_id' => 0]] as $cambio) {
            $this->rechaza(fn () => $servicio->conciliarManual($venta->id, 7, array_replace($datos, $cambio)), ValidationException::class);
            $this->assertSame('INCIERTA', AnulacionVenta::sole()->estado);
            $this->assertStock('3');
        }
        $intento = IntentoAnulacionVenta::sole()->getAttributes();
        $anulacion = $servicio->conciliarManual($venta->id, 7, $datos);
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame('MANUAL', $anulacion->origen_confirmacion);
        $this->assertSame(7, $anulacion->confirmado_por_id);
        $this->assertSame('Soporte Ainnova #123', $anulacion->referencia_soporte);
        $this->assertSame($intento, IntentoAnulacionVenta::sole()->getAttributes());
        $this->rechaza(fn () => $servicio->conciliarManual($venta->id, 7, $datos), ValidationException::class);
        $this->assertStock('5');
        $this->assertSame(1, IntentoAnulacionVenta::count());
    }

    public function test_envio_abandonado_solo_admite_evidencia_despues_del_plazo_sin_reintento_automatico(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TIMEOUT));
        $servicio = $this->anulador($cliente);
        $servicio->anular($venta->id, 7, $this->datosAnulacion());
        DB::table('anulaciones_venta')->update(['estado' => 'EN_PROCESO']);
        DB::table('intentos_anulacion_venta')->update(['resultado' => 'EN_PROCESO', 'fecha_fin' => null]);
        $this->rechaza(fn () => $servicio->conciliarManual($venta->id, 7, $this->datosEvidencia()), ValidationException::class);
        Carbon::setTestNow(now()->addMinutes(DocumentoFel::minutosIntentoExpirado() + 1));
        $this->assertSame('APLICADA', $servicio->conciliarManual($venta->id, 7, $this->datosEvidencia())->estado);
        $this->assertSame('INCIERTA', IntentoAnulacionVenta::sole()->resultado);
        $this->assertNotNull(IntentoAnulacionVenta::sole()->fecha_fin);
        $this->assertStock('5');
    }

    public function test_reintegro_por_linea_usa_inventario_original_aunque_este_inactivo(): void
    {
        $venta = $this->crearVenta(['detalles' => [$this->linea(), $this->linea(['cantidad' => '1'])]]);
        $this->confirmacion->confirmarVenta($venta->id, 7);
        DB::table('inventarios')->update(['estado' => false]);
        $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertStock('5');
        $this->assertSame(0, (int) DB::table('inventarios')->value('estado'));
        $this->assertSame(2, MovimientoInventario::where('tipo_movimiento', 'ENTRADA')->count());
        foreach ($venta->detalles as $detalle) {
            $movimientos = MovimientoInventario::where('detalle_venta_id', $detalle->id)->get();
            $this->assertSame(2, $movimientos->count());
            $this->assertSame(1, $movimientos->pluck('inventario_id')->unique()->count());
        }
    }

    public function test_historial_de_stock_inconsistente_y_limite_de_entero_impiden_enviar(): void
    {
        $venta = $this->certificada();
        DB::table('movimientos_inventario')->update(['cantidad' => '1']);
        $servicio = $this->anulador($this->clienteSinEnvio());
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        DB::table('movimientos_inventario')->update(['cantidad' => '2']);
        DB::table('inventarios')->update(['cantidad' => '18446744073709551615']);
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertSame(0, AnulacionVenta::count());
    }

    public function test_emisor_incompatible_identidad_incompleta_y_transaccion_externa_se_rechazan(): void
    {
        $venta = $this->certificada();
        $servicio = $this->anulador($this->clienteSinEnvio());
        DB::transaction(fn () => $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class));
        DB::table('documentos_fel')->update(['nit_emisor' => '9999999K']);
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        DB::table('documentos_fel')->update(['nit_emisor' => '1234567K', 'fel_uuid' => null]);
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        DB::table('documentos_fel')->update(['fel_uuid' => $venta->documentoFel->fel_uuid, 'hash_xml' => str_repeat('0', 64)]);
        $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        $this->assertSame(0, IntentoAnulacionVenta::count());
    }

    public function test_identidad_incongruente_con_xml_o_rastros_de_certificacion_impiden_cancelar(): void
    {
        $venta = $this->certificada();
        $servicio = $this->anulador($this->clienteSinEnvio());
        foreach ([['fel_serie' => 'OTRA'], ['fel_numero' => '999999'],
            ['fel_serie' => 'A001', 'fel_numero' => '000123', 'xml_certificado' => '<invalido/>'],
            ['estado_fel' => 'PENDIENTE', 'fel_uuid' => null, 'xml_certificado' => null]] as $cambio) {
            DB::table('documentos_fel')->update($cambio);
            $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion()), ValidationException::class);
        }
        $this->assertSame(0, AnulacionVenta::count());
        $this->assertStock('3');
    }

    public function test_fecha_motivo_y_formulario_son_obligatorios_y_no_se_admiten_fechas_fuera_de_venta(): void
    {
        $venta = $this->ventaConfirmada();
        $servicio = $this->anulador($this->clienteSinEnvio());
        foreach ([['fecha_anulacion' => '2026-10-02'], ['fecha_anulacion' => '2026-10-31'],
            ['fecha_anulacion' => '2026-02-30'], ['motivo' => ' '], ['motivo' => str_repeat('x', 201)],
            ['ultimo_intento_anulacion_id' => -1]] as $cambio) {
            $this->rechaza(fn () => $servicio->anular($venta->id, 7, $this->datosAnulacion($cambio)), ValidationException::class);
        }
        $this->assertSame(0, AnulacionVenta::count());
        $this->assertStock('3');
    }

    public function test_anulacion_fel_acepta_la_misma_fecha_del_documento(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->with('A001', '000123', '1234567K', '20261003', 'Error de captura')
            ->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->acuse()));
        $anulacion = $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion(['fecha_anulacion' => '2026-10-03']));
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame('2026-10-03', $anulacion->fecha_anulacion->toDateString());
        $this->assertStock('5');
    }

    public function test_anulacion_fel_fuera_del_mes_se_rechaza_antes_de_reservar_o_llamar(): void
    {
        $venta = $this->certificada();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'America/Guatemala'));
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7,
            $this->datosAnulacion(['fecha_anulacion' => '2026-11-01'])), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(0, AnulacionVenta::count());
        $this->assertSame(0, IntentoAnulacionVenta::count());
    }

    #[DataProvider('fechasFueraDelMes')]
    public function test_fecha_posterior_en_otro_mes_o_ano_no_modifica_venta_ni_inventario(string $fechaDocumento, string $fechaAnulacion): void
    {
        $venta = $this->crearVenta(['fecha' => $fechaDocumento]);
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $antes = $this->snapshot();
        try {
            $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion(['fecha_anulacion' => $fechaAnulacion]));
            $this->fail('La fecha fuera del mes del documento debe rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('mismo mes y año', $exception->errors()['anulacion'][0]);
        }
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(0, AnulacionVenta::count());
        $this->assertSame(0, IntentoAnulacionVenta::count());
    }

    public static function fechasFueraDelMes(): array
    {
        return [
            'mes siguiente' => ['2026-09-30', '2026-10-01'],
            'mismo mes de otro año' => ['2025-10-03', '2026-10-03'],
            'cambio de año' => ['2025-12-31', '2026-01-01'],
            'mes posterior a febrero bisiesto' => ['2024-02-29', '2024-03-01'],
        ];
    }

    #[DataProvider('cierresDeMes')]
    public function test_ultimo_dia_del_mes_del_documento_se_admite(string $fechaDocumento, string $fechaAnulacion): void
    {
        $venta = $this->crearVenta(['fecha' => $fechaDocumento]);
        $this->confirmacion->confirmarVenta($venta->id, 7);
        $anulacion = $this->anulador($this->clienteSinEnvio())->anular($venta->id, 7, $this->datosAnulacion(['fecha_anulacion' => $fechaAnulacion]));
        $this->assertSame('APLICADA', $anulacion->estado);
        $this->assertSame($fechaAnulacion, $anulacion->fecha_anulacion->toDateString());
        $this->assertStock('5');
    }

    public static function cierresDeMes(): array
    {
        return [['2026-09-03', '2026-09-30'], ['2024-02-01', '2024-02-29'], ['2025-12-01', '2025-12-31']];
    }

    #[DataProvider('limitesDelFormulario')]
    public function test_formulario_limita_fecha_y_las_observaciones_se_muestran_en_un_desplegable_cerrado(string $fechaDocumento, string $fechaMaxima): void
    {
        $venta = $this->crearVenta(['fecha' => $fechaDocumento]);
        $html = $this->controller->show($venta)->render();
        $this->assertStringContainsString('La fecha de anulación debe ser igual o posterior a la fecha del documento, dentro del mismo mes y año, y no puede ser futura.', $html);
        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($dom->loadHTML($html, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xpath = new DOMXPath($dom);
        $observaciones = $xpath->query('//details[summary[normalize-space(.)="OBSERVACIONES:"]]');
        $this->assertSame(1, $observaciones->length);
        $this->assertFalse($observaciones->item(0)->hasAttribute('open'));
        $this->assertSame(4, $xpath->query('./ul/li', $observaciones->item(0))->length);
        $fecha = $xpath->query('//input[@id="fecha_anulacion"]')->item(0);
        $this->assertNotNull($fecha);
        $this->assertSame($fechaDocumento, $fecha->getAttribute('min'));
        $this->assertSame($fechaMaxima, $fecha->getAttribute('max'));
        $this->assertSame($fechaMaxima, $fecha->getAttribute('value'));
        $this->assertSame($fechaDocumento, $venta->fecha->toDateString());
    }

    public static function limitesDelFormulario(): array
    {
        return [
            'mes actual limitado por hoy' => ['2026-10-03', '2026-10-30'],
            'mes anterior limitado por su cierre' => ['2026-09-03', '2026-09-30'],
            'febrero bisiesto' => ['2024-02-01', '2024-02-29'],
        ];
    }

    public function test_respuesta_con_secretos_no_se_acepta_ni_se_almacena_sin_ocultar(): void
    {
        $venta = $this->certificada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('anularDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA,
            str_replace('</RESULTADO>', '<pPassword>'.$this->configuracionFel['ws_password'].'</pPassword></RESULTADO>', $this->acuse())));
        $anulacion = $this->anulador($cliente)->anular($venta->id, 7, $this->datosAnulacion());
        $this->assertSame('INCIERTA', $anulacion->estado);
        $this->assertStringNotContainsString($this->configuracionFel['ws_password'], IntentoAnulacionVenta::sole()->respuesta_raw);
        $this->assertStock('3');
    }

    public function test_controller_retira_xml_de_input_incluso_si_la_validacion_falla(): void
    {
        $venta = $this->certificada();
        $request = $this->request(['respuesta_ainnova' => 'XML sensible sin referencia']);
        $request->query->set('respuesta_ainnova', 'XML sensible en query');
        $this->rechaza(fn () => $this->controller->conciliarAnulacion($request, $venta, $this->anulador($this->clienteSinEnvio())), ValidationException::class);
        $this->assertFalse($request->has('respuesta_ainnova'));
    }

    public function test_rutas_requieren_eliminar_y_los_formularios_se_ocultan_sin_ese_permiso(): void
    {
        $venta = $this->ventaConfirmada();
        foreach (['anular', 'aplicar_anulacion', 'conciliar_anulacion'] as $accion) {
            $ruta = $this->router->getRoutes()->getByName('ventas.'.$accion);
            $this->assertNotNull($ruta);
            $this->assertContains('auth', $ruta->gatherMiddleware());
            $this->assertContains('permiso', $ruta->gatherMiddleware());
            $this->assertSame('eliminar', VerificarPermiso::ACCIONES_POR_METODO[$accion]);
        }
        $this->assertStringContainsString('Cancelar venta', $this->controller->show($venta)->render());
        $opcion = DB::table('opciones')->where('ruta', 'ventas')->value('id');
        $accion = DB::table('acciones')->where('clave', 'eliminar')->value('id');
        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion)->where('accion_id', $accion)->delete();
        $this->assertStringNotContainsString('Cancelar venta', $this->controller->show($venta)->render());
        $request = $this->request([]);
        $request->setRouteResolver(fn () => $this->router->getRoutes()->getByName('ventas.anular'));
        Container::setInstance(new class extends Container
        {
            public function abort($code, $message = '', array $headers = []): never
            {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
            }
        });
        $this->rechaza(fn () => (new VerificarPermiso)->handle($request, fn () => $this->fail('No debe permitir anular.')), \Symfony\Component\HttpKernel\Exception\HttpException::class);
    }

    private function certificada(): Venta
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        $this->servicio($cliente)->certificarVenta($venta->id, 7);

        return $venta->fresh();
    }

    private function anulador(AinnovaFelClient $cliente): AnulacionVentaService
    {
        $cliente->shouldNotReceive('generarDocumento');

        return new AnulacionVentaService($cliente);
    }

    private function clienteSinEnvio(): AinnovaFelClient
    {
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('anularDocumento');

        return $cliente;
    }

    private function datosAnulacion(array $cambios = []): array
    {
        return array_replace(['fecha_anulacion' => '2026-10-30', 'motivo' => 'Error de captura', 'ultimo_intento_anulacion_id' => 0], $cambios);
    }

    private function datosEvidencia(): array
    {
        return ['ultimo_intento_anulacion_id' => IntentoAnulacionVenta::latest('id')->value('id'),
            'respuesta_ainnova' => $this->acuse(), 'referencia_soporte' => 'Soporte Ainnova #123', 'confirmacion_ainnova' => true];
    }

    private function acuse(): string
    {
        return '<RESULTADO><ESTADO>ANULADO</ESTADO><EMISOR>1234567K</EMISOR><COMPRADOR>1234567K</COMPRADOR><SERIE>A001</SERIE><PREIMPRESO>000123</PREIMPRESO></RESULTADO>';
    }

    private function assertStock(string $cantidad): void
    {
        $this->assertSame($cantidad, (string) DB::table('inventarios')->value('cantidad'));
    }
}
