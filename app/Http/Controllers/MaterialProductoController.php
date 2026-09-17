<?php

namespace App\Http\Controllers;

use App\Models\MaterialProducto;
use App\Models\Producto;
use App\Models\InventarioCompra;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = MaterialProducto::with([
            'producto',
            'inventarioCompra',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('producto')) {
            $query->whereHas('producto', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%' . $request->producto . '%'
                );
            });
        }

        if ($request->filled('material')) {
            $query->whereHas('inventarioCompra', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%' . $request->material . '%'
                );
            });
        }

        if ($request->filled('cantidad_requerida')) {
            $query->where(
                'cantidad_requerida',
                $request->cantidad_requerida
            );
        }

        if ($request->filled('observacion')) {
            $query->where(
                'observacion',
                'like',
                '%' . $request->observacion . '%'
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
            );
        }

        $materialesProducto = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'materiales_producto.index',
            compact('materialesProducto')
        );
    }

    public function create()
    {
        $productos = Producto::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'materiales_producto.create',
            compact(
                'productos',
                'inventariosCompra'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',

                Rule::unique('materiales_producto')
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where('producto_id', $request->producto_id)
                            ->where(
                                'inventario_compra_id',
                                $request->inventario_compra_id
                            );
                    }),
            ],

            'inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'cantidad_requerida' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'observacion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        MaterialProducto::create([
            'producto_id' =>
            $validated['producto_id'],

            'inventario_compra_id' =>
            $validated['inventario_compra_id'],

            'cantidad_requerida' =>
            $validated['cantidad_requerida'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Material del producto registrado correctamente.'
            );
    }

    public function show(MaterialProducto $materialProducto)
    {
        $materialProducto->load([
            'producto',
            'inventarioCompra',
        ]);

        return view(
            'materiales_producto.show',
            compact('materialProducto')
        );
    }

    public function edit(MaterialProducto $materialProducto)
    {
        $productos = Producto::where('estado', true)
            ->orWhere('id', $materialProducto->producto_id)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where('estado', true)
            ->orWhere(
                'id',
                $materialProducto->inventario_compra_id
            )
            ->orderBy('nombre')
            ->get();

        return view(
            'materiales_producto.edit',
            compact(
                'materialProducto',
                'productos',
                'inventariosCompra'
            )
        );
    }

    public function update(
        Request $request,
        MaterialProducto $materialProducto
    ) {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',
            ],

            'inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'cantidad_requerida' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'observacion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $duplicado = MaterialProducto::where(
            'producto_id',
            $validated['producto_id']
        )
            ->where(
                'inventario_compra_id',
                $validated['inventario_compra_id']
            )
            ->where(
                'id',
                '!=',
                $materialProducto->id
            )
            ->exists();

        if ($duplicado) {
            return back()
                ->withErrors([
                    'inventario_compra_id' =>
                    'Este material ya está asignado al producto seleccionado.',
                ])
                ->withInput();
        }

        $materialProducto->update([
            'producto_id' =>
            $validated['producto_id'],

            'inventario_compra_id' =>
            $validated['inventario_compra_id'],

            'cantidad_requerida' =>
            $validated['cantidad_requerida'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Material del producto actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        MaterialProducto $materialProducto
    ) {
        $materialProducto->update([
            'estado' => !$materialProducto->estado,
        ]);

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Estado actualizado correctamente.'
            );
    }
}
