<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller

{
    public function index(Request $request)
    {
        $query = Departamento::with('pais');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por país
        if ($request->filled('pais')) {
            $query->whereHas('pais', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->pais . '%');
            });
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

        $departamentos = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('departamentos.index', compact('departamentos'));
    }

    public function create()
    {
        $paises = Pais::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('departamentos.create', compact('paises'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pais_id' => 'required|exists:paises,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departamentos')->where(function ($query) use ($request) {
                    return $query->where('pais_id', $request->pais_id);
                }),
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('departamentos')->where(function ($query) use ($request) {
                    return $query->where('pais_id', $request->pais_id);
                }),
            ],
            'estado' => 'nullable|boolean',
        ]);

        Departamento::create([
            'pais_id' => $validated['pais_id'],
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('departamentos.index')
            ->with('success', 'Departamento creado correctamente.');
    }

    public function show(Departamento $departamento)
    {
        $departamento->load('pais');

        return view('departamentos.show', compact('departamento'));
    }

    public function edit(Departamento $departamento)
    {
        $paises = Pais::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('departamentos.edit', compact('departamento', 'paises'));
    }

    public function update(Request $request, Departamento $departamento)
    {
        $validated = $request->validate([
            'pais_id' => 'required|exists:paises,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departamentos')
                    ->where(function ($query) use ($request) {
                        return $query->where('pais_id', $request->pais_id);
                    })
                    ->ignore($departamento->id),
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('departamentos')
                    ->where(function ($query) use ($request) {
                        return $query->where('pais_id', $request->pais_id);
                    })
                    ->ignore($departamento->id),
            ],
            'estado' => 'nullable|boolean',
        ]);

        $departamento->update([
            'pais_id' => $validated['pais_id'],
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('departamentos.index')
            ->with('success', 'Departamento actualizado correctamente.');
    }

    public function cambiarEstado(Departamento $departamento)
    {
        $departamento->update([
            'estado' => !$departamento->estado,
        ]);

        return redirect()
            ->route('departamentos.index')
            ->with('success', 'Estado del departamento actualizado correctamente.');
    }
}