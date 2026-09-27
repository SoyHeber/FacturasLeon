<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
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

    public function create()
    {
        $compras = Compra::where('estado', true)
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        return view(
            'detalles_compra.create',
            compact(
                'compras',
                'inventariosCompra'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'compra_id' => [
                'required',
                'exists:compras,id',
            ],

            'inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'porcentaje_descuento' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'importe_bruto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_descuento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_exento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_otros' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_neto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_iva' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_total' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Calcular automáticamente numero_linea
        |--------------------------------------------------------------------------
        |
        | Busca la última línea de la compra seleccionada.
        | Si no existen detalles, comienza en 1.
        |
        */
        $ultimaLinea = DetalleCompra::where(
            'compra_id',
            $validated['compra_id']
        )->max('numero_linea');

        $numeroLinea = ($ultimaLinea ?? 0) + 1;

        DetalleCompra::create([
            'compra_id' =>
            $validated['compra_id'],

            'numero_linea' =>
            $numeroLinea,

            'inventario_compra_id' =>
            $validated['inventario_compra_id'],

            'cantidad' =>
            $validated['cantidad'],

            'precio_unitario' =>
            $validated['precio_unitario'],

            'porcentaje_descuento' =>
            $validated['porcentaje_descuento'],

            'importe_bruto' =>
            $validated['importe_bruto'],

            'importe_descuento' =>
            $validated['importe_descuento'],

            'importe_exento' =>
            $validated['importe_exento'],

            'importe_otros' =>
            $validated['importe_otros'],

            'importe_neto' =>
            $validated['importe_neto'],

            'importe_iva' =>
            $validated['importe_iva'],

            'importe_total' =>
            $validated['importe_total'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

        return redirect()
            ->route('detalles_compra.index')
            ->with(
                'success',
                'Detalle de compra registrado correctamente.'
            );
    }

    public function show(DetalleCompra $detalleCompra)
    {
        $detalleCompra->load([
            'compra.proveedor',
            'inventarioCompra',
        ]);

        return view(
            'detalles_compra.show',
            compact('detalleCompra')
        );
    }

    public function edit(DetalleCompra $detalleCompra)
    {
        $compras = Compra::where('estado', true)
            ->orWhere(
                'id',
                $detalleCompra->compra_id
            )
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orWhere(
                'id',
                $detalleCompra->inventario_compra_id
            )
            ->orderBy('nombre')
            ->get();

        return view(
            'detalles_compra.edit',
            compact(
                'detalleCompra',
                'compras',
                'inventariosCompra'
            )
        );
    }

    public function update(
        Request $request,
        DetalleCompra $detalleCompra
    ) {
        $validated = $request->validate([
            'compra_id' => [
                'required',
                'exists:compras,id',
            ],

            'inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'porcentaje_descuento' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'importe_bruto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_descuento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_exento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_otros' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_neto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_iva' => [
                'required',
                'numeric',
                'min:0',
            ],

            'importe_total' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Si cambia de compra
        |--------------------------------------------------------------------------
        |
        | Si el detalle se mueve a otra compra, le asignamos
        | automáticamente la siguiente línea disponible.
        |
        */
        $numeroLinea = $detalleCompra->numero_linea;

        if (
            (int) $detalleCompra->compra_id !==
            (int) $validated['compra_id']
        ) {
            $ultimaLinea = DetalleCompra::where(
                'compra_id',
                $validated['compra_id']
            )->max('numero_linea');

            $numeroLinea = ($ultimaLinea ?? 0) + 1;
        }

        $detalleCompra->update([
            'compra_id' =>
            $validated['compra_id'],

            'numero_linea' =>
            $numeroLinea,

            'inventario_compra_id' =>
            $validated['inventario_compra_id'],

            'cantidad' =>
            $validated['cantidad'],

            'precio_unitario' =>
            $validated['precio_unitario'],

            'porcentaje_descuento' =>
            $validated['porcentaje_descuento'],

            'importe_bruto' =>
            $validated['importe_bruto'],

            'importe_descuento' =>
            $validated['importe_descuento'],

            'importe_exento' =>
            $validated['importe_exento'],

            'importe_otros' =>
            $validated['importe_otros'],

            'importe_neto' =>
            $validated['importe_neto'],

            'importe_iva' =>
            $validated['importe_iva'],

            'importe_total' =>
            $validated['importe_total'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

        return redirect()
            ->route('detalles_compra.index')
            ->with(
                'success',
                'Detalle de compra actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        DetalleCompra $detalleCompra
    ) {
        $detalleCompra->update([
            'estado' => !$detalleCompra->estado,
        ]);

        return redirect()
            ->route('detalles_compra.index')
            ->with(
                'success',
                'Estado actualizado correctamente.'
            );
    }
}
