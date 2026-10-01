<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuloController extends Controller
{
    public function index(Request $request)
    {
        $query = Modulo::withCount('opciones');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por orden
        if ($request->filled('orden')) {
            $query->where('orden', $request->orden);
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

        $modulos = $query
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('modulos.index', compact('modulos'));
    }

    public function create()
    {
        return view('modulos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:modulos,nombre',
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:20',
            'orden' => 'nullable|integer|min:0',
            'estado' => 'nullable|boolean',
        ]);

        Modulo::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'icono' => $validated['icono'] ?? null,
            'orden' => $validated['orden'] ?? 0,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('modulos.index')
            ->with('success', 'Módulo creado correctamente.');
    }

    public function show(Modulo $modulo)
    {
        $modulo->load([
            'creador',
            'opciones' => fn ($query) => $query->orderBy('orden')->orderBy('nombre'),
        ]);

        return view('modulos.show', compact('modulo'));
    }

    public function edit(Modulo $modulo)
    {
        return view('modulos.edit', compact('modulo'));
    }

    public function update(Request $request, Modulo $modulo)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:modulos,nombre,' . $modulo->id,
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:20',
            'orden' => 'nullable|integer|min:0',
            'estado' => 'nullable|boolean',
        ]);

        $modulo->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'icono' => $validated['icono'] ?? null,
            'orden' => $validated['orden'] ?? 0,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('modulos.index')
            ->with('success', 'Módulo actualizado correctamente.');
    }

    public function cambiarEstado(Modulo $modulo)
    {
        $modulo->update([
            'estado' => !$modulo->estado,
        ]);

        return redirect()
            ->route('modulos.index')
            ->with('success', 'Estado del módulo actualizado correctamente.');
    }
}
