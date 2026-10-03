<?php

namespace Tests\Unit;

use App\Data\RespuestaAinnova;
use App\Http\Controllers\FelController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Support\FelTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';
require_once dirname(__DIR__).'/Support/FelTestCase.php';

class FelReintentoTest extends FelTestCase
{
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::spy(NullLogger::class);
        Log::swap($this->logger);
    }

    public function test_busca_certificacion_en_cualquier_respuesta_antes_de_reenviar_y_conserva_intentos(): void
    {
        [$venta, $primero] = $this->preparar(raw: $this->certificado());
        IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => 'INCIERTA', 'fecha_inicio' => now(), 'fecha_fin' => now(), 'respuesta_raw' => 'timeout']);
        $historia = $this->historia();
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        config(['fel.ainnova' => ['nit_emisor' => '1234567-K']]);
        $documento = $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame($historia, $this->historia());
        $this->assertSame($protegidas, $this->protegidas());
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame(2, IntentoFel::count());
        $this->logger->shouldHaveReceived('info')->once()->with('Conciliación local de respuesta FEL.', [
            'documento_fel_id' => $documento->id, 'intento_id' => $primero->id, 'usuario_id' => 7, 'estado_fel' => 'CERTIFICADA',
        ]);
    }

    public function test_reintento_envia_mismos_bytes_tipo_referencia_hash_y_registra_nuevo_intento(): void
    {
        [$venta, $primero] = $this->preparar();
        $anterior = $primero->getRawOriginal();
        $congelados = $this->congelados($venta);
        $protegidas = $this->protegidas();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->with($congelados['xml_solicitud'], 1, 'D')->andReturnUsing(function () use ($venta) {
            $this->assertSame(0, DB::connection()->transactionLevel());
            $this->assertSame('EN_PROCESO', $venta->documentoFel->fresh()->estado_fel);
            $nuevo = IntentoFel::latest('id')->firstOrFail();
            $this->assertSame('EN_PROCESO', $nuevo->resultado);
            $this->assertNull($nuevo->fecha_fin);
            $this->assertSame(7, $nuevo->usuario_id);

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado());
        });
        $documento = $this->servicio($cliente)->reintentarCertificacion($venta->documentoFel->id, 7, $primero->id);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(2, IntentoFel::count());
        $this->assertSame($anterior, $primero->fresh()->getRawOriginal());
        $this->assertSame('CERTIFICADA', $documento->ultimoIntento->resultado);
        $this->assertNotNull($documento->ultimoIntento->fecha_fin);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
    }

    /** @dataProvider respuestasRemotas */
    public function test_timeout_duda_o_rechazo_se_registran_sin_alterar_venta(RespuestaAinnova $respuesta, string $estado): void
    {
        [$venta, $primero] = $this->preparar();
        $congelados = $this->congelados($venta);
        $protegidas = $this->protegidas();
        $anterior = $primero->getRawOriginal();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn($respuesta);
        $documento = $this->servicio($cliente)->reintentarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame($estado, $documento->estado_fel);
        $this->assertSame($estado, $documento->ultimoIntento->resultado);
        $this->assertSame($anterior, $primero->fresh()->getRawOriginal());
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
    }

    public static function respuestasRemotas(): array
    {
        return [[new RespuestaAinnova(RespuestaAinnova::TIMEOUT), 'INCIERTA'],
            [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'Sin resultado concluyente'), 'INCIERTA'],
            [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'ERROR: Documento rechazado'), 'ERROR']];
    }

    /** @dataProvider estadosSinReintento */
    public function test_solo_incierta_se_puede_reintentar(string $estado): void
    {
        [$venta] = $this->preparar($estado);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosSinReintento(): array
    {
        return [['CERTIFICADA'], ['ERROR'], ['EN_PROCESO'], ['PENDIENTE'], ['ANULADA']];
    }

    public function test_hash_alterado_o_intento_abierto_impiden_reintento_sin_llamada(): void
    {
        [$venta, $primero] = $this->preparar();
        $hash = $venta->documentoFel->hash_xml;
        DB::table('documentos_fel')->where('id', $venta->documentoFel->id)->update(['hash_xml' => str_repeat('0', 64)]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        DB::table('documentos_fel')->where('id', $venta->documentoFel->id)->update(['hash_xml' => $hash]);
        $primero->update(['resultado' => 'EN_PROCESO', 'fecha_fin' => null]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_segundo_click_y_recuperacion_durante_llamada_no_envian_otra_vez(): void
    {
        [$venta] = $this->preparar();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturnUsing(function () use ($venta) {
            $otroServicio = $this->servicio($this->clienteLocal());
            $this->rechaza(fn () => $otroServicio->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
            IntentoFel::latest('id')->firstOrFail()->update(['fecha_inicio' => now()->subHours(2)]);
            $this->rechaza(fn () => $otroServicio->recuperarCertificacion($venta->documentoFel->id, 7), ValidationException::class);

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado());
        });
        $documento = $this->servicio($cliente)->reintentarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(2, IntentoFel::count());
    }

    public function test_doble_click_con_formulario_anterior_no_reenvia_aunque_primer_reintento_termine_en_timeout(): void
    {
        [$venta, $primero] = $this->preparar();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TIMEOUT));
        $servicio = $this->servicio($cliente);
        $servicio->reintentarCertificacion($venta->documentoFel->id, 7, $primero->id);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $servicio->reintentarCertificacion($venta->documentoFel->id, 7, $primero->id), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(2, IntentoFel::count());
    }

    public function test_en_proceso_reciente_no_recuperable_y_expirado_se_cierra_sin_envio(): void
    {
        [$venta, $intento] = $this->preparar('EN_PROCESO');
        $servicio = $this->servicio($this->clienteLocal());
        $antes = $this->snapshot();
        $this->assertFalse($venta->documentoFel->puedeRecuperarCertificacion());
        $this->rechaza(fn () => $servicio->recuperarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $intento->update(['fecha_inicio' => now()->subMinutes(DocumentoFel::minutosIntentoExpirado() + 1)]);
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $documento = $servicio->recuperarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $this->assertSame('INCIERTA', $intento->fresh()->resultado);
        $this->assertNotNull($intento->fresh()->fecha_fin);
        $this->assertSame(1, IntentoFel::count());
        $this->assertSame($protegidas, $this->protegidas());
        $this->assertSame($congelados, $this->congelados($venta));
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        $this->assertSame('CERTIFICADA', $this->servicio($cliente)->reintentarCertificacion($documento->id, 7)->estado_fel);
    }

    public function test_recuperacion_con_respuesta_valida_certifica_sin_reenvio_y_sin_cambiar_intentos_cerrados(): void
    {
        [$venta, $primero] = $this->preparar(raw: $this->certificado());
        $historia = $primero->getRawOriginal();
        $ultimo = IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => 'EN_PROCESO', 'fecha_inicio' => now()->subHours(1)]);
        $venta->documentoFel->forceFill(['estado_fel' => 'EN_PROCESO'])->save();
        $documento = $this->servicio($this->clienteLocal())->recuperarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame('CERTIFICADA', $ultimo->fresh()->resultado);
        $this->assertNotNull($ultimo->fresh()->fecha_fin);
        $this->assertSame($historia, $primero->fresh()->getRawOriginal());
        $this->assertSame(2, IntentoFel::count());
    }

    public function test_expiracion_configurable_respeta_timeout_y_limite_estricto(): void
    {
        [$venta, $intento] = $this->preparar('EN_PROCESO');
        config(['fel.ainnova.intento_expirado_minutos' => 10]);
        $intento->update(['fecha_inicio' => now()->subMinutes(9)]);
        $this->assertFalse($venta->documentoFel->intentoExpirado($intento));
        $intento->update(['fecha_inicio' => now()->subMinutes(11)]);
        $this->assertTrue($venta->documentoFel->intentoExpirado($intento));
        config(['fel.ainnova.intento_expirado_minutos' => 1, 'fel.ainnova.timeout' => 600, 'fel.ainnova.connect_timeout' => 600]);
        $this->assertGreaterThan(20, DocumentoFel::minutosIntentoExpirado());
    }

    public function test_fallo_reserva_revierte_nuevo_intento_y_no_llama_soap(): void
    {
        [$venta] = $this->preparar();
        $antes = $this->snapshot();
        DocumentoFel::updating(fn () => false);
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        $this->rechaza(fn () => $this->servicio($cliente)->reintentarCertificacion($venta->documentoFel->id, 7), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_credenciales_devuelta_por_soap_no_aparecen_en_bd_ni_diagnosticos(): void
    {
        [$venta] = $this->preparar();
        $cliente = $this->cliente();
        $texto = 'ERROR: '.$this->configuracionFel['ws_password'].' '.$this->configuracionFel['basic_password'];
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TEXTUAL, $texto, $texto));
        $documento = $this->servicio($cliente)->reintentarCertificacion($venta->documentoFel->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        foreach (['ws_password', 'basic_password'] as $campo) {
            $this->assertStringNotContainsString($this->configuracionFel[$campo], json_encode($this->snapshot()));
        }
        $this->logger->shouldNotHaveReceived('error');
        $this->logger->shouldNotHaveReceived('info');
    }

    public function test_ui_historial_y_acciones_exclusivas_por_estado_y_rutas_con_permiso(): void
    {
        [$venta, $intento] = $this->preparar();
        $html = $this->controller->show($venta->fresh())->render();
        foreach (['Conciliar respuesta', 'Reintentar certificación', 'misma referencia y XML', 'Intento 1', 'Inicio', 'Fin', 'Usuario'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        foreach (['Recuperar certificación', 'Ver PDF', 'Consultar XML', 'Certificar FEL'] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
        $intento->update(['resultado' => 'EN_PROCESO', 'fecha_fin' => null, 'fecha_inicio' => now()]);
        $venta->documentoFel->forceFill(['estado_fel' => 'EN_PROCESO'])->save();
        $html = $this->controller->show($venta->fresh())->render();
        $this->assertStringNotContainsString('Recuperar certificación', $html);
        $this->assertStringNotContainsString('Reintentar certificación', $html);
        $intento->update(['fecha_inicio' => now()->subHours(1)]);
        $html = $this->controller->show($venta->fresh())->render();
        $this->assertStringContainsString('Recuperar certificación', $html);
        $this->assertStringNotContainsString('Reintentar certificación', $html);
        $this->assertStringNotContainsString('Conciliar respuesta', $html);
        $this->servicio($this->clienteLocal())->recuperarCertificacion($venta->documentoFel->id, 7);
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        $controller = new FelController($this->servicio($cliente));
        $controller->reintentarCertificacion($this->request(['ultimo_intento_id' => $intento->id]), $venta);
        $html = $this->controller->show($venta->fresh())->render();
        foreach (['Ver PDF', 'Consultar XML', 'Intento 1', 'Intento 2', 'rel="noopener noreferrer"'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        foreach (['Conciliar respuesta', 'Reintentar certificación', 'Recuperar certificación', 'Certificar FEL'] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
        foreach (['reintentar_certificacion', 'recuperar_certificacion', 'consultar_representacion'] as $metodo) {
            $ruta = $this->router->getRoutes()->getByName('ventas.'.$metodo);
            $this->assertContains('auth', $ruta->gatherMiddleware());
            $this->assertContains('permiso', $ruta->gatherMiddleware());
            $this->assertSame($metodo === 'consultar_representacion' ? 'ver' : 'modificar', VerificarPermiso::ACCIONES_POR_METODO[$metodo]);
        }
    }

    public function test_error_no_muestra_reintento_y_historial_oculta_secretos_y_escapa_html(): void
    {
        [$venta, $intento] = $this->preparar('ERROR');
        $intento->update(['mensaje_error' => '<script>alert(1)</script> '.$this->configuracionFel['ws_password'],
            'error_tecnico' => $this->configuracionFel['basic_password']]);
        $html = $this->controller->show($venta->fresh())->render();
        foreach (['Reintentar certificación', 'Recuperar certificación', 'Ver PDF', 'Consultar XML',
            '<script>alert(1)</script>', $this->configuracionFel['ws_password'], $this->configuracionFel['basic_password']] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
        $this->assertStringContainsString('[REDACTADO]', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_recuperacion_falla_atomicamente_si_no_puede_cerrar_intento(): void
    {
        [$venta, $intento] = $this->preparar('EN_PROCESO', $this->certificado());
        $intento->update(['fecha_inicio' => now()->subHours(1)]);
        $antes = $this->snapshot();
        IntentoFel::updating(fn () => false);
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->recuperarCertificacion($venta->documentoFel->id, 7), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('info');
    }

    public function test_contexto_emisor_distinto_o_venta_no_confirmada_rechazan_reintento(): void
    {
        [$venta] = $this->preparar();
        config(['fel.ainnova.nit_emisor' => '9999999']);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        config(['fel.ainnova.nit_emisor' => $this->configuracionFel['nit_emisor']]);
        DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'ANULADA']);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($this->clienteLocal())->reintentarCertificacion($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    private function preparar(string $estado = 'INCIERTA', ?string $raw = 'Respuesta no concluyente'): array
    {
        $venta = $this->ventaConfirmada();
        $venta->documentoFel->forceFill(['estado_fel' => $estado, 'nit_emisor' => $this->configuracionFel['nit_emisor'],
            'codigo_establecimiento' => '1', 'id_maquina' => 'TEST-01'])->save();
        $intento = IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => $estado === 'EN_PROCESO' ? 'EN_PROCESO' : 'INCIERTA', 'fecha_inicio' => now(),
            'fecha_fin' => $estado === 'EN_PROCESO' ? null : now(), 'respuesta_raw' => $raw]);

        return [$venta, $intento->refresh()];
    }

    private function clienteLocal(): \App\Services\AinnovaFelClient
    {
        $cliente = $this->cliente();
        foreach (['generarDocumento', 'validarConfiguracion', 'contextoEmision', 'protegerTexto'] as $metodo) {
            $cliente->shouldNotReceive($metodo);
        }

        return $cliente;
    }

    private function historia(): string
    {
        return DB::table('intentos_fel')->orderBy('id')->get()->toJson();
    }
}
