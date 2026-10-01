<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    public function index(Request $request)
    {
        $query = Rol::withCount('usuarios');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
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

        $roles = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre',
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        $rol = Rol::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('roles.permisos', $rol->id)
            ->with('success', 'Rol creado correctamente. Ahora asigna sus permisos.');
    }

    public function show(Rol $rol)
    {
        $rol->load(['usuarios', 'creador']);

        $permisos = DB::table('roles_opciones_acciones as roa')
            ->join('opciones as o', 'o.id', '=', 'roa.opcion_id')
            ->join('modulos as m', 'm.id', '=', 'o.modulo_id')
            ->join('acciones as a', 'a.id', '=', 'roa.accion_id')
            ->where('roa.rol_id', $rol->id)
            ->orderBy('m.orden')
            ->orderBy('o.orden')
            ->orderBy('a.id')
            ->select('m.nombre as modulo', 'o.nombre as opcion', 'a.nombre as accion')
            ->get()
            ->groupBy(['modulo', 'opcion']);

        return view('roles.show', compact('rol', 'permisos'));
    }

    public function edit(Rol $rol)
    {
        return view('roles.edit', compact('rol'));
    }

    public function update(Request $request, Rol $rol)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre,' . $rol->id,
            'descripcion' => 'nullable|string',
            'estado' => 'nullable|boolean',
        ]);

        $rol->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function cambiarEstado(Rol $rol)
    {
        $rol->update([
            'estado' => !$rol->estado,
        ]);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Estado del rol actualizado correctamente.');
    }

    // Matriz de opciones x acciones para asignar permisos al rol
    public function permisos(Rol $rol)
    {
        $modulos = Modulo::with(['opciones' => function ($query) {
            $query->with(['acciones' => fn ($q) => $q->orderBy('acciones.id')])
                ->orderBy('orden')
                ->orderBy('nombre');
        }])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        // Permisos actuales como "opcion_id-accion_id"
        $permisosActuales = DB::table('roles_opciones_acciones')
            ->where('rol_id', $rol->id)
            ->get()
            ->map(fn ($permiso) => $permiso->opcion_id . '-' . $permiso->accion_id)
            ->all();

        return view('roles.permisos', compact('rol', 'modulos', 'permisosActuales'));
    }

    public function guardarPermisos(Request $request, Rol $rol)
    {
        $validated = $request->validate([
            'permisos' => 'nullable|array',
            'permisos.*' => 'array',
            'permisos.*.*' => 'integer',
        ]);

        // Solo se guardan combinaciones que la opción realmente admite
        $permitidas = DB::table('opciones_acciones')
            ->get()
            ->map(fn ($fila) => $fila->opcion_id . '-' . $fila->accion_id)
            ->all();

        $filas = [];
        $ahora = now();

        foreach ($validated['permisos'] ?? [] as $opcionId => $acciones) {
            foreach ($acciones as $accionId) {
                if (!in_array($opcionId . '-' . $accionId, $permitidas, true)) {
                    continue;
                }

                $filas[] = [
                    'rol_id' => $rol->id,
                    'opcion_id' => (int) $opcionId,
                    'accion_id' => (int) $accionId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::transaction(function () use ($rol, $filas) {
            DB::table('roles_opciones_acciones')
                ->where('rol_id', $rol->id)
                ->delete();

            if ($filas) {
                DB::table('roles_opciones_acciones')->insert($filas);
            }
        });

        return redirect()
            ->route('roles.show', $rol->id)
            ->with('success', 'Permisos del rol actualizados correctamente.');
    }
}
