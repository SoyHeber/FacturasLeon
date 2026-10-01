<?php

namespace App\Http\Controllers;

use App\Models\Direccion;
use App\Models\Proveedor;
use App\Models\TipoIdentificacion;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::with([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais',
        ]);

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por nombre
        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtro por tipo de identificación
        if ($request->filled('tipo_identificacion')) {
            $query->whereHas('tipoIdentificacion', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->tipo_identificacion . '%');
            });
        }

        // Filtro por número de identificación
        if ($request->filled('numero_identificacion')) {
            $query->where('numero_identificacion', 'like', '%' . $request->numero_identificacion . '%');
        }

        // Filtro por dirección (dirección, municipio, departamento o país)
        if ($request->filled('direccion')) {
            $query->whereHas('direccion', function ($q) use ($request) {
                $q->where('direccion', 'like', '%' . $request->direccion . '%')
                    ->orWhereHas('municipio', function ($m) use ($request) {
                        $m->where('nombre', 'like', '%' . $request->direccion . '%');
                    })
                    ->orWhereHas('municipio.departamento', function ($d) use ($request) {
                        $d->where('nombre', 'like', '%' . $request->direccion . '%');
                    })
                    ->orWhereHas('municipio.departamento.pais', function ($p) use ($request) {
                        $p->where('nombre', 'like', '%' . $request->direccion . '%');
                    });
            });
        }

        // Filtro por teléfono
        if ($request->filled('telefono')) {
            $query->where('telefono', 'like', '%' . $request->telefono . '%');
        }

        // Filtro por correo
        if ($request->filled('correo')) {
            $query->where('correo', 'like', '%' . $request->correo . '%');
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de creación
        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $proveedores = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        $tiposIdentificacion = TipoIdentificacion::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $direcciones = Direccion::with('municipio.departamento.pais')
            ->where('estado', true)
            ->orderBy('id', 'desc')
            ->get();

        return view('proveedores.create', compact('tiposIdentificacion', 'direcciones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'tipo_identificacion_id' => 'required|exists:tipos_identificacion,id',
            'numero_identificacion' => 'required|string|max:30|unique:proveedores,numero_identificacion',
            'direccion_id' => 'required|exists:direcciones,id',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'estado' => 'nullable|boolean',
        ]);

        Proveedor::create([
            'nombre' => $validated['nombre'],
            'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
            'numero_identificacion' => $validated['numero_identificacion'],
            'direccion_id' => $validated['direccion_id'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('proveedores.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function show(Proveedor $proveedor)
    {
        $proveedor->load([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais'
        ]);

        return view('proveedores.show', compact('proveedor'));
    }

    public function edit(Proveedor $proveedor)
    {
        $tiposIdentificacion = TipoIdentificacion::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $direcciones = Direccion::with('municipio.departamento.pais')
            ->where('estado', true)
            ->orderBy('id', 'desc')
            ->get();

        return view('proveedores.edit', compact('proveedor', 'tiposIdentificacion', 'direcciones'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'tipo_identificacion_id' => 'required|exists:tipos_identificacion,id',
            'numero_identificacion' => 'required|string|max:30|unique:proveedores,numero_identificacion,' . $proveedor->id,
            'direccion_id' => 'required|exists:direcciones,id',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'estado' => 'nullable|boolean',
        ]);

        $proveedor->update([
            'nombre' => $validated['nombre'],
            'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
            'numero_identificacion' => $validated['numero_identificacion'],
            'direccion_id' => $validated['direccion_id'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('proveedores.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function cambiarEstado(Proveedor $proveedor)
    {
        $proveedor->update([
            'estado' => !$proveedor->estado,
        ]);

        return redirect()
            ->route('proveedores.index')
            ->with('success', 'Estado del proveedor actualizado correctamente.');
    }
}
