<?php

namespace App\Http\Controllers;

use App\Models\Accion;
use Illuminate\Http\Request;

class AccionController extends Controller
{
    public function index()
    {
        $acciones = Accion::withCount('opciones')
            ->orderBy('id')
            ->paginate(10);

        return view('acciones.index', compact('acciones'));
    }

    public function create()
    {
        return view('acciones.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50|unique:acciones,nombre',
            'clave' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:acciones,clave',
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ], [
            'clave.regex' => 'La clave solo puede contener minúsculas, números, guion y guion bajo.',
        ]);

        Accion::create([
            'nombre' => $validated['nombre'],
            'clave' => $validated['clave'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('acciones.index')
            ->with('success', 'Acción creada correctamente.');
    }

    public function show(Accion $accion)
    {
        $accion->load('opciones.modulo');

        return view('acciones.show', compact('accion'));
    }

    public function edit(Accion $accion)
    {
        return view('acciones.edit', compact('accion'));
    }

    public function update(Request $request, Accion $accion)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50|unique:acciones,nombre,' . $accion->id,
            'clave' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:acciones,clave,' . $accion->id,
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ], [
            'clave.regex' => 'La clave solo puede contener minúsculas, números, guion y guion bajo.',
        ]);

        $accion->update([
            'nombre' => $validated['nombre'],
            'clave' => $validated['clave'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('acciones.index')
            ->with('success', 'Acción actualizada correctamente.');
    }

    public function cambiarEstado(Accion $accion)
    {
        $accion->update([
            'estado' => !$accion->estado,
        ]);

        return redirect()
            ->route('acciones.index')
            ->with('success', 'Estado de la acción actualizado correctamente.');
    }
}
