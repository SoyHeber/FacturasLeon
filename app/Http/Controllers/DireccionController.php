<?php

namespace App\Http\Controllers;

use App\Models\Direccion;
use App\Models\Municipio;
use Illuminate\Http\Request;

class DireccionController extends Controller
{
    public function index()
    {
        $direcciones = Direccion::with('municipio.departamento.pais')
            ->latest()
            ->paginate(10);

        return view('direcciones.index', compact('direcciones'));
    }

    public function create()
    {
        $municipios = Municipio::with('departamento.pais')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('direcciones.create', compact('municipios'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'municipio_id' => 'required|exists:municipios,id',
            'direccion' => 'required|string',
            'referencia' => 'nullable|string',
            'codigo_postal' => 'nullable|string|max:15',
            'estado' => 'nullable|boolean',
        ]);

        Direccion::create([
            'municipio_id' => $validated['municipio_id'],
            'direccion' => $validated['direccion'],
            'referencia' => $validated['referencia'] ?? null,
            'codigo_postal' => $validated['codigo_postal'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('direcciones.index')
            ->with('success', 'Dirección creada correctamente.');
    }

    public function show(Direccion $direccion)
    {
        $direccion->load('municipio.departamento.pais');

        return view('direcciones.show', compact('direccion'));
    }

    public function edit(Direccion $direccion)
    {
        $municipios = Municipio::with('departamento.pais')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('direcciones.edit', compact('direccion', 'municipios'));
    }

    public function update(Request $request, Direccion $direccion)
    {
        $validated = $request->validate([
            'municipio_id' => 'required|exists:municipios,id',
            'direccion' => 'required|string',
            'referencia' => 'nullable|string',
            'codigo_postal' => 'nullable|string|max:15',
            'estado' => 'nullable|boolean',
        ]);

        $direccion->update([
            'municipio_id' => $validated['municipio_id'],
            'direccion' => $validated['direccion'],
            'referencia' => $validated['referencia'] ?? null,
            'codigo_postal' => $validated['codigo_postal'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('direcciones.index')
            ->with('success', 'Dirección actualizada correctamente.');
    }

    public function cambiarEstado(Direccion $direccion)
    {
        $direccion->update([
            'estado' => !$direccion->estado,
        ]);

        return redirect()
            ->route('direcciones.index')
            ->with('success', 'Estado de la dirección actualizado correctamente.');
    }
}
