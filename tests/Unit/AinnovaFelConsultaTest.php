<?php

namespace Tests\Unit;

use App\Data\RespuestaAinnova;
use App\Http\Controllers\FelController;
use App\Models\DocumentoFel;
use App\Services\AinnovaFelConsultaClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery;
use Psr\Log\NullLogger;
use Tests\Support\FelTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';
require_once dirname(__DIR__).'/Support/FelTestCase.php';

class AinnovaFelConsultaTest extends FelTestCase
{
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuracionFel['endpoint_rest'] = 'https://dte.guatefacturas.com/app/pruebas/fel/pdf/URL';
        config(['fel.ainnova' => $this->configuracionFel]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->logger = Mockery::spy(NullLogger::class);
        Log::swap($this->logger);
    }

    /** @dataProvider tipos */
    public function test_consulta_url_pdf_xml_con_basic_y_headers_sin_mutar_bd(string $tipo): void
    {
        $documento = $this->certificar();
        $url = 'https://dte.guatefacturas.com/archivo/certificado.'.strtolower($tipo);
        Http::fake([$this->configuracionFel['endpoint_rest'] => Http::response($url)]);
        $antes = $this->snapshot();
        $this->assertSame($url, (new AinnovaFelConsultaClient)->consultar($documento->id, $tipo));
        Http::assertSent(function ($request) use ($documento, $tipo) {
            return $request->method() === 'GET' && $request->url() === $this->configuracionFel['endpoint_rest']
                && $request->hasHeader('Authorization', 'Basic '.base64_encode($this->configuracionFel['basic_usuario'].':'.$this->configuracionFel['basic_password']))
                && $request->hasHeader('p_usuario', $this->configuracionFel['ws_usuario'])
                && $request->hasHeader('p_clave', $this->configuracionFel['ws_password'])
                && $request->hasHeader('p_emisor', $this->configuracionFel['nit_emisor'])
                && $request->hasHeader('p_serie', $documento->fel_serie)
                && $request->hasHeader('p_numero', $documento->fel_numero) && $request->hasHeader('p_tipo', $tipo);
        });
        Http::assertSentCount(1);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('CERTIFICADA', $documento->fresh()->estado_fel);
    }

    public static function tipos(): array
    {
        return [['PDF'], ['XML']];
    }

    /** @dataProvider estadosNoCertificados */
    public function test_pdf_y_xml_solo_para_certificada(string $estado, string $tipo): void
    {
        $documento = $this->ventaConfirmada()->documentoFel;
        $documento->forceFill(['estado_fel' => $estado])->save();
        Http::fake();
        $antes = $this->snapshot();
        $this->rechaza(fn () => (new AinnovaFelConsultaClient)->consultar($documento->id, $tipo), ValidationException::class);
        Http::assertNothingSent();
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosNoCertificados(): array
    {
        $casos = [];
        foreach (['PENDIENTE', 'INCIERTA', 'EN_PROCESO', 'ERROR', 'ANULADA'] as $estado) {
            foreach (['PDF', 'XML'] as $tipo) {
                $casos[] = [$estado, $tipo];
            }
        }

        return $casos;
    }

    /** @dataProvider respuestasInvalidas */
    public function test_error_rest_o_url_insegura_no_cambia_certificacion(string $texto, int $status): void
    {
        $documento = $this->certificar();
        Http::fake(['*' => Http::response($texto, $status)]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => (new AinnovaFelConsultaClient)->consultar($documento->id, 'PDF'), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('CERTIFICADA', $documento->fresh()->estado_fel);
        $this->logger->shouldNotHaveReceived('error');
    }

    public static function respuestasInvalidas(): array
    {
        return [['ERROR: No disponible', 200], ['', 200], ['javascript:alert(1)', 200],
            ['http://dte.guatefacturas.com/pdf', 200], ['https://usuario:clave@dte.guatefacturas.com/pdf', 200],
            ["https://dte.guatefacturas.com/pdf\r\nLocation: https://otro.invalid", 200],
            ['https://dte.guatefacturas.com/pdf', 302], ['Error de autenticación', 401], ['No disponible', 503]];
    }

    public function test_fallo_transporte_no_expone_excepcion_ni_altera_certificacion(): void
    {
        $documento = $this->certificar();
        $antes = $this->snapshot();
        Http::fake(fn () => throw new \RuntimeException($this->configuracionFel['ws_password']));
        try {
            (new AinnovaFelConsultaClient)->consultar($documento->id, 'XML');
            $this->fail('Debe rechazar un fallo de transporte.');
        } catch (ValidationException $e) {
            $this->assertStringNotContainsString($this->configuracionFel['ws_password'], json_encode($e->errors()));
        }
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('error');
    }

    public function test_errores_y_urls_con_secretos_se_rechazan_sin_persistir_o_registrar_credenciales(): void
    {
        $documento = $this->certificar();
        $antes = $this->snapshot();
        foreach (['ERROR: '.$this->configuracionFel['ws_password'],
            'https://dte.guatefacturas.com/pdf?clave='.rawurlencode($this->configuracionFel['basic_password'])] as $texto) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['*' => Http::response($texto)]);
            try {
                (new AinnovaFelConsultaClient)->consultar($documento->id, 'PDF');
                $this->fail('Debe ocultar o rechazar los secretos.');
            } catch (ValidationException $e) {
                foreach (['ws_password', 'basic_password'] as $campo) {
                    $this->assertStringNotContainsString($this->configuracionFel[$campo], json_encode($e->errors()));
                    $this->assertStringNotContainsString($this->configuracionFel[$campo], json_encode($this->snapshot()));
                }
            }
        }
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldNotHaveReceived('info');
        $this->logger->shouldNotHaveReceived('error');
    }

    public function test_no_sigue_redirecciones_y_controller_redirige_sin_referer(): void
    {
        $documento = $this->certificar();
        $url = 'https://dte.guatefacturas.com/archivo/factura.xml';
        $opciones = null;
        Http::fake(function ($request, $options) use ($url, &$opciones) {
            $opciones = $options;

            return Http::response($url);
        });
        $controller = new FelController($this->servicio($this->cliente()));
        $respuesta = $controller->consultarRepresentacion($this->request([], 'GET'), $documento->venta, 'xml', new AinnovaFelConsultaClient);
        $this->assertSame($url, $respuesta->getTargetUrl());
        $this->assertSame('no-referrer', $respuesta->headers->get('Referrer-Policy'));
        $this->assertFalse($opciones['allow_redirects']);
        $this->assertTrue($opciones['verify']);
    }

    public function test_endpoint_o_tipo_invalidos_no_hacen_llamadas(): void
    {
        $documento = $this->certificar();
        Http::fake();
        $this->rechaza(fn () => (new AinnovaFelConsultaClient)->consultar($documento->id, 'HTML'), ValidationException::class);
        config(['fel.ainnova.endpoint_rest' => 'http://dte.guatefacturas.com/pdf']);
        $this->rechaza(fn () => (new AinnovaFelConsultaClient)->consultar($documento->id, 'PDF'), ValidationException::class);
        Http::assertNothingSent();
    }

    private function certificar(): DocumentoFel
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));

        return $this->servicio($cliente)->certificarVenta($venta->id, 7);
    }
}
