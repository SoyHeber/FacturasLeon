<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\InventarioCompra;
use App\Models\TipoDocumento;
use App\Services\CompraInventarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with([
            'proveedor',
            'user',
            'anuladoPor',
            'tipoDocumento',
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

        if ($request->filled('tipo_documento')) {
            $query->whereHas('tipoDocumento', function ($tipo) use ($request) {
                $tipo->where('codigo', 'like', '%' . $request->tipo_documento . '%')
                    ->orWhere('nombre', 'like', '%' . $request->tipo_documento . '%');
            });
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
        $tiposDocumento = TipoDocumento::where('codigo', 'FACT')
            ->where('estado', true)
            ->get();

        $proveedores = Proveedor::with('direccion')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        return view(
            'compras.create',
            compact(
                'proveedores',
                'tiposDocumento',
                'inventariosCompra'
            )
        );
    }


    public function store(
        Request $request,
        CompraInventarioService $compraInventarioService
    ) {
        foreach (['serie', 'numero_autorizacion'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => strtoupper($request->input($campo))]);
            }
        }

        $validated = $request->validate([
            // Encabezado

            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],

            'tipo_documento_id' => [
                'required',
                'integer',
                Rule::exists('tipos_documento', 'id')
                    ->where('codigo', 'FACT')
                    ->where('estado', true),
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

                Rule::unique(
                    'compras',
                    'numero'
                )->where(function ($query) use ($request) {
                    return $query
                        ->where(
                            'proveedor_id',
                            $request->proveedor_id
                        )
                        ->where(
                            'serie',
                            is_string($request->serie) ? $request->serie : ''
                        )
                        ->where('estado', true);
                }),
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',
                Rule::unique('compras', 'numero_autorizacion')->where('estado', true),
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

            'observacion' => [
                'nullable',
                'string',
            ],

            // Detalles

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],

            'detalles.*.inventario_compra_id' => [
                'required',
                'integer',
                Rule::exists('inventarios_compra', 'id')->where('estado', true),
            ],

            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.porcentaje_descuento' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'detalles.*.importe_exento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_otros' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.observacion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'numero.unique' =>
            'Ya existe una compra para este proveedor con la misma serie y número.',

            'numero_autorizacion.unique' =>
            'El número de autorización ya se encuentra registrado.',
        ]);


        $compraInventarioService->registrarCompra($validated, (int) auth()->id());

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
            'tipoDocumento',
            'proveedor.direccion',
            'user',
            'anuladoPor',
            'detalles.inventarioCompra',
        ]);

        return view(
            'compras.show',
            compact('compra')
        );
    }


    public function cambiarEstado(Compra $compra, CompraInventarioService $compraInventarioService)
    {
        $compraInventarioService->anularCompra((int) $compra->id, (int) auth()->id());

        return redirect()
            ->route('compras.index')
            ->with('success', 'Compra anulada correctamente.');
    }
}
