<?php

namespace App\Http\Controllers;

use App\Models\Direccion;
use App\Models\Proveedor;
use App\Models\TipoIdentificacion;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::with([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais'
        ])->latest()->paginate(10);

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
