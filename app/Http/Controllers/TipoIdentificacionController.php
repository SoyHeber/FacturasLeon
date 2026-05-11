<?php

namespace App\Http\Controllers;

use App\Models\TipoIdentificacion;
use Illuminate\Http\Request;

class TipoIdentificacionController extends Controller
{
    public function index()
    {
        $tiposIdentificacion = TipoIdentificacion::latest()->paginate(10);

        return view('tipos_identificacion.index', compact('tiposIdentificacion'));
    }

    public function create()
    {
        return view('tipos_identificacion.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_identificacion,nombre',
            'codigo' => 'required|string|max:30|unique:tipos_identificacion,codigo',
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        TipoIdentificacion::create([
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('tipos_identificacion.index')
            ->with('success', 'Tipo de identificación creado correctamente.');
    }

    public function show(TipoIdentificacion $tipoIdentificacion)
    {
        return view('tipos_identificacion.show', compact('tipoIdentificacion'));
    }

    public function edit(TipoIdentificacion $tipoIdentificacion)
    {
        return view('tipos_identificacion.edit', compact('tipoIdentificacion'));
    }

    public function update(Request $request, TipoIdentificacion $tipoIdentificacion)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_identificacion,nombre,' . $tipoIdentificacion->id,
            'codigo' => 'required|string|max:30|unique:tipos_identificacion,codigo,' . $tipoIdentificacion->id,
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        $tipoIdentificacion->update([
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('tipos_identificacion.index')
            ->with('success', 'Tipo de identificación actualizado correctamente.');
    }

    public function cambiarEstado(TipoIdentificacion $tipoIdentificacion)
    {
        $tipoIdentificacion->update([
            'estado' => !$tipoIdentificacion->estado,
        ]);

        return redirect()
            ->route('tipos_identificacion.index')
            ->with('success', 'Estado del tipo de identificación actualizado correctamente.');
    }
}
