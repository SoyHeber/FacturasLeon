<?php

namespace Tests\Unit;

use App\Services\FelRespuestaParserService;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class FelRespuestaSoapTest extends TestCase
{
    public function test_formato_soap_observado_aisla_gt_documento_y_extrae_certificacion(): void
    {
        $resultado = $this->procesar(file_get_contents(dirname(__DIR__).'/Fixtures/fel/respuesta_soap_ainnova.xml'));
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($this->gtDocumento(), $resultado['campos']['xml_certificado']);
        $this->assertSame('8e511596-ff36-49a8-9dc5-8f482cd2a3dc', $resultado['campos']['fel_uuid']);
        $this->assertSame('8E511596-FF36-49A8-9DC5-8F482CD2A3DC', $resultado['campos']['fel_uuid_normalized']);
        $this->assertSame('A001', $resultado['campos']['fel_serie']);
        $this->assertSame('000123', $resultado['campos']['fel_numero']);
        $this->assertSame('2026-10-03 20:15:16', $resultado['campos']['fecha_certificacion']);
        $this->assertSame('9876543K', $resultado['campos']['nit_certificador']);
        $this->assertSame('Certificador & Pruebas', $resultado['campos']['nombre_certificador']);
    }

    /** @dataProvider formatosResult */
    public function test_separacion_de_capas_no_reformatea_xml_certificado(string $formato, string $prefijo): void
    {
        $certificado = $this->gtDocumento();
        $payload = match ($formato) {
            'escapado' => htmlspecialchars($certificado, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'CDATA' => '<![CDATA['.$certificado.']]>',
            'XML' => $certificado,
            'Resultado escapado' => htmlspecialchars('<Resultado><?xml version="1.0" encoding="UTF-8"?>'.$certificado.'</Resultado>', ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'Resultado CDATA' => '<![CDATA[<Resultado><?xml version="1.0"?>'.$certificado.'</Resultado>]]>',
            'Resultado XML' => '<Resultado>'.$certificado.'</Resultado>',
        };
        $soap = '<'.$prefijo.':Envelope xmlns:'.$prefijo.'="http://schemas.xmlsoap.org/soap/envelope/"><'.$prefijo.':Body><r:generaDocumentoResponse xmlns:r="http://dbguatefac/Guatefac.wsdl"><r:result>'.$payload.'</r:result></r:generaDocumentoResponse></'.$prefijo.':Body></'.$prefijo.':Envelope>';
        $resultado = $this->procesar($soap);
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($certificado, $resultado['campos']['xml_certificado']);
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($resultado['campos']['xml_certificado'], LIBXML_NONET));
        $this->assertSame('GTDocumento', $dom->documentElement->localName);
    }

    public static function formatosResult(): array
    {
        return [['escapado', 'env'], ['CDATA', 'soapenv'], ['XML', 's'], ['Resultado escapado', 'otro'], ['Resultado CDATA', 'env'], ['Resultado XML', 'env']];
    }

    public function test_resultado_con_declaracion_interna_aisla_solamente_gt_documento(): void
    {
        $xml = '<Resultado>'.$this->certificado().'</Resultado>';
        $resultado = $this->procesar($xml);
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($this->gtDocumento(), $resultado['campos']['xml_certificado']);
    }

    public function test_cdata_preserva_crlf_firma_comentarios_y_entidades_del_certificado(): void
    {
        $certificado = str_replace("\n", "\r\n", $this->gtDocumento());
        $certificado = str_replace('<ds:Signature>', '<!-- <result>falso</result> -->'.'<ds:Signature>', $certificado);
        $soap = '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><generaDocumentoResponse xmlns="http://dbguatefac/Guatefac.wsdl"><result><![CDATA['.$certificado.']]></result></generaDocumentoResponse></Body></Envelope>';
        $resultado = $this->procesar($soap);
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($certificado, $resultado['campos']['xml_certificado']);
    }

    public function test_texto_escapado_decodifica_solo_una_capa_y_no_modifica_entidades_funcionales(): void
    {
        $xml = str_replace('Certificador &amp; Pruebas', 'Certificador &amp;lt; Pruebas', $this->gtDocumento());
        $payload = htmlspecialchars($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $soap = '<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body><generaDocumentoResponse><result>'.$payload.'</result></generaDocumentoResponse></env:Body></env:Envelope>';
        $resultado = $this->procesar($soap);
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($xml, $resultado['campos']['xml_certificado']);
        $this->assertSame('Certificador &lt; Pruebas', $resultado['campos']['nombre_certificador']);
        $this->assertSame('INCIERTA', $this->procesar(str_replace($payload, htmlspecialchars($payload, ENT_QUOTES | ENT_XML1, 'UTF-8'), $soap))['estado']);
    }

    public function test_result_en_header_no_se_confunde_con_el_result_del_body(): void
    {
        $certificado = $this->gtDocumento();
        $soap = '<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Header><result>Otra respuesta</result></env:Header><env:Body><generaDocumentoResponse><result><![CDATA['.$certificado.']]></result></generaDocumentoResponse></env:Body></env:Envelope>';
        $resultado = $this->procesar($soap);
        $this->assertSame('CERTIFICADA', $resultado['estado']);
        $this->assertSame($certificado, $resultado['campos']['xml_certificado']);
        $soap = str_replace('<result>Otra respuesta</result>', '<result>'.$certificado.'</result>', $soap);
        $soap = str_replace('<result><![CDATA['.$certificado.']]></result>', '<result>Respuesta no concluyente</result>', $soap);
        $this->assertSame('INCIERTA', $this->procesar($soap)['estado']);
    }

    /** @dataProvider soapInvalidos */
    public function test_envelope_sin_resultado_unico_no_confirma(string $xml): void
    {
        $this->assertSame('INCIERTA', $this->procesar($xml)['estado']);
    }

    public static function soapInvalidos(): array
    {
        return [
            ['<Envelope xmlns="urn:incorrecto"><Body><generaDocumentoResponse><result/></generaDocumentoResponse></Body></Envelope>'],
            ['<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><generaDocumentoResponse><result/><result/></generaDocumentoResponse></s:Body></s:Envelope>'],
            ['<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><otraOperacionResponse><result/></otraOperacionResponse></s:Body></s:Envelope>'],
            ['<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultstring>Error ambiguo</faultstring></s:Fault></s:Body></s:Envelope>'],
            ['<env:Envelope'],
            ['<!DOCTYPE Envelope [<!ENTITY x SYSTEM "file:///no-debe-leerse">]><Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body>&x;</Body></Envelope>'],
        ];
    }

    private function procesar(string $xml): array
    {
        return (new FelRespuestaParserService)->procesar($xml, '1234567-K', '201.60');
    }

    private function certificado(): string
    {
        return file_get_contents(dirname(__DIR__).'/Fixtures/fel/certificado_fact.xml');
    }

    private function gtDocumento(): string
    {
        return trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $this->certificado()));
    }
}
