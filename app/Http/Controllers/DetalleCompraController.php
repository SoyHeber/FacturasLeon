<?php

namespace App\Http\Controllers;

use App\Models\DetalleCompra;
use Illuminate\Http\Request;

class DetalleCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = DetalleCompra::with([
            'compra.proveedor',
            'inventarioCompra',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('compra_id')) {
            $query->where('compra_id', $request->compra_id);
        }

        if ($request->filled('numero_linea')) {
            $query->where('numero_linea', $request->numero_linea);
        }

        if ($request->filled('inventario_compra')) {
            $query->whereHas('inventarioCompra', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%' . $request->inventario_compra . '%'
                );
            });
        }

        if ($request->filled('cantidad')) {
            $query->where('cantidad', $request->cantidad);
        }

        if ($request->filled('precio_unitario')) {
            $query->where(
                'precio_unitario',
                $request->precio_unitario
            );
        }

        if ($request->filled('porcentaje_descuento')) {
            $query->where(
                'porcentaje_descuento',
                $request->porcentaje_descuento
            );
        }

        if ($request->filled('importe_total')) {
            $query->where(
                'importe_total',
                $request->importe_total
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
            );
        }

        $detallesCompra = $query
            ->orderBy('compra_id', 'desc')
            ->orderBy('numero_linea')
            ->paginate(10)
            ->withQueryString();

        return view(
            'detalles_compra.index',
            compact('detallesCompra')
        );
    }

    public function show(DetalleCompra $detalleCompra)
    {
        $detalleCompra->load([
            'compra.proveedor',
            'compra.tipoDocumento',
            'inventarioCompra',
        ]);

        return view(
            'detalles_compra.show',
            compact('detalleCompra')
        );
    }

}
