<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use Illuminate\Http\Request;

class PaisController extends Controller
{
    public function index(Request $request)
    {
        $query = Pais::query();

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por nombre
        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtro por código
        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de creación
        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $paises = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('paises.index', compact('paises'));
    }

    public function create()
    {
        return view('paises.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:paises,nombre',
            'codigo' => 'nullable|string|max:10|unique:paises,codigo',
            'estado' => 'nullable|boolean',
        ]);

        Pais::create([
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('paises.index')
            ->with('success', 'País creado correctamente.');
    }

    public function show(Pais $pais)
    {
        return view('paises.show', compact('pais'));
    }

    public function edit(Pais $pais)
    {
        return view('paises.edit', compact('pais'));
    }

    public function update(Request $request, Pais $pais)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:paises,nombre,' . $pais->id,
            'codigo' => 'nullable|string|max:10|unique:paises,codigo,' . $pais->id,
            'estado' => 'nullable|boolean',
        ]);

        $pais->update([
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('paises.index')
            ->with('success', 'País actualizado correctamente.');
    }

    public function cambiarEstado(Pais $pais)
    {
        $pais->update([
            'estado' => !$pais->estado
        ]);

        return redirect()
            ->route('paises.index')
            ->with('success', 'Estado del país actualizado correctamente.');
    }
}