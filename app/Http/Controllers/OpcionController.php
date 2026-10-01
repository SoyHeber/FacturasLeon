<?php

namespace App\Http\Controllers;

use App\Models\Accion;
use App\Models\Modulo;
use App\Models\Opcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpcionController extends Controller
{
    public function index(Request $request)
    {
        $query = Opcion::with(['modulo', 'acciones']);

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por módulo
        if ($request->filled('modulo')) {
            $query->whereHas('modulo', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->modulo . '%');
            });
        }

        // Filtro por nombre
        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtro por ruta
        if ($request->filled('ruta')) {
            $query->where('ruta', 'like', '%' . $request->ruta . '%');
        }

        // Filtro por acciones admitidas
        if ($request->filled('accion')) {
            $query->whereHas('acciones', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->accion . '%');
            });
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $opciones = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('opciones.index', compact('opciones'));
    }

    public function create()
    {
        $modulos = Modulo::where('estado', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $acciones = Accion::where('estado', true)
            ->orderBy('id')
            ->get();

        return view('opciones.create', compact('modulos', 'acciones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'modulo_id' => 'required|exists:modulos,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('opciones')->where(function ($query) use ($request) {
                    return $query->where('modulo_id', $request->modulo_id);
                }),
            ],
            'descripcion' => 'nullable|string',
            'ruta' => 'required|string|max:100|regex:/^[a-z0-9_]+$/|unique:opciones,ruta',
            'icono' => 'nullable|string|max:20',
            'orden' => 'nullable|integer|min:0',
            'estado' => 'nullable|boolean',
            'acciones' => 'nullable|array',
            'acciones.*' => 'exists:acciones,id',
        ], [
            'ruta.regex' => 'La ruta solo puede contener minúsculas, números y guion bajo (ej: metodos_pago).',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $opcion = Opcion::create([
                'modulo_id' => $validated['modulo_id'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'ruta' => $validated['ruta'],
                'icono' => $validated['icono'] ?? null,
                'orden' => $validated['orden'] ?? 0,
                'estado' => $request->has('estado') ? (bool) $request->estado : true,
                'created_by' => Auth::id(),
            ]);

            $opcion->acciones()->sync($validated['acciones'] ?? []);
        });

        return redirect()
            ->route('opciones.index')
            ->with('success', 'Opción creada correctamente.');
    }

    public function show(Opcion $opcion)
    {
        $opcion->load(['modulo', 'acciones', 'creador']);

        return view('opciones.show', compact('opcion'));
    }

    public function edit(Opcion $opcion)
    {
        $opcion->load('acciones');

        $modulos = Modulo::where('estado', true)
            ->orWhere('id', $opcion->modulo_id)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $acciones = Accion::where('estado', true)
            ->orderBy('id')
            ->get();

        return view('opciones.edit', compact('opcion', 'modulos', 'acciones'));
    }

    public function update(Request $request, Opcion $opcion)
    {
        $validated = $request->validate([
            'modulo_id' => 'required|exists:modulos,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('opciones')
                    ->where(function ($query) use ($request) {
                        return $query->where('modulo_id', $request->modulo_id);
                    })
                    ->ignore($opcion->id),
            ],
            'descripcion' => 'nullable|string',
            'ruta' => 'required|string|max:100|regex:/^[a-z0-9_]+$/|unique:opciones,ruta,' . $opcion->id,
            'icono' => 'nullable|string|max:20',
            'orden' => 'nullable|integer|min:0',
            'estado' => 'nullable|boolean',
            'acciones' => 'nullable|array',
            'acciones.*' => 'exists:acciones,id',
        ], [
            'ruta.regex' => 'La ruta solo puede contener minúsculas, números y guion bajo (ej: metodos_pago).',
        ]);

        DB::transaction(function () use ($validated, $request, $opcion) {
            $opcion->update([
                'modulo_id' => $validated['modulo_id'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'ruta' => $validated['ruta'],
                'icono' => $validated['icono'] ?? null,
                'orden' => $validated['orden'] ?? 0,
                'estado' => $request->has('estado') ? (bool) $request->estado : false,
            ]);

            $acciones = $validated['acciones'] ?? [];

            $opcion->acciones()->sync($acciones);

            // Si la opción ya no admite una acción, se quita de los roles que la tenían
            DB::table('roles_opciones_acciones')
                ->where('opcion_id', $opcion->id)
                ->whereNotIn('accion_id', $acciones)
                ->delete();
        });

        return redirect()
            ->route('opciones.index')
            ->with('success', 'Opción actualizada correctamente.');
    }

    public function cambiarEstado(Opcion $opcion)
    {
        $opcion->update([
            'estado' => !$opcion->estado,
        ]);

        return redirect()
            ->route('opciones.index')
            ->with('success', 'Estado de la opción actualizado correctamente.');
    }
}
