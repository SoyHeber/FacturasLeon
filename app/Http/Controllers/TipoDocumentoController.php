<?php

namespace App\Http\Controllers;

use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TipoDocumentoController extends Controller
{
    public function index(Request $request)
    {
        $query = TipoDocumento::query();

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por código
        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
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

        $tiposDocumento = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tipos_documento.index', compact('tiposDocumento'));
    }

    public function create()
    {
        return view('tipos_documento.create');
    }

    public function store(Request $request)
    {
        if (is_string($request->codigo)) {
            $request->merge(['codigo' => strtoupper(trim($request->codigo))]);
        }

        $validated = $request->validate([
            'codigo' => 'required|string|max:10|unique:tipos_documento,codigo',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
        ]);

        TipoDocumento::create([
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('tipos_documento.index')
            ->with('success', 'Tipo de documento creado correctamente.');
    }

    public function show(TipoDocumento $tipoDocumento)
    {
        return view('tipos_documento.show', compact('tipoDocumento'));
    }

    public function edit(TipoDocumento $tipoDocumento)
    {
        return view('tipos_documento.edit', compact('tipoDocumento'));
    }

    public function update(Request $request, TipoDocumento $tipoDocumento)
    {
        if (is_string($request->codigo)) {
            $request->merge(['codigo' => strtoupper(trim($request->codigo))]);
        }

        DB::transaction(function () use ($request, $tipoDocumento) {
            $tipoDocumento = TipoDocumento::whereKey($tipoDocumento->id)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $request->validate([
                'codigo' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('tipos_documento', 'codigo')->ignore($tipoDocumento->id),
                    function ($attribute, $value, $fail) use ($tipoDocumento) {
                        if ($tipoDocumento->codigo === 'FACT' && $value !== $tipoDocumento->codigo) {
                            $fail('El código FACT es utilizado internamente por el sistema y no puede modificarse.');
                            return;
                        }

                        if ($value !== $tipoDocumento->codigo && $tipoDocumento->compras()->exists()) {
                            $fail('El código no puede modificarse porque este tipo de documento ya está siendo utilizado.');
                        }
                    },
                ],
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string|max:255',
                'estado' => 'nullable|boolean',
            ]);

            $tipoDocumento->update([
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'estado' => $request->has('estado') ? (bool) $request->estado : false,
            ]);
        });

        return redirect()
            ->route('tipos_documento.index')
            ->with('success', 'Tipo de documento actualizado correctamente.');
    }

    public function cambiarEstado(TipoDocumento $tipoDocumento)
    {
        $tipoDocumento->update([
            'estado' => !$tipoDocumento->estado,
        ]);

        return redirect()
            ->route('tipos_documento.index')
            ->with('success', 'Estado del tipo de documento actualizado correctamente.');
    }
}
