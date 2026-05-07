<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use Illuminate\Http\Request;

class PaisController extends Controller
{
    public function index()
    {
        $paises = Pais::latest()->paginate(10);

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