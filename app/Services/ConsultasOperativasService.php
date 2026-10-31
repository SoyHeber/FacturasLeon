<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultasOperativasService
{
    /**
     * Base compartida por Dashboard y reportes: conserva las ventas que obtuvieron
     * certificación, incluso anuladas. Solo vincula anulaciones fiscales confirmadas
     * con fecha de confirmación; las inciertas y cancelaciones locales quedan fuera.
     * Devuelve un constructor sin ejecutarlo ni cargar XML; no llama a Ainnova.
     */
    public function ventasCertificadas(): Builder
    {
        return DB::table('ventas as v')->join('documentos_fel as d', 'd.venta_id', '=', 'v.id')
            ->leftJoin('anulaciones_venta as a', function ($join) {
                $join->on('a.venta_id', '=', 'v.id')->where('a.tipo', 'FEL')
                    ->whereIn('a.estado', ['CONFIRMADA', 'APLICADA'])->whereNotNull('a.fecha_confirmacion');
            })
            ->whereIn('v.estado_venta', ['CONFIRMADA', 'ANULADA'])->whereIn('d.estado_fel', ['CERTIFICADA', 'ANULADA'])
            ->whereNotNull('d.fel_uuid')->where('d.fel_uuid', '<>', '')
            ->whereNotNull('d.xml_certificado')->where('d.xml_certificado', '<>', '')
            ->whereNotNull('d.fecha_certificacion')->whereNotNull('d.fecha_hora_emision');
    }

    /** Aplica la validación al alias c, conservando los filtros del constructor recibido. */
    public function comprasValidadas(?Builder $consulta = null): Builder
    {
        return ($consulta ?? DB::table('compras as c'))->where('c.inventario_aplicado', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('detalles_compra as dc')
                ->whereColumn('dc.compra_id', 'c.id')->where('dc.estado', true));
    }

    /** Producciones que ingresaron producto terminado; excluye borradores y legadas. */
    public function produccionesConfirmadas(): Builder
    {
        return DB::table('producciones as pr')->whereIn('pr.estado_produccion', ['CONFIRMADA', 'ANULADA'])
            ->where('pr.inventario_aplicado', true)->where('pr.producto_terminado_aplicado', true)
            ->whereNotNull('pr.fecha_confirmacion');
    }
}
