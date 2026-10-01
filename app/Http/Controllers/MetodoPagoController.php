<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        $query = MetodoPago::query();

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por nombre
        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtro por descripción
        if ($request->filled('descripcion')) {
            $query->where('descripcion', 'like', '%' . $request->descripcion . '%');
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de creación
        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $metodosPago = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('metodos_pago.index', compact('metodosPago'));
    }

    public function create()
    {
        return view('metodos_pago.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:metodos_pago,nombre',
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        MetodoPago::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('metodos_pago.index')
            ->with('success', 'Método de pago creado correctamente.');
    }

    public function show(MetodoPago $metodoPago)
    {
        return view('metodos_pago.show', compact('metodoPago'));
    }

    public function edit(MetodoPago $metodoPago)
    {
        return view('metodos_pago.edit', compact('metodoPago'));
    }

    public function update(Request $request, MetodoPago $metodoPago)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:metodos_pago,nombre,' . $metodoPago->id,
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        $metodoPago->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('metodos_pago.index')
            ->with('success', 'Método de pago actualizado correctamente.');
    }

    public function cambiarEstado(MetodoPago $metodoPago)
    {
        $metodoPago->update([
            'estado' => !$metodoPago->estado,
        ]);

        return redirect()
            ->route('metodos_pago.index')
            ->with('success', 'Estado del método de pago actualizado correctamente.');
    }
}
