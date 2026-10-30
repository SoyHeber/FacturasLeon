<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class FelAnulacionRespuestaParserService
{
    public const MAXIMO_BYTES = 1048576;

    /**
     * Interpreta RESULTADO directo, SOAP o un string XML escapado, sin resolver entidades.
     * Solo acredita ANULADO cuando emisor, comprador, serie y número coinciden con la
     * solicitud histórica. El error de documento previamente anulado permanece incierto:
     * no sustituye el acuse y no autoriza un reintegro de inventario por sí solo.
     */
    public function procesar(?string $respuesta, array $solicitud): array
    {
        if ($respuesta === null || strlen($respuesta) > self::MAXIMO_BYTES) {
            return $this->incierta('La respuesta de anulación está vacía o excede el tamaño permitido.');
        }
        $texto = trim(preg_replace('/^\xEF\xBB\xBF/', '', $respuesta));
        for ($nivel = 0; $nivel < 5; $nivel++) {
            if ($texto === '') {
                break;
            }
            if (! str_starts_with($texto, '<')) {
                $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($decodificado !== $texto && str_starts_with(ltrim($decodificado), '<')) {
                    $texto = ltrim($decodificado);
                    continue;
                }

                return $this->rechazoOIncierta($texto);
            }
            $dom = new DOMDocument;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $dom->validateOnParse = false;
            $anterior = libxml_use_internal_errors(true);
            try {
                $valido = $dom->loadXML($texto, LIBXML_NONET) && $dom->doctype === null;
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($anterior);
            }
            if (! $valido || ! $dom->documentElement) {
                return $this->incierta('La respuesta de anulación contiene XML inválido o entidades no permitidas.');
            }
            $raiz = $dom->documentElement;
            if ($raiz->localName === 'RESULTADO') {
                return $this->resultado($raiz, $solicitud);
            }
            $xpath = new DOMXPath($dom);
            if ($raiz->localName === 'Envelope'
                && ($raiz->namespaceURI !== 'http://schemas.xmlsoap.org/soap/envelope/'
                    || $xpath->query('/*/*[local-name()="Body"]')->length !== 1)) {
                return $this->incierta('El sobre no corresponde a una respuesta SOAP 1.1 única.');
            }
            if ($xpath->query('//*[local-name()="Fault"]')->length !== 0) {
                return $this->incierta('El servicio devolvió un SOAP Fault; no confirma la anulación.');
            }
            $campoRetorno = '*[local-name()="return" or local-name()="result" or local-name()="anulaDocumentoReturn" or local-name()="anulaDocumentoResult"]';
            $retornos = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*[local-name()="anulaDocumentoResponse"]/'.$campoRetorno.' | /*[local-name()="anulaDocumentoResponse"]/'.$campoRetorno);
            if ($retornos->length !== 1) {
                return $this->incierta('La respuesta no identifica un único resultado de anulaDocumento.');
            }
            $retorno = $retornos->item(0);
            $hijos = array_values(array_filter(iterator_to_array($retorno->childNodes), fn ($nodo) => $nodo instanceof DOMElement));
            if (count($hijos) > 1) {
                return $this->incierta('La respuesta contiene varios resultados de anulación.');
            }
            if ($hijos && trim($retorno->textContent) !== trim($hijos[0]->textContent)) {
                return $this->incierta('El retorno contiene texto adicional al acuse de anulación.');
            }
            $texto = trim($hijos ? $dom->saveXML($hijos[0]) : $retorno->textContent);
        }

        return $this->incierta('La respuesta no contiene una confirmación de anulación reconocible.');
    }

    private function resultado(DOMElement $raiz, array $solicitud): array
    {
        $elementos = array_values(array_filter(iterator_to_array($raiz->childNodes), fn ($nodo) => $nodo instanceof DOMElement));
        if (! $elementos) {
            return $this->rechazoOIncierta(trim($raiz->textContent));
        }
        foreach ($raiz->childNodes as $nodo) {
            if ($nodo instanceof \DOMText && trim($nodo->textContent) !== '') {
                return $this->incierta('El resultado contiene texto adicional a sus campos.');
            }
        }
        $campos = [];
        foreach ($elementos as $elemento) {
            if (isset($campos[$elemento->localName]) || $elemento->getElementsByTagName('*')->length !== 0) {
                return $this->incierta('La respuesta contiene campos duplicados o estructuras inesperadas.');
            }
            $campos[$elemento->localName] = trim($elemento->textContent);
        }
        if (count($campos) !== 5 || ($campos['ESTADO'] ?? null) !== 'ANULADO') {
            return $this->incierta('El resultado no confirma ESTADO ANULADO.');
        }
        foreach (['EMISOR' => 'nit_emisor', 'COMPRADOR' => 'nit_comprador', 'SERIE' => 'serie', 'PREIMPRESO' => 'numero'] as $campo => $clave) {
            if (! isset($campos[$campo], $solicitud[$clave]) || $campos[$campo] === ''
                || $this->normalizar($campo, $campos[$campo]) !== $this->normalizar($campo, (string) $solicitud[$clave])) {
                return $this->incierta('El '.$campo.' de la respuesta no coincide con la factura solicitada.');
            }
        }

        return ['estado' => 'CONFIRMADA', 'mensaje' => 'Ainnova confirmó la anulación del documento solicitado.'];
    }

    private function normalizar(string $campo, string $valor): string
    {
        $valor = strtoupper(trim($valor));
        if (in_array($campo, ['EMISOR', 'COMPRADOR'], true)) {
            return preg_replace('/[\s-]+/', '', $valor);
        }
        if ($campo === 'PREIMPRESO' && preg_match('/^[0-9]+$/D', $valor)) {
            return ltrim($valor, '0') ?: '0';
        }

        return $valor;
    }

    private function rechazoOIncierta(string $texto): array
    {
        if (preg_match('/timeout|timed\s*out|comunicaci[oó]n|conexi[oó]n|connection|interrump|transport|intern[oa]|servidor|server|resultado.*(?:incierto|desconocido)/iu', $texto)) {
            return $this->incierta('La respuesta describe un fallo de comunicación, no un rechazo definitivo.');
        }
        if (preg_match('/anulad[oa]\s+previamente|previamente\s+anulad[oa]|ya\s+(?:fue\s+|est[aá]\s+)?anulad[oa]|\b94\b/iu', $texto)) {
            return $this->incierta('Ainnova informa un documento previamente anulado; se requiere el acuse para conciliar.');
        }
        if (preg_match('/^\s*(?:ERROR\b|RECHAZ(?:O|AD[OA])\b)/iu', $texto)) {
            return ['estado' => 'RECHAZADA', 'mensaje' => mb_substr($texto, 0, 1000)];
        }

        return $this->incierta('La respuesta no acredita ni una anulación ni un rechazo definitivo.');
    }

    private function incierta(string $mensaje): array
    {
        return ['estado' => 'INCIERTA', 'mensaje' => $mensaje];
    }
}
