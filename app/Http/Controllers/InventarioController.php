<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventarioController extends Controller
{
    public function index()
    {
        $inventarios = Inventario::with('producto')
            ->latest()
            ->paginate(10);

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

            'cantidad' => [
                'required',
                'integer',
                'min:0',
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
            'cantidad' => $validated['cantidad'],
            'stock_minimo' => $validated['stock_minimo'],
            'stock_maximo' => $validated['stock_maximo'] ?? null,
            'ubicacion' => $validated['ubicacion'] ?? null,
            'estado' => $request->has('estado'),
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
        $productos = Producto::where('estado', true)
            ->where(function ($query) use ($inventario) {
                $query->whereDoesntHave('inventarios')
                    ->orWhere('id', $inventario->producto_id);
            })
            ->orderBy('nombre')
            ->get();

        return view(
            'inventarios.edit',
            compact('inventario', 'productos')
        );
    }

    public function update(
        Request $request,
        Inventario $inventario
    ) {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',

                Rule::unique('inventarios', 'producto_id')
                    ->ignore($inventario->id),
            ],

            'cantidad' => [
                'required',
                'integer',
                'min:0',
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

        $inventario->update([
            'producto_id' => $validated['producto_id'],
            'cantidad' => $validated['cantidad'],
            'stock_minimo' => $validated['stock_minimo'],
            'stock_maximo' => $validated['stock_maximo'] ?? null,
            'ubicacion' => $validated['ubicacion'] ?? null,
            'estado' => $request->has('estado'),
        ]);

        return redirect()
            ->route('inventarios.index')
            ->with('success', 'Inventario actualizado correctamente.');
    }

    public function cambiarEstado(Inventario $inventario)
    {
        $inventario->update([
            'estado' => !$inventario->estado,
        ]);

        return redirect()
            ->route('inventarios.index')
            ->with(
                'success',
                'Estado del inventario actualizado correctamente.'
            );
    }
}
