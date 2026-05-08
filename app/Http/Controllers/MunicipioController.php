<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MunicipioController extends Controller
{
    public function index()
    {
        $municipios = Municipio::with('departamento.pais')
            ->latest()
            ->paginate(10);

        return view('municipios.index', compact('municipios'));
    }

    public function create()
    {
        $departamentos = Departamento::with('pais')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('municipios.create', compact('departamentos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('municipios')->where(function ($query) use ($request) {
                    return $query->where('departamento_id', $request->departamento_id);
                }),
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('municipios')->where(function ($query) use ($request) {
                    return $query->where('departamento_id', $request->departamento_id);
                }),
            ],
            'estado' => 'nullable|boolean',
        ]);

        Municipio::create([
            'departamento_id' => $validated['departamento_id'],
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('municipios.index')
            ->with('success', 'Municipio creado correctamente.');
    }

    public function show(Municipio $municipio)
    {
        $municipio->load('departamento.pais');

        return view('municipios.show', compact('municipio'));
    }

    public function edit(Municipio $municipio)
    {
        $departamentos = Departamento::with('pais')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('municipios.edit', compact('municipio', 'departamentos'));
    }

    public function update(Request $request, Municipio $municipio)
    {
        $validated = $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('municipios')
                    ->where(function ($query) use ($request) {
                        return $query->where('departamento_id', $request->departamento_id);
                    })
                    ->ignore($municipio->id),
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('municipios')
                    ->where(function ($query) use ($request) {
                        return $query->where('departamento_id', $request->departamento_id);
                    })
                    ->ignore($municipio->id),
            ],
            'estado' => 'nullable|boolean',
        ]);

        $municipio->update([
            'departamento_id' => $validated['departamento_id'],
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('municipios.index')
            ->with('success', 'Municipio actualizado correctamente.');
    }

    public function cambiarEstado(Municipio $municipio)
    {
        $municipio->update([
            'estado' => !$municipio->estado,
        ]);

        return redirect()
            ->route('municipios.index')
            ->with('success', 'Estado del municipio actualizado correctamente.');
    }
}