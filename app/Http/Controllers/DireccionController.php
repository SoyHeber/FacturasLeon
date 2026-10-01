<?php

namespace App\Http\Controllers;

use App\Models\Direccion;
use App\Models\Municipio;
use Illuminate\Http\Request;

class DireccionController extends Controller
{
    public function index(Request $request)
    {
        $query = Direccion::with('municipio.departamento.pais');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por país
        if ($request->filled('pais')) {
            $query->whereHas('municipio.departamento.pais', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->pais . '%');
            });
        }

        // Filtro por departamento
        if ($request->filled('departamento')) {
            $query->whereHas('municipio.departamento', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->departamento . '%');
            });
        }

        // Filtro por municipio
        if ($request->filled('municipio')) {
            $query->whereHas('municipio', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->municipio . '%');
            });
        }

        // Filtro por dirección
        if ($request->filled('direccion')) {
            $query->where('direccion', 'like', '%' . $request->direccion . '%');
        }

        // Filtro por código postal
        if ($request->filled('codigo_postal')) {
            $query->where('codigo_postal', 'like', '%' . $request->codigo_postal . '%');
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de creación
        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $direcciones = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

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
