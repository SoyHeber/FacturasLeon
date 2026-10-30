<?php

namespace Tests\Unit;

use App\Services\FelAnulacionRespuestaParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FelAnulacionRespuestaTest extends TestCase
{
    private const SOLICITUD = ['nit_emisor' => '1234567-K', 'nit_comprador' => 'CF', 'serie' => 'ABC123', 'numero' => '000123'];

    public function test_acuse_directo_soap_escapado_y_cdata_confirman_solo_el_documento_solicitado(): void
    {
        $xml = $this->acuse();
        foreach ([$xml, $this->sobre($xml), $this->sobre(htmlspecialchars($xml, ENT_XML1)), $this->sobre('<![CDATA['.$xml.']]>')] as $respuesta) {
            $this->assertSame('CONFIRMADA', $this->procesar($respuesta));
        }
    }

    #[DataProvider('respuestasInvalidas')]
    public function test_ningun_resultado_incompleto_ambiguo_o_de_otra_factura_habilita_el_reintegro(string $respuesta): void
    {
        $this->assertSame('INCIERTA', $this->procesar($respuesta));
    }

    public static function respuestasInvalidas(): array
    {
        $base = '<RESULTADO><ESTADO>ANULADO</ESTADO><EMISOR>1234567K</EMISOR><COMPRADOR>CF</COMPRADOR><SERIE>ABC123</SERIE><PREIMPRESO>123</PREIMPRESO></RESULTADO>';

        return [
            'emisor distinto' => [str_replace('1234567K', '999', $base)],
            'comprador distinto' => [str_replace('<COMPRADOR>CF', '<COMPRADOR>123', $base)],
            'serie distinta' => [str_replace('ABC123', 'OTRA', $base)],
            'numero distinto' => [str_replace('>123<', '>124<', $base)],
            'estado distinto' => [str_replace('ANULADO', 'CERTIFICADO', $base)],
            'campo repetido' => [str_replace('</RESULTADO>', '<SERIE>ABC123</SERIE></RESULTADO>', $base)],
            'resultado contradictorio' => [str_replace('</RESULTADO>', '<ERROR>Fallo al anular</ERROR></RESULTADO>', $base)],
            'texto fuera de campos' => [str_replace('<RESULTADO>', '<RESULTADO>ERROR:', $base)],
            'texto fuera del acuse' => ['<anulaDocumentoResponse><return>ERROR:'.$base.'</return></anulaDocumentoResponse>'],
            'sobre ajeno' => ['<s:Envelope xmlns:s="urn:ajeno"><s:Body><anulaDocumentoResponse><return>'.$base.'</return></anulaDocumentoResponse></s:Body></s:Envelope>'],
            'error interno' => ['ERROR: fallo interno del servidor'],
            'campo ausente' => [str_replace('<EMISOR>1234567K</EMISOR>', '', $base)],
            'campo anidado' => [str_replace('<EMISOR>1234567K', '<EMISOR><valor>1234567K</valor>', $base)],
            'entidad externa' => ['<!DOCTYPE RESULTADO [<!ENTITY x SYSTEM "file:///archivo-inexistente">]>'.str_replace('1234567K', '&x;', $base)],
            'xml truncado' => ['<RESULTADO>'],
            'error 94' => ['<RESULTADO>ERROR 94 DOCUMENTO ANULADO PREVIAMENTE</RESULTADO>'],
            'estado vago' => ['OK'],
            'texto ambiguo' => ['ERROR: timeout de comunicación'],
            'soap fault' => ['<Envelope><Body><Fault><faultstring>ERROR</faultstring></Fault></Body></Envelope>'],
            'envoltorio ajeno' => ['<otro>'.$base.'</otro>'],
        ];
    }

    public function test_rechazo_funcional_es_distinto_del_resultado_incierto(): void
    {
        $this->assertSame('RECHAZADA', $this->procesar('<RESULTADO>ERROR en los datos enviados, campo FECHA_ANULACION</RESULTADO>'));
        $this->assertSame('INCIERTA', $this->procesar(str_repeat('x', FelAnulacionRespuestaParserService::MAXIMO_BYTES + 1)));
    }

    private function procesar(string $respuesta): string
    {
        return (new FelAnulacionRespuestaParserService)->procesar($respuesta, self::SOLICITUD)['estado'];
    }

    private function acuse(): string
    {
        return '<RESULTADO><ESTADO>ANULADO</ESTADO><EMISOR>1234567K</EMISOR><COMPRADOR>CF</COMPRADOR><SERIE>abc123</SERIE><PREIMPRESO>123</PREIMPRESO></RESULTADO>';
    }

    private function sobre(string $contenido): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><anulaDocumentoResponse><return>'.$contenido.'</return></anulaDocumentoResponse></s:Body></s:Envelope>';
    }
}
