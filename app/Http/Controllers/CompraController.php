<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with([
            'proveedor',
            'user',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('proveedor')) {
            $query->whereHas('proveedor', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%' . $request->proveedor . '%'
                );
            });
        }

        if ($request->filled('tipo_dte')) {
            $query->where(
                'tipo_dte',
                'like',
                '%' . $request->tipo_dte . '%'
            );
        }

        if ($request->filled('serie')) {
            $query->where(
                'serie',
                'like',
                '%' . $request->serie . '%'
            );
        }

        if ($request->filled('numero')) {
            $query->where(
                'numero',
                $request->numero
            );
        }

        if ($request->filled('numero_autorizacion')) {
            $query->where(
                'numero_autorizacion',
                'like',
                '%' . $request->numero_autorizacion . '%'
            );
        }

        if ($request->filled('fecha_emision')) {
            $query->whereDate(
                'fecha_emision',
                $request->fecha_emision
            );
        }

        if ($request->filled('moneda')) {
            $query->where(
                'moneda',
                $request->moneda
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

        $compras = $query
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'compras.index',
            compact('compras')
        );
    }

    public function create()
    {
        $proveedores = Proveedor::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'compras.create',
            compact('proveedores')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],

            'tipo_dte' => [
                'required',
                Rule::in([
                    'FACT',
                ]),
            ],

            'serie' => [
                'required',
                'string',
                'max:20',
            ],

            'numero' => [
                'required',
                'integer',
                'min:0',
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',
                'unique:compras,numero_autorizacion',
            ],

            'fecha_emision' => [
                'required',
                'date',
            ],

            'fecha_certificacion' => [
                'nullable',
                'date',
            ],

            'moneda' => [
                'required',
                Rule::in([
                    'GTQ',
                    'USD',
                ]),
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

        Compra::create([
            'proveedor_id' =>
            $validated['proveedor_id'],

            'user_id' =>
            auth()->id(),

            'tipo_dte' =>
            strtoupper($validated['tipo_dte']),

            'serie' =>
            strtoupper($validated['serie']),

            'numero' =>
            $validated['numero'],

            'numero_autorizacion' =>
            strtoupper(
                $validated['numero_autorizacion']
            ),

            'fecha_emision' =>
            $validated['fecha_emision'],

            'fecha_certificacion' =>
            $validated['fecha_certificacion'] ?? null,

            'moneda' =>
            strtoupper($validated['moneda']),

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
            ->route('compras.index')
            ->with(
                'success',
                'Compra registrada correctamente.'
            );
    }

    public function show(Compra $compra)
    {
        $compra->load([
            'proveedor',
            'user',
        ]);

        return view(
            'compras.show',
            compact('compra')
        );
    }

    public function edit(Compra $compra)
    {
        $proveedores = Proveedor::where('estado', true)
            ->orWhere('id', $compra->proveedor_id)
            ->orderBy('nombre')
            ->get();

        return view(
            'compras.edit',
            compact(
                'compra',
                'proveedores'
            )
        );
    }

    public function update(
        Request $request,
        Compra $compra
    ) {
        $validated = $request->validate([
            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],

            'tipo_dte' => [
                'required',
                Rule::in([
                    'FACT',
                ]),
            ],

            'serie' => [
                'required',
                'string',
                'max:20',
            ],

            'numero' => [
                'required',
                'integer',
                'min:0',
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',

                Rule::unique(
                    'compras',
                    'numero_autorizacion'
                )->ignore($compra->id),
            ],

            'fecha_emision' => [
                'required',
                'date',
            ],

            'fecha_certificacion' => [
                'nullable',
                'date',
            ],

            'moneda' => [
                'required',
                Rule::in([
                    'GTQ',
                    'USD',
                ]),
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

        $compra->update([
            'proveedor_id' =>
            $validated['proveedor_id'],

            'tipo_dte' =>
            strtoupper($validated['tipo_dte']),

            'serie' =>
            strtoupper($validated['serie']),

            'numero' =>
            $validated['numero'],

            'numero_autorizacion' =>
            strtoupper(
                $validated['numero_autorizacion']
            ),

            'fecha_emision' =>
            $validated['fecha_emision'],

            'fecha_certificacion' =>
            $validated['fecha_certificacion'] ?? null,

            'moneda' =>
            strtoupper($validated['moneda']),

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
            ->route('compras.index')
            ->with(
                'success',
                'Compra actualizada correctamente.'
            );
    }

    public function cambiarEstado(Compra $compra)
    {
        $compra->update([
            'estado' => !$compra->estado,
        ]);

        return redirect()
            ->route('compras.index')
            ->with(
                'success',
                'Estado de la compra actualizado correctamente.'
            );
    }
}
