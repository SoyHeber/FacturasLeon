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
    public function test_consulta_url_pdf_xml_con_basic_y_headers_sin_mutar_bd(string $tipo, int $status): void
    {
        $documento = $this->certificar();
        $url = 'https://dte.guatefacturas.com/archivo/certificado.'.strtolower($tipo);
        Http::fake([$this->configuracionFel['endpoint_rest'] => Http::response($url, $status)]);
        $antes = $this->snapshot();
        $this->assertSame($url, (new AinnovaFelConsultaClient)->consultar($documento->id, $tipo));
        Http::assertSent(function ($request) use ($documento, $tipo) {
            $this->assertSame('', $request->body());
            $this->assertStringNotContainsString('[]', $request->body());
            $this->assertFalse($request->isJson());
            $this->assertFalse($request->hasHeader('Content-Type'));

            return $request->method() === 'POST' && $request->url() === $this->configuracionFel['endpoint_rest']
                && parse_url($request->url(), PHP_URL_QUERY) === null && $request->data() === []
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
        $this->logger->shouldHaveReceived('info')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['http_status'] === $status && $datos['body'] === $url && $datos['causa'] === 'EXITO'
            && $datos['excepcion_tecnica'] === null));
    }

    public static function tipos(): array
    {
        return [['PDF', 200], ['XML', 200], ['PDF', 201], ['XML', 206]];
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
        try {
            (new AinnovaFelConsultaClient)->consultar($documento->id, 'PDF');
            $this->fail('Debe rechazar el error de consulta.');
        } catch (ValidationException $e) {
            $this->assertSame($status < 200 || $status >= 300
                ? 'Ainnova no pudo entregar la representación FEL. La factura continúa CERTIFICADA.'
                : (str_starts_with($texto, 'ERROR:') ? $texto : 'Ainnova no devolvió una URL segura de PDF/XML. La factura continúa CERTIFICADA.'),
                $e->errors()['fel'][0]);
        }
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('CERTIFICADA', $documento->fresh()->estado_fel);
        $this->logger->shouldHaveReceived('warning')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['http_status'] === $status && $datos['body'] === trim($texto)
            && $datos['causa'] === ($status < 200 || $status >= 300 ? 'HTTP_NO_EXITOSO'
                : (str_starts_with($texto, 'ERROR:') ? 'ERROR_FUNCIONAL' : 'URL_INVALIDA'))));
        $this->logger->shouldNotHaveReceived('error');
    }

    public static function respuestasInvalidas(): array
    {
        return [['ERROR: No disponible', 200], ['', 200], ['javascript:alert(1)', 200],
            ['http://dte.guatefacturas.com/pdf', 200], ['https://usuario:clave@dte.guatefacturas.com/pdf', 200],
            ["https://dte.guatefacturas.com/pdf\r\nLocation: https://otro.invalid", 200],
            ['https://dte.guatefacturas.com/pdf', 302], ['Error de autenticación', 401], ['Method Not Allowed', 405], ['No disponible', 503]];
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
            $this->assertSame('No se pudo consultar la representación FEL. La factura continúa CERTIFICADA.', $e->errors()['fel'][0]);
            $this->assertStringNotContainsString($this->configuracionFel['ws_password'], json_encode($e->errors()));
        }
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldHaveReceived('warning')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['http_status'] === null && $datos['content_type'] === null && $datos['body'] === null
            && $datos['tipo_excepcion'] === \RuntimeException::class && $datos['excepcion_tecnica'] === '[REDACTADO]'));
        $this->logger->shouldNotHaveReceived('error');
    }

    public function test_error_funcional_oculta_credenciales_en_mensaje_y_diagnostico(): void
    {
        $documento = $this->certificar();
        $antes = $this->snapshot();
        $texto = 'ERROR: '.$this->configuracionFel['ws_password'].' '.$this->configuracionFel['basic_password'];
        Http::fake(['*' => Http::response($texto)]);
        try {
            (new AinnovaFelConsultaClient)->consultar($documento->id, 'PDF');
            $this->fail('Debe ocultar los secretos del error funcional.');
        } catch (ValidationException $e) {
            foreach (['ws_password', 'basic_password'] as $campo) {
                $this->assertStringNotContainsString($this->configuracionFel[$campo], json_encode($e->errors()));
                $this->assertStringNotContainsString($this->configuracionFel[$campo], json_encode($this->snapshot()));
            }
        }
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldHaveReceived('warning')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['body'] === 'ERROR: [REDACTADO] [REDACTADO]' && $datos['causa'] === 'ERROR_FUNCIONAL'));
        $this->logger->shouldNotHaveReceived('info');
        $this->logger->shouldNotHaveReceived('error');
    }

    /** @dataProvider urlsValidas */
    public function test_2xx_con_url_trim_es_exito_independientemente_de_content_type(string $tipo, int $status, string $contentType, string $url): void
    {
        $documento = $this->certificar();
        Http::fake(['*' => Http::response(" \r\n".$url."\t\r\n ", $status, ['Content-Type' => $contentType])]);
        $antes = $this->snapshot();
        $this->assertSame($url, (new AinnovaFelConsultaClient)->consultar($documento->id, $tipo));
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldHaveReceived('info')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['http_status'] === $status && $datos['content_type'] === $contentType && $datos['body'] === $url));
    }

    public static function urlsValidas(): array
    {
        return [['PDF', 200, 'text/plain; charset=utf-8', 'https://dte.guatefacturas.com/archivo/factura.pdf'],
            ['XML', 200, 'text/html', 'https://dte.guatefacturas.com/archivo/factura.xml'],
            ['PDF', 201, 'application/json', 'HTTPS://dte.guatefacturas.com/archivo/factura.pdf'],
            ['XML', 206, 'application/octet-stream', 'https://dte.guatefacturas.com/archivo/'.str_repeat('a', 4200).'.xml']];
    }

    public function test_url_valida_con_usuario_en_ruta_no_se_rechaza_por_redaccion(): void
    {
        $documento = $this->certificar();
        $url = 'https://dte.guatefacturas.com/archivo/'.$this->configuracionFel['ws_usuario'].'/factura.pdf';
        Http::fake(['*' => Http::response("\r\n".$url."\r\n", 200, ['Content-Type' => 'text/plain'])]);
        $antes = $this->snapshot();
        $controller = new FelController($this->servicio($this->cliente()));
        $respuesta = $controller->consultarRepresentacion($this->request([], 'GET'), $documento->venta, 'pdf', new AinnovaFelConsultaClient);
        $this->assertSame($url, $respuesta->getTargetUrl());
        $this->assertSame($antes, $this->snapshot());
        $this->logger->shouldHaveReceived('info')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(fn ($datos) => $datos['body'] === 'https://dte.guatefacturas.com/archivo/[REDACTADO]/factura.pdf'
            && ! str_contains(json_encode($datos), $this->configuracionFel['ws_usuario'])));
        Http::assertSentCount(1);
    }

    /** @dataProvider fallosConsulta */
    public function test_controller_muestra_warning_y_conserva_certificada_ante_error(string $body, int $status): void
    {
        $documento = $this->certificar();
        $antes = $this->snapshot();
        Http::fake(['*' => Http::response($body, $status, ['Content-Type' => 'text/plain'])]);
        $controller = new FelController($this->servicio($this->cliente()));
        $respuesta = $controller->consultarRepresentacion($this->request([], 'GET'), $documento->venta, 'xml', new AinnovaFelConsultaClient);
        $this->assertSame('http://localhost/ventas/'.$documento->venta_id, $respuesta->getTargetUrl());
        $this->assertNotEmpty(session('warning'));
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame('CERTIFICADA', $documento->fresh()->estado_fel);
        $this->logger->shouldHaveReceived('warning')->once();
    }

    public static function fallosConsulta(): array
    {
        return [['ERROR: Representación no disponible', 200], ['Method Not Allowed', 405], ['Respuesta inválida', 200]];
    }

    public function test_diagnostico_ssl_conserva_causa_tecnica_y_oculta_basic_y_headers(): void
    {
        $documento = $this->certificar();
        $basic = base64_encode($this->configuracionFel['basic_usuario'].':'.$this->configuracionFel['basic_password']);
        $mensaje = 'cURL error 60: SSL certificate problem; Authorization: Basic '.$basic
            .' p_usuario='.$this->configuracionFel['ws_usuario'].' p_clave='.$this->configuracionFel['ws_password'];
        Http::fake(fn () => throw new \RuntimeException($mensaje));
        $antes = $this->snapshot();
        $controller = new FelController($this->servicio($this->cliente()));
        $respuesta = $controller->consultarRepresentacion($this->request([], 'GET'), $documento->venta, 'pdf', new AinnovaFelConsultaClient);
        $this->assertSame('http://localhost/ventas/'.$documento->venta_id, $respuesta->getTargetUrl());
        $this->assertSame($antes, $this->snapshot());
        $this->assertStringNotContainsString('cURL', session('warning'));
        $this->logger->shouldHaveReceived('warning')->once()->with('Consulta REST FEL/Ainnova.', Mockery::on(function ($datos) use ($basic) {
            $texto = json_encode($datos);
            foreach (['basic_usuario', 'basic_password', 'ws_usuario', 'ws_password'] as $campo) {
                $this->assertStringNotContainsString($this->configuracionFel[$campo], $texto);
            }
            $this->assertStringNotContainsString($basic, $texto);
            $this->assertStringContainsString('cURL error 60: SSL certificate problem', $datos['excepcion_tecnica']);

            return $datos['causa'] === 'EXCEPCION';
        }));
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
        $this->assertArrayNotHasKey('json', $opciones);
        $this->assertArrayNotHasKey('form_params', $opciones);
        $this->assertArrayNotHasKey('query', $opciones);
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
