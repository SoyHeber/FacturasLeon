<?php

namespace Tests\Unit;

use App\Data\RespuestaAinnova;
use App\Services\AinnovaFelClient;
use App\Services\AinnovaSoapClient;
use DOMDocument;
use DOMXPath;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use SoapFault;
use Throwable;

class AinnovaFelClientTest extends TestCase
{
    private array $configuracion;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $container->instance('config', new Repository);
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $container));
        $this->configuracion = [
            'endpoint' => 'https://ainnova.invalid/soap',
            'basic_usuario' => 'basic-usuario-prueba', 'basic_password' => 'basic-password-prueba',
            'ws_usuario' => 'ws-usuario-prueba', 'ws_password' => 'ws-password-prueba',
            'nit_emisor' => '1234567K', 'establecimiento' => '1', 'id_maquina' => 'TEST-01',
            'timeout' => 30, 'connect_timeout' => 5, 'verificar_ssl' => true,
        ];
    }

    public function test_ext_soap_serializa_rpc_literal_nombres_parametros_y_xml_exacto_sin_red(): void
    {
        $payload = '<?xml version="1.0"?><DocElectronico><Referencia>A&B&lt;1&gt;</Referencia></DocElectronico>';
        $cliente = $this->cliente('Documento rechazado por NIT inválido');
        $respuesta = $cliente->generarDocumento($payload, 1, 'D');
        $this->assertSame(RespuestaAinnova::TEXTUAL, $respuesta->tipo);
        $this->assertSame('Documento rechazado por NIT inválido', $respuesta->texto);
        $this->assertGreaterThanOrEqual(0, $respuesta->duracionMs);
        $this->assertSame(1, $cliente->soap->llamadas);
        $this->assertSame(AinnovaFelClient::SOAP_ACTION, $cliente->soap->accion);
        $this->assertSame($this->configuracion['endpoint'], $cliente->soap->ubicacion);
        $this->assertSame(SOAP_1_1, $cliente->soap->version);
        $this->assertSame(SOAP_RPC, $cliente->opciones['style']);
        $this->assertSame(SOAP_LITERAL, $cliente->opciones['use']);
        $this->assertSame(SOAP_AUTHENTICATION_BASIC, $cliente->opciones['authentication']);
        $this->assertSame($this->configuracion['basic_usuario'], $cliente->opciones['login']);
        $this->assertSame($this->configuracion['basic_password'], $cliente->opciones['password']);
        $this->assertFalse($cliente->opciones['trace']);
        $this->assertTrue($cliente->opciones['exceptions']);
        $this->assertSame(5, $cliente->opciones['connection_timeout']);
        $contexto = stream_context_get_options($cliente->opciones['stream_context']);
        $this->assertTrue($contexto['ssl']['verify_peer']);
        $this->assertTrue($contexto['ssl']['verify_peer_name']);
        $this->assertFalse($contexto['ssl']['allow_self_signed']);
        $this->assertSame(0, $contexto['http']['max_redirects']);
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($cliente->soap->requestXml, LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('a', AinnovaFelClient::NAMESPACE);
        $operacion = $xpath->query('/s:Envelope/s:Body/a:generaDocumento')->item(0);
        $this->assertNotNull($operacion);
        $parametros = [];
        foreach ($operacion->childNodes as $nodo) {
            if ($nodo instanceof \DOMElement) {
                $parametros[$nodo->localName] = $nodo->textContent;
            }
        }
        $this->assertSame([
            'pUsuario' => $this->configuracion['ws_usuario'], 'pPassword' => $this->configuracion['ws_password'],
            'pNitEmisor' => '1234567K', 'pEstablecimiento' => '1', 'pTipoDoc' => '1',
            'pIdMaquina' => 'TEST-01', 'pTipoRespuesta' => 'D', 'pXml' => $payload,
        ], $parametros);
        $this->assertStringNotContainsString('encodingStyle', $cliente->soap->requestXml);
        $this->assertNull($cliente->soap->__getLastRequest());
        $this->assertSame($cliente->soap->respuestaRaw(), $respuesta->respuestaRaw);
        foreach (['basic_usuario', 'basic_password', 'ws_usuario', 'ws_password'] as $campo) {
            $this->assertStringNotContainsString($this->configuracion[$campo], json_encode($respuesta));
        }
    }

    /** @dataProvider fallosTransporte */
    public function test_fallos_son_dto_sin_excepciones_crudas_y_restauran_timeout(Throwable $fallo, string $tipo): void
    {
        $original = ini_get('default_socket_timeout');
        $cliente = $this->cliente(null, $fallo);
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame($tipo, $respuesta->tipo);
        $this->assertSame(1, $cliente->soap->llamadas);
        $this->assertSame('30', $cliente->soap->timeoutDuranteLlamada);
        $this->assertSame($original, ini_get('default_socket_timeout'));
        $this->assertStringNotContainsString('password', $respuesta->mensajeTecnico);
    }

    public static function fallosTransporte(): array
    {
        return [
            'timeout' => [new SoapFault('HTTP', 'Operation timed out password'), RespuestaAinnova::TIMEOUT],
            'HTTP' => [new SoapFault('HTTP', '401 Unauthorized password'), RespuestaAinnova::HTTP],
            'SOAP Fault' => [new SoapFault('Server', 'Fault ambiguo password'), RespuestaAinnova::SOAP_FAULT],
            'red' => [new \RuntimeException('Conexión interrumpida password'), RespuestaAinnova::TRANSPORTE],
        ];
    }

    public function test_soap_fault_recibido_conserva_envelope_original_sin_request(): void
    {
        $cliente = $this->cliente(null);
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame(RespuestaAinnova::SOAP_FAULT, $respuesta->tipo);
        $this->assertSame($cliente->soap->respuestaRaw(), $respuesta->respuestaRaw);
        $this->assertStringContainsString('<SOAP-ENV:Fault>', $respuesta->respuestaRaw);
    }

    public function test_respuesta_xml_es_texto_y_no_objeto_soap(): void
    {
        $xml = '<Resultado><Error><Codigo>E-NIT</Codigo><Mensaje>NIT inválido</Mensaje></Error></Resultado>';
        $cliente = $this->cliente($xml);
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame(RespuestaAinnova::RESPUESTA, $respuesta->tipo);
        $this->assertSame($xml, $respuesta->texto);
        $this->assertStringContainsString('&lt;Resultado&gt;', $respuesta->respuestaRaw);
    }

    public function test_xml_utf8_con_bom_se_reconoce_sin_cambiar_sus_bytes(): void
    {
        $xml = "\xEF\xBB\xBF".'<Resultado><Error><Mensaje>NIT inválido</Mensaje></Error></Resultado>';
        $respuesta = $this->cliente($xml)->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame(RespuestaAinnova::RESPUESTA, $respuesta->tipo);
        $this->assertSame($xml, $respuesta->texto);
    }

    public function test_credenciales_repetidas_en_respuesta_no_salen_del_cliente(): void
    {
        $texto = 'ERROR: '.$this->configuracion['ws_password'].' '.$this->configuracion['basic_usuario'];
        $cliente = $this->cliente($texto);
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame(RespuestaAinnova::TRANSPORTE, $respuesta->tipo);
        $this->assertSame('RESPUESTA_CON_SECRETOS', $respuesta->codigoTecnico);
        $this->assertStringNotContainsString($this->configuracion['ws_password'], json_encode($respuesta));
        $this->assertStringNotContainsString($this->configuracion['basic_usuario'], json_encode($respuesta));
    }

    public function test_redaccion_cubre_password_xml_basic_y_valores_escapados(): void
    {
        $cliente = new AinnovaFelClient(array_replace($this->configuracion, ['ws_password' => 'clave<&>prueba']));
        $texto = 'Authorization: Basic '.base64_encode($this->configuracion['basic_usuario'].':'.$this->configuracion['basic_password'])."\n";
        $texto .= '<pPassword>clave&lt;&amp;&gt;prueba</pPassword> clave<&>prueba';
        $seguro = $cliente->protegerTexto($texto);
        $this->assertStringNotContainsString('clave', $seguro);
        $this->assertStringNotContainsString(base64_encode($this->configuracion['basic_usuario'].':'.$this->configuracion['basic_password']), $seguro);
        $this->assertStringContainsString('[REDACTADO]', $seguro);
        $doble = 'clave&amp;lt;&amp;amp;&amp;gt;prueba';
        $this->assertSame('[REDACTADO]', $cliente->protegerTexto($doble));
    }

    public function test_advertencias_nativas_no_exponen_secretos_y_restauran_handler(): void
    {
        $advertencias = 0;
        set_error_handler(function () use (&$advertencias) {
            $advertencias++;

            return true;
        });
        try {
            $cliente = $this->cliente(null, new \ErrorException($this->configuracion['ws_password']));
            $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
            $this->assertSame(RespuestaAinnova::TRANSPORTE, $respuesta->tipo);
            $this->assertStringNotContainsString($this->configuracion['ws_password'], json_encode($respuesta));
            $this->assertSame(0, $advertencias);
            trigger_error('Advertencia de prueba sin secretos', E_USER_WARNING);
            $this->assertSame(1, $advertencias);
        } finally {
            restore_error_handler();
        }
    }

    public function test_configuracion_invalida_devuelve_dto_antes_de_crear_soap(): void
    {
        $cliente = $this->cliente('sin llamada');
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 2, 'D');
        $this->assertSame(RespuestaAinnova::CONFIGURACION, $respuesta->tipo);
        $this->assertNull($cliente->soap);
        $cliente = $this->cliente('sin llamada', cambios: ['endpoint' => 'https://usuario:password@ainnova.invalid']);
        $respuesta = $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertSame(RespuestaAinnova::CONFIGURACION, $respuesta->tipo);
        $this->assertNull($cliente->soap);
    }

    public function test_ssl_solo_se_desactiva_mediante_configuracion_explicita(): void
    {
        $cliente = $this->cliente('Documento rechazado', cambios: ['verificar_ssl' => false]);
        $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertFalse(stream_context_get_options($cliente->opciones['stream_context'])['ssl']['verify_peer']);
        $config = $this->configuracion;
        unset($config['verificar_ssl']);
        $cliente = $this->cliente('Documento rechazado', cambios: $config);
        $cliente->generarDocumento('<DocElectronico/>', 1, 'D');
        $this->assertTrue(stream_context_get_options($cliente->opciones['stream_context'])['ssl']['verify_peer']);
    }

    private function cliente(?string $texto, ?Throwable $fallo = null, array $cambios = []): AinnovaFelClient
    {
        return new class(array_replace($this->configuracion, $cambios), $texto, $fallo) extends AinnovaFelClient
        {
            public array $opciones = [];

            public ?AinnovaSoapClient $soap = null;

            public function __construct(array $configuracion, private ?string $texto, private ?Throwable $fallo)
            {
                parent::__construct($configuracion);
            }

            protected function crearSoapClient(array $opciones): AinnovaSoapClient
            {
                $this->opciones = $opciones;

                return $this->soap = new class($opciones, $this->texto, $this->fallo) extends AinnovaSoapClient
                {
                    public int $llamadas = 0;

                    public string $requestXml = '';

                    public string $accion = '';

                    public string $ubicacion = '';

                    public int $version = 0;

                    public ?string $timeoutDuranteLlamada = null;

                    private ?string $raw = null;

                    public function __construct(array $opciones, private ?string $texto, private ?Throwable $fallo)
                    {
                        parent::__construct(null, $opciones);
                    }

                    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
                    {
                        $this->llamadas++;
                        $this->requestXml = $request;
                        $this->accion = $action;
                        $this->ubicacion = $location;
                        $this->version = $version;
                        $this->timeoutDuranteLlamada = ini_get('default_socket_timeout');
                        if ($this->fallo instanceof \ErrorException) {
                            trigger_error($this->fallo->getMessage(), E_USER_WARNING);
                        }
                        if ($this->fallo !== null) {
                            throw $this->fallo;
                        }
                        $dom = new DOMDocument('1.0', 'UTF-8');
                        $sobre = $dom->appendChild($dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'SOAP-ENV:Envelope'));
                        $body = $sobre->appendChild($dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'SOAP-ENV:Body'));
                        if ($this->texto === null) {
                            $fault = $body->appendChild($dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'SOAP-ENV:Fault'));
                            $fault->appendChild($dom->createElement('faultcode', 'Server'));
                            $fault->appendChild($dom->createElement('faultstring', 'Resultado ambiguo'));
                        } else {
                            $respuesta = $body->appendChild($dom->createElementNS(AinnovaFelClient::NAMESPACE, 'a:generaDocumentoResponse'));
                            $retorno = $respuesta->appendChild($dom->createElement('return'));
                            $retorno->appendChild($dom->createTextNode($this->texto));
                        }

                        return $this->raw = $dom->saveXML();
                    }

                    public function respuestaRaw(): ?string
                    {
                        return $this->raw;
                    }
                };
            }
        };
    }
}
