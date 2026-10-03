<?php

namespace App\Services;

use App\Models\DocumentoFel;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DOMDocument;
use DOMElement;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FelXmlBuilderService
{
    public function generarFactura(Venta $venta, DocumentoFel $documento): string
    {
        if ($venta->moneda !== 'GTQ' || $documento->p_tipo_doc !== DocumentoFel::TIPOS_AINNOVA['FACT']
            || (int) $documento->venta_id !== (int) $venta->id
            || (int) $documento->tipo_documento_id !== (int) $venta->tipo_documento_id) {
            $this->rechazar('Solo se puede generar FACT en GTQ para el documento de esta venta.');
        }
        $referencia = $documento->referencia;
        if (! is_string($referencia) || trim($referencia) === '' || mb_strlen($referencia, 'UTF-8') > DocumentoFel::LONGITUD_REFERENCIA) {
            $this->rechazar('La referencia FEL existente debe tener entre 1 y 20 caracteres. No se modificó la referencia.');
        }
        $identificacion = $venta->receptor_identificacion;
        $codigoLocal = trim($venta->receptor_tipo_identificacion_codigo);
        $tipo = config('fel.tipos_identificacion_locales', [])[$codigoLocal] ?? null;
        if (strtoupper(trim($identificacion)) === 'CF') {
            $identificacion = 'CF';
            $tipo = 'CF';
        }
        $tipoReceptor = config('fel.tipos_receptor_ainnova', [])[$tipo ?? ''] ?? null;
        if ($tipoReceptor === null) {
            $this->rechazar('El tipo de identificación histórico no tiene un mapeo Ainnova soportado.');
        }
        foreach ([$identificacion, $venta->receptor_nombre, $venta->receptor_direccion] as $dato) {
            if (trim($dato) === '') {
                $this->rechazar('El receptor necesita identificación, nombre y dirección históricos.');
            }
        }
        if ($venta->detalles->isEmpty()) {
            $this->rechazar('La venta necesita al menos un detalle.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $raiz = $dom->appendChild($dom->createElement('DocElectronico'));
        $encabezado = $raiz->appendChild($dom->createElement('Encabezado'));
        $this->grupo($dom, $encabezado, 'Receptor', [
            'NITReceptor' => $identificacion, 'Nombre' => $venta->receptor_nombre, 'Direccion' => $venta->receptor_direccion,
        ]);
        $this->grupo($dom, $encabezado, 'InfoDoc', [
            'TipoVenta' => 'B', 'DestinoVenta' => '1', 'Fecha' => $venta->fecha->format('d/m/Y'),
            'Moneda' => '1', 'Tasa' => '1', 'Referencia' => $referencia,
            'NumeroAcceso' => '', 'SerieAdmin' => '', 'NumeroAdmin' => '', 'Reversion' => '',
        ]);
        $this->grupo($dom, $encabezado, 'Totales', [
            'Bruto' => $venta->importe_bruto, 'Descuento' => $venta->importe_descuento,
            'Exento' => $venta->importe_exento, 'Otros' => '0.00', 'Neto' => $venta->importe_neto,
            'Isr' => '0.00', 'Iva' => $venta->importe_iva, 'Total' => $venta->importe_total,
        ]);
        $this->grupo($dom, $encabezado, 'DatosAdicionales', ['TipoReceptor' => $tipoReceptor]);
        $detalles = $raiz->appendChild($dom->createElement('Detalles'));
        foreach ($venta->detalles as $detalle) {
            if ($detalle->bien_servicio !== 'B' || $detalle->unidad_medida !== 'UNI') {
                $this->rechazar('Solo se admiten productos terminados de clase B y unidad UNI.');
            }
            $this->grupo($dom, $detalles, 'Productos', [
                'Producto' => $detalle->producto_codigo, 'Descripcion' => $detalle->descripcion,
                'Medida' => '1', 'Cantidad' => $detalle->cantidad,
                'Precio' => (string) BigDecimal::of($detalle->precio_unitario)->toScale(6, RoundingMode::UNNECESSARY),
                'PorcDesc' => $detalle->porcentaje_descuento, 'ImpBruto' => $detalle->importe_bruto,
                'ImpDescuento' => $detalle->importe_descuento, 'ImpExento' => $detalle->importe_exento,
                'ImpOtros' => '0.00', 'ImpNeto' => $detalle->importe_neto, 'ImpIsr' => '0.00',
                'ImpIva' => $detalle->importe_iva, 'ImpTotal' => $detalle->importe_total, 'TipoVentaDet' => 'B',
            ]);
        }
        $xml = $dom->saveXML(null, LIBXML_NOEMPTYTAG);
        if ($xml === false) {
            throw new RuntimeException('No se pudo generar el XML FACT.');
        }

        return $xml;
    }

    private function grupo(DOMDocument $dom, DOMElement $padre, string $nombre, array $datos): void
    {
        $grupo = $padre->appendChild($dom->createElement($nombre));
        foreach ($datos as $etiqueta => $valor) {
            if (! is_string($valor) || ! mb_check_encoding($valor, 'UTF-8')
                || preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $valor)) {
                $this->rechazar('Un dato histórico contiene caracteres no válidos para XML.');
            }
            $elemento = $grupo->appendChild($dom->createElement($etiqueta));
            $elemento->appendChild($dom->createTextNode($valor));
        }
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['venta' => $mensaje]);
    }
}
