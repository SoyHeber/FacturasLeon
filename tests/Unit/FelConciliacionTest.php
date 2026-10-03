<?php

namespace Tests\Unit;

use App\Http\Controllers\FelController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use Illuminate\Container\Container;
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

class FelConciliacionTest extends FelTestCase
{
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::spy(NullLogger::class);
        Log::swap($this->logger);
    }

    public function test_concilia_respuesta_existente_sin_cliente_soap_y_conserva_historia_stock_y_solicitud(): void
    {
        [$venta, $intento] = $this->preparar();
        $raw = $intento->respuesta_raw;
        $fin = $intento->fecha_fin->toDateTimeString();
        $diagnostico = $intento->error_tecnico;
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        // La conciliación solo necesita el NIT; no requiere endpoint, credenciales ni ext-soap.
        config(['fel.ainnova' => ['nit_emisor' => '1234567-K']]);
        $documento = $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7);

        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame('8e511596-ff36-49a8-9dc5-8f482cd2a3dc', $documento->fel_uuid);
        $this->assertSame('8E511596-FF36-49A8-9DC5-8F482CD2A3DC', $documento->fel_uuid_normalized);
        $this->assertSame('A001', $documento->fel_serie);
        $this->assertSame('000123', $documento->fel_numero);
        $this->assertSame('2026-10-03 20:15:16', $documento->fecha_certificacion->toDateTimeString());
        $this->assertSame('9876543K', $documento->nit_certificador);
        $this->assertSame('Certificador & Pruebas', $documento->nombre_certificador);
        $this->assertSame(trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $this->certificado())), $documento->xml_certificado);
        $intento->refresh();
        $this->assertSame('CERTIFICADA', $intento->resultado);
        $this->assertSame($raw, $intento->respuesta_raw);
        $this->assertSame($fin, $intento->fecha_fin->toDateTimeString());
        $this->assertSame($diagnostico, $intento->error_tecnico);
        $this->assertSame(7, $intento->usuario_id);
        $this->assertSame(1, IntentoFel::count());
        $this->assertSame(1, DocumentoFel::count());
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
        $this->logger->shouldHaveReceived('info')->once()->with('Conciliación local de respuesta FEL.', [
            'documento_fel_id' => $documento->id, 'intento_id' => $intento->id,
            'usuario_id' => 7, 'estado_fel' => 'CERTIFICADA',
        ]);
    }

    /** @dataProvider respuestasNoCertificadas */
    public function test_respuesta_invalida_o_incompatible_continua_incierta_y_conserva_raw(array $cambios): void
    {
        $raw = str_replace(array_keys($cambios), array_values($cambios), $this->respuestaSoap());
        [$venta, $intento] = $this->preparar($raw);
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $documento = $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $this->assertNull($documento->fel_uuid);
        $this->assertNull($documento->xml_certificado);
        $this->assertSame('INCIERTA', $intento->fresh()->resultado);
        $this->assertSame($raw, $intento->fresh()->respuesta_raw);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
    }

    public static function respuestasNoCertificadas(): array
    {
        return [
            'SOAP inválido' => [['</env:Envelope>' => '']],
            'GTDocumento inválido' => [['&lt;/dte:GTDocumento&gt;' => '']],
            'NIT diferente' => [['1234567K' => '1111111K']],
            'total diferente' => [['201.60' => '201.61']],
            'UUID faltante' => [['8e511596-ff36-49a8-9dc5-8f482cd2a3dc' => '']],
            'FACT diferente' => [['Tipo="FACT"' => 'Tipo="FCAM"']],
        ];
    }

    public function test_en_proceso_con_respuesta_del_intento_actual_es_recuperable_localmente(): void
    {
        [$venta, $intento] = $this->preparar(estado: 'EN_PROCESO');
        $this->assertNull($intento->fecha_fin);
        $documento = $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertNotNull($intento->fresh()->fecha_fin);
    }

    public function test_localiza_ultimo_intento_con_respuesta_aunque_el_ultimo_cerrado_no_tenga_raw(): void
    {
        [$venta, $conRespuesta] = $this->preparar();
        $sinRespuesta = IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => 'INCIERTA', 'fecha_inicio' => now(), 'fecha_fin' => now()]);
        $this->assertSame($conRespuesta->id, $venta->fresh()->documentoFel->ultimoIntentoConRespuesta->id);
        $this->assertStringContainsString('Conciliar respuesta', $this->controller->show($venta->fresh())->render());
        $documento = $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame('CERTIFICADA', $conRespuesta->fresh()->resultado);
        $this->assertSame('INCIERTA', $sinRespuesta->fresh()->resultado);
        $this->assertSame(2, IntentoFel::count());
    }

    public function test_intento_posterior_activo_impide_conciliar_respuesta_anterior(): void
    {
        [$venta] = $this->preparar();
        IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => 'EN_PROCESO', 'fecha_inicio' => now()]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('info');
    }

    /** @dataProvider sinRespuesta */
    public function test_no_concilia_si_no_hay_respuesta_raw(?string $raw): void
    {
        [$venta, $intento] = $this->preparar();
        $intento->update(['respuesta_raw' => $raw]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function sinRespuesta(): array
    {
        return [[null], [''], [" \n"]];
    }

    /** @dataProvider estadosNoConciliables */
    public function test_documento_fuera_de_estados_recuperables_no_se_reprocesa(string $estado): void
    {
        [$venta] = $this->preparar(estado: $estado);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosNoConciliables(): array
    {
        return [['PENDIENTE'], ['CERTIFICADA'], ['ERROR'], ['ANULADA']];
    }

    public function test_doble_click_no_reconcilia_otra_vez_un_documento_certificado(): void
    {
        [$venta] = $this->preparar();
        $servicio = $this->servicioLocal();
        $servicio->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $servicio->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldHaveReceived('info')->once();
    }

    public function test_fallo_de_persistencia_revierte_documento_e_intento_y_no_registra_exito(): void
    {
        [$venta] = $this->preparar();
        $antes = $this->snapshot();
        IntentoFel::updating(fn () => false);
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('info');
    }

    public function test_hash_alterado_impide_conciliar_y_no_cambia_datos(): void
    {
        [$venta] = $this->preparar();
        DB::table('documentos_fel')->where('id', $venta->documentoFel->id)->update(['hash_xml' => str_repeat('0', 64)]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('info');
    }

    public function test_sin_nit_configurado_no_concilia_ni_modifica_datos(): void
    {
        [$venta] = $this->preparar();
        config(['fel.ainnova' => []]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicioLocal()->conciliarDesdeRespuestaExistente($venta->documentoFel->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_ruta_y_boton_con_permiso_modificar_reprocesan_sin_reenvio(): void
    {
        [$venta] = $this->preparar();
        $html = $this->controller->show($venta)->render();
        $this->assertStringContainsString('Conciliar respuesta', $html);
        $this->assertStringContainsString('No realiza una nueva llamada a Ainnova.', $html);
        $this->assertStringNotContainsString('Certificar FEL', $html);
        $this->assertStringNotContainsString('&lt;env:Envelope', $html);
        $ruta = $this->router->getRoutes()->getByName('ventas.conciliar_respuesta');
        $this->assertSame(['POST'], $ruta->methods());
        $this->assertSame('ventas/{venta}/conciliar-respuesta', $ruta->uri());
        $this->assertContains('auth', $ruta->gatherMiddleware());
        $this->assertContains('permiso', $ruta->gatherMiddleware());
        $this->assertSame('modificar', VerificarPermiso::ACCIONES_POR_METODO['conciliar_respuesta']);
        $request = $this->request([], 'POST');
        $request->setRouteResolver(fn () => $ruta);
        $controller = new FelController($this->servicioLocal());
        $respuesta = (new VerificarPermiso)->handle($request, fn ($request) => $controller->conciliarRespuesta($request, $venta));
        $this->assertSame('http://localhost/ventas/'.$venta->id, $respuesta->getTargetUrl());
        $this->assertStringContainsString('No se realizó una nueva llamada a Ainnova.', session('success'));
        $this->assertStringNotContainsString('Conciliar respuesta', $this->controller->show($venta->fresh())->render());
    }

    public function test_sin_permiso_no_muestra_boton_ni_permite_conciliar(): void
    {
        [$venta] = $this->preparar();
        $opcion = DB::table('opciones')->where('ruta', 'ventas')->value('id');
        $accion = DB::table('acciones')->where('clave', 'modificar')->value('id');
        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion)->where('accion_id', $accion)->delete();
        $this->assertStringNotContainsString('Conciliar respuesta', $this->controller->show($venta)->render());
        $request = $this->request([], 'POST');
        $request->setRouteResolver(fn () => $this->router->getRoutes()->getByName('ventas.conciliar_respuesta'));
        Container::setInstance(new class extends Container
        {
            public function abort($code, $message = '', array $headers = []): never
            {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
            }
        });
        $this->rechaza(fn () => (new VerificarPermiso)->handle($request, fn () => $this->fail('No debe conciliar.')), \Symfony\Component\HttpKernel\Exception\HttpException::class);
    }

    private function preparar(?string $raw = null, string $estado = 'INCIERTA'): array
    {
        $venta = $this->ventaConfirmada();
        $intento = IntentoFel::create([
            'documento_fel_id' => $venta->documentoFel->id, 'usuario_id' => 7,
            'resultado' => $estado === 'EN_PROCESO' ? 'EN_PROCESO' : 'INCIERTA',
            'fecha_inicio' => '2026-10-03 20:15:00', 'fecha_fin' => $estado === 'EN_PROCESO' ? null : '2026-10-03 20:15:20',
            'respuesta_raw' => $raw ?? $this->respuestaSoap(), 'error_tecnico' => 'XML inválido al procesar la respuesta original.',
        ]);
        $venta->documentoFel->forceFill(['estado_fel' => $estado, 'nit_emisor' => '1234567-K'])->save();

        return [$venta, $intento];
    }

    private function respuestaSoap(): string
    {
        return file_get_contents(dirname(__DIR__).'/Fixtures/fel/respuesta_soap_ainnova.xml');
    }

    private function servicioLocal(): \App\Services\FelCertificacionService
    {
        $cliente = $this->cliente();
        foreach (['generarDocumento', 'validarConfiguracion', 'contextoEmision', 'protegerTexto'] as $metodo) {
            $cliente->shouldNotReceive($metodo);
        }

        return $this->servicio($cliente);
    }
}
