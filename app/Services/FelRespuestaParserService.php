<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class FelRespuestaParserService
{
    public const NAMESPACE = 'http://www.sat.gob.gt/dte/fel/0.2.0';

    public function procesar(?string $texto, string $nitEmisor, string $totalVenta, int $nivel = 0): array
    {
        if ($nivel > 3) {
            return $this->incierta('La respuesta contiene demasiados envoltorios XML.');
        }
        if ($texto === null || trim($texto) === '') {
            return $this->incierta('Ainnova devolvió una respuesta vacía o ilegible.');
        }
        if (! $this->pareceXml($texto)) {
            return $this->rechazoInequivoco($texto)
                ? $this->error(null, trim($texto))
                : $this->incierta('La respuesta textual no confirma certificación ni rechazo definitivo.');
        }
        $texto = $this->quitarDeclaracionInterna($texto);
        $dom = $this->cargarXml($texto);
        if ($dom === null) {
            return $this->incierta('El XML recibido es inválido o contiene declaraciones de entidades.');
        }
        $xpath = new DOMXPath($dom);
        if ($dom->documentElement->localName === 'Envelope') {
            if ($dom->documentElement->namespaceURI !== 'http://schemas.xmlsoap.org/soap/envelope/') {
                return $this->incierta('El Envelope recibido no corresponde a SOAP 1.1.');
            }
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $resultados = $xpath->query('/soap:Envelope/soap:Body/*[local-name()="generaDocumentoResponse"]/*[local-name()="result" or local-name()="return" or local-name()="generaDocumentoReturn" or local-name()="generaDocumentoResult"]');
            if ($xpath->query('/soap:Envelope/soap:Body')->length !== 1
                || $xpath->query('/soap:Envelope/soap:Body/soap:Fault')->length !== 0 || $resultados->length !== 1) {
                return $this->incierta('El SOAP recibido no identifica un único resultado de generaDocumento.');
            }

            return $this->procesar($this->contenido($texto, $resultados->item(0)), $nitEmisor, $totalVenta, $nivel + 1);
        }
        if ($dom->documentElement->localName === 'Resultado' && $dom->documentElement->childElementCount === 0
            && $this->pareceXml($dom->documentElement->textContent)) {
            return $this->procesar($this->contenido($texto, $dom->documentElement), $nitEmisor, $totalVenta, $nivel + 1);
        }
        $xpath->registerNamespace('fel', self::NAMESPACE);
        $documentos = $xpath->query('//fel:GTDocumento');
        if ($documentos->length === 0) {
            if ($xpath->query('//*[local-name()="GTDocumento"]')->length > 0) {
                return $this->incierta('El supuesto certificado tiene un namespace FEL incompatible.');
            }

            return $this->procesarErrorXml($dom, $xpath);
        }
        if ($documentos->length !== 1 || ! in_array($dom->documentElement->localName, ['GTDocumento', 'Resultado'], true)) {
            return $this->incierta('La respuesta no identifica un único documento FEL certificado.');
        }
        $documento = $documentos->item(0);
        $certificado = $dom->documentElement === $documento ? $texto : ($this->fragmento($texto, $documento)['xml'] ?? null);
        if ($certificado === null || $this->cargarXml($certificado) === null) {
            return $this->incierta('No se pudo aislar el GTDocumento certificado completo.');
        }
        $base = 'fel:SAT/fel:DTE/';
        $tipo = $this->valor($xpath, $base.'fel:DatosEmision/fel:DatosGenerales/@Tipo', $documento);
        $nit = $this->valor($xpath, $base.'fel:DatosEmision/fel:Emisor/@NITEmisor', $documento);
        $total = $this->valor($xpath, $base.'fel:DatosEmision/fel:Totales/fel:GranTotal', $documento);
        $uuid = $this->valor($xpath, $base.'fel:Certificacion/fel:NumeroAutorizacion', $documento);
        $serie = $this->valor($xpath, $base.'fel:Certificacion/fel:NumeroAutorizacion/@Serie', $documento);
        $numero = $this->valor($xpath, $base.'fel:Certificacion/fel:NumeroAutorizacion/@Numero', $documento);
        $fecha = $this->valor($xpath, $base.'fel:Certificacion/fel:FechaHoraCertificacion', $documento);
        $nitCertificador = $this->valor($xpath, $base.'fel:Certificacion/fel:NITCertificador', $documento);
        $nombreCertificador = $this->valor($xpath, $base.'fel:Certificacion/fel:NombreCertificador', $documento);
        foreach ([$tipo, $nit, $total, $uuid, $serie, $numero, $fecha, $nitCertificador, $nombreCertificador] as $valor) {
            if ($valor === null || trim($valor) === '') {
                return $this->incierta('El XML certificado está incompleto o tiene datos duplicados.');
            }
        }
        if (trim($tipo) !== 'FACT' || $this->normalizarNit($nit) !== $this->normalizarNit($nitEmisor)) {
            return $this->incierta('El tipo de documento o NIT emisor certificado no coincide.');
        }
        if (! preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', trim($total)) || strlen($total) > 80) {
            return $this->incierta('El total certificado no es un decimal válido.');
        }
        try {
            if (! BigDecimal::of(trim($total))->isEqualTo(BigDecimal::of($totalVenta))) {
                return $this->incierta('El total certificado no coincide exactamente con la Venta.');
            }
        } catch (Throwable) {
            return $this->incierta('No se pudo validar el total certificado.');
        }
        $fechaCertificacion = $this->fechaValida(trim($fecha));
        if ($fechaCertificacion === null) {
            return $this->incierta('La fecha de certificación no es válida.');
        }
        foreach ([[$uuid, 36], [$serie, 50], [$numero, 50], [$nitCertificador, 30], [$nombreCertificador, 200]] as [$valor, $maximo]) {
            if (mb_strlen($valor, 'UTF-8') > $maximo) {
                return $this->incierta('Un identificador certificado excede la longitud admitida.');
            }
        }

        return ['estado' => 'CERTIFICADA', 'campos' => [
            'xml_certificado' => $certificado, 'fel_uuid' => $uuid, 'fel_uuid_normalized' => strtoupper(trim($uuid)),
            'fel_serie' => $serie, 'fel_numero' => $numero,
            'fecha_certificacion' => $fechaCertificacion->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'nit_certificador' => $nitCertificador, 'nombre_certificador' => $nombreCertificador,
        ], 'codigo_error' => null, 'mensaje_error' => null, 'error_tecnico' => null];
    }

    private function quitarDeclaracionInterna(string $texto): string
    {
        // Solo se quita la declaración inmediata dentro de Resultado; el GTDocumento conserva sus bytes.
        return preg_replace('~^(\xEF\xBB\xBF)?(\s*(?:<\?xml\s[^?]*\?>\s*)?<(?:[\w.-]+:)?Resultado\b[^>]*>\s*)<\?xml\s[^?]*\?>~u', '$1$2', $texto, 1) ?? $texto;
    }

    private function contenido(string $xml, DOMElement $elemento): ?string
    {
        $contenido = $this->fragmento($xml, $elemento)['contenido'] ?? null;
        if ($contenido === null || $elemento->childElementCount > 0) {
            return $contenido;
        }
        $partes = preg_split('~(<!\[CDATA\[.*?\]\]>|<!--.*?-->|<\?.*?\?>)~s', $contenido, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($partes === false) {
            return null;
        }
        $payload = '';
        foreach ($partes as $parte) {
            if (str_starts_with($parte, '<![CDATA[')) {
                $payload .= substr($parte, 9, -3);
            } elseif (! str_starts_with($parte, '<!--') && ! str_starts_with($parte, '<?')) {
                // Una sola decodificación XML por capa de texto; CDATA no se decodifica.
                $payload .= html_entity_decode($parte, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return $payload;
    }

    private function fragmento(string $xml, DOMElement $elemento): ?array
    {
        $xpath = new DOMXPath($elemento->ownerDocument);
        $posicion = (int) $xpath->evaluate('count(preceding::* | ancestor::*)', $elemento) + 1;
        $cantidad = preg_match_all('~<!--.*?-->|<!\[CDATA\[.*?\]\]>|<\?.*?\?>|<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~s', $xml, $tokens, PREG_OFFSET_CAPTURE);
        if ($cantidad === false) {
            return null;
        }
        $indice = 0;
        $profundidad = 0;
        $inicio = null;
        $inicioContenido = null;
        $nivel = null;
        foreach ($tokens[0] as [$token, $offset]) {
            if (str_starts_with($token, '<!') || str_starts_with($token, '<?')) {
                continue;
            }
            if (str_starts_with($token, '</')) {
                if ($inicio !== null && $profundidad === $nivel) {
                    return ['xml' => substr($xml, $inicio, $offset + strlen($token) - $inicio),
                        'contenido' => substr($xml, $inicioContenido, $offset - $inicioContenido)];
                }
                $profundidad--;
            } else {
                $indice++;
                $vacio = str_ends_with($token, '/>');
                if ($indice === $posicion) {
                    if ($vacio) {
                        return ['xml' => $token, 'contenido' => ''];
                    }
                    $inicio = $offset;
                    $inicioContenido = $offset + strlen($token);
                    $nivel = $profundidad + 1;
                }
                if (! $vacio) {
                    $profundidad++;
                }
            }
        }

        return null;
    }

    private function cargarXml(string $xml): ?DOMDocument
    {
        if (strlen($xml) > 20000000 || ! mb_check_encoding($xml, 'UTF-8') || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            return null;
        }
        $anterior = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $dom->validateOnParse = false;

            return $dom->loadXML($xml, LIBXML_NONET) && $dom->doctype === null ? $dom : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }

    private function pareceXml(string $texto): bool
    {
        return str_starts_with(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $texto)), '<');
    }

    private function valor(DOMXPath $xpath, string $ruta, DOMElement $contexto): ?string
    {
        $nodos = $xpath->query($ruta, $contexto);

        return $nodos->length === 1 ? $nodos->item(0)->nodeValue : null;
    }

    private function fechaValida(string $fecha): ?DateTimeImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-3]):[0-5]\d|[+-]14:00)$/D', $fecha)) {
            return null;
        }
        $formato = str_contains($fecha, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $valor = DateTimeImmutable::createFromFormat($formato, $fecha);
        $errores = DateTimeImmutable::getLastErrors();

        return $valor !== false && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0)) ? $valor : null;
    }

    private function normalizarNit(string $nit): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $nit));
    }

    private function procesarErrorXml(DOMDocument $dom, DOMXPath $xpath): array
    {
        $errores = $xpath->query('//*[local-name()="Error" or local-name()="error"]');
        $contexto = $errores->length === 1 ? $errores->item(0) : $dom->documentElement;
        $mensajes = $xpath->query('.//*[local-name()="Mensaje" or local-name()="mensaje" or local-name()="Descripcion" or local-name()="descripcion"]', $contexto);
        $mensaje = trim($mensajes->length === 1 ? $mensajes->item(0)->textContent : $contexto->textContent);
        $esError = in_array($contexto->localName, ['Error', 'error'], true);
        if ($mensaje === '' || $this->errorAmbiguo($mensaje) || (! $esError && ! $this->rechazoInequivoco($mensaje))) {
            return $this->incierta('El XML recibido no confirma certificación ni rechazo definitivo.');
        }
        $codigos = $xpath->query('.//*[local-name()="Codigo" or local-name()="codigo" or local-name()="CodigoError" or local-name()="codigo_error"]', $contexto);
        $codigo = $codigos->length === 1 ? trim($codigos->item(0)->textContent) : null;
        if ($codigo !== null && ! preg_match('/^[\p{L}\p{N}_.:-]{1,100}$/uD', $codigo)) {
            $codigo = null;
        }

        return $this->error($codigo, $mensaje);
    }

    private function rechazoInequivoco(string $mensaje): bool
    {
        return ! $this->errorAmbiguo($mensaje) && (bool) preg_match('/^\s*(?:ERROR\s*[:\-]|RECHAZ(?:O|AD[OA])\b|(?:EL\s+)?(?:DOCUMENTO|FACTURA)\s+(?:FUE\s+)?RECHAZAD[OA]\b)/iu', $mensaje);
    }

    private function errorAmbiguo(string $mensaje): bool
    {
        return (bool) preg_match('/timeout|timed\s*out|conexi[oó]n|connection|interrump|transport|intern[oa]|servidor|server/iu', $mensaje);
    }

    private function error(?string $codigo, string $mensaje): array
    {
        return ['estado' => 'ERROR', 'campos' => [], 'codigo_error' => $codigo,
            'mensaje_error' => mb_substr($mensaje, 0, 4000), 'error_tecnico' => null];
    }

    private function incierta(string $mensaje): array
    {
        return ['estado' => 'INCIERTA', 'campos' => [], 'codigo_error' => null, 'mensaje_error' => null, 'error_tecnico' => $mensaje];
    }
}
