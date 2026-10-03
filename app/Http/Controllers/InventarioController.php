<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function index(Request $request)
    {
        $query = Inventario::with('producto');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por producto
        if ($request->filled('producto')) {
            $query->whereHas('producto', function ($q) use ($request) {
                $q->where('nombre', 'like', '%'.$request->producto.'%');
            });
        }

        // Filtro por código del producto
        if ($request->filled('codigo')) {
            $query->whereHas('producto', function ($q) use ($request) {
                $q->where('codigo', 'like', '%'.$request->codigo.'%');
            });
        }

        // Filtro por cantidad
        if ($request->filled('cantidad')) {
            $query->where('cantidad', $request->cantidad);
        }

        // Filtro por stock mínimo
        if ($request->filled('stock_minimo')) {
            $query->where('stock_minimo', $request->stock_minimo);
        }

        // Filtro por stock máximo
        if ($request->filled('stock_maximo')) {
            $query->where('stock_maximo', $request->stock_maximo);
        }

        // Filtro por ubicación
        if ($request->filled('ubicacion')) {
            $query->where('ubicacion', 'like', '%'.$request->ubicacion.'%');
        }

        // Filtro por estado del stock (bajo, sobre o normal)
        if ($request->filled('estado_stock')) {
            if ($request->estado_stock === 'bajo') {
                $query->whereColumn('cantidad', '<', 'stock_minimo');
            } elseif ($request->estado_stock === 'sobre') {
                $query->whereNotNull('stock_maximo')
                    ->whereColumn('cantidad', '>', 'stock_maximo');
            } elseif ($request->estado_stock === 'normal') {
                $query->whereColumn('cantidad', '>=', 'stock_minimo')
                    ->where(function ($q) {
                        $q->whereNull('stock_maximo')
                            ->orWhereColumn('cantidad', '<=', 'stock_maximo');
                    });
            }
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $inventarios = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('inventarios.index', compact('inventarios'));
    }

    public function create()
    {
        $productos = Producto::where('estado', true)
            ->whereDoesntHave('inventarios')
            ->orderBy('nombre')
            ->get();

        return view('inventarios.create', compact('productos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',
                'unique:inventarios,producto_id',
            ],

            'stock_minimo' => [
                'required',
                'integer',
                'min:0',
            ],

            'stock_maximo' => [
                'nullable',
                'integer',
                'min:0',
                'gte:stock_minimo',
            ],

            'ubicacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        Inventario::create([
            'producto_id' => $validated['producto_id'],
            'stock_minimo' => $validated['stock_minimo'],
            'stock_maximo' => $validated['stock_maximo'] ?? null,
            'ubicacion' => $validated['ubicacion'] ?? null,
            'estado' => $request->boolean('estado'),
        ]);

        return redirect()
            ->route('inventarios.index')
            ->with('success', 'Inventario creado correctamente.');
    }

    public function show(Inventario $inventario)
    {
        $inventario->load('producto');

        return view('inventarios.show', compact('inventario'));
    }

    public function edit(Inventario $inventario)
    {
        $inventario->load('producto');

        return view(
            'inventarios.edit',
            compact('inventario')
        );
    }

    public function update(
        Request $request,
        Inventario $inventario
    ) {
        $validated = $request->validate([
            'stock_minimo' => [
                'required',
                'integer',
                'min:0',
            ],

            'stock_maximo' => [
                'nullable',
                'integer',
                'min:0',
                'gte:stock_minimo',
            ],

            'ubicacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $inventario->update([
            'stock_minimo' => $validated['stock_minimo'],
            'stock_maximo' => $validated['stock_maximo'] ?? null,
            'ubicacion' => $validated['ubicacion'] ?? null,
            'estado' => $request->boolean('estado'),
        ]);

        return redirect()
            ->route('inventarios.index')
            ->with('success', 'Inventario actualizado correctamente.');
    }

    public function cambiarEstado(Inventario $inventario)
    {
        $inventario->update([
            'estado' => ! $inventario->estado,
        ]);

        return redirect()
            ->route('inventarios.index')
            ->with(
                'success',
                'Estado del inventario actualizado correctamente.'
            );
    }
}
