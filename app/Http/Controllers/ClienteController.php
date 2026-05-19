<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\TipoIdentificacion;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::with([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais'
        ])->latest()->paginate(10);

        return view('clientes.index', compact('clientes'));
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

        return view('clientes.create', compact('tiposIdentificacion', 'direcciones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'tipo_identificacion_id' => 'required|exists:tipos_identificacion,id',
            'numero_identificacion' => 'required|string|max:30|unique:clientes,numero_identificacion',
            'direccion_id' => 'required|exists:direcciones,id',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'estado' => 'nullable|boolean',
        ]);

        Cliente::create([
            'nombre' => $validated['nombre'],
            'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
            'numero_identificacion' => $validated['numero_identificacion'],
            'direccion_id' => $validated['direccion_id'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : true,
        ]);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais'
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        $tiposIdentificacion = TipoIdentificacion::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $direcciones = Direccion::with('municipio.departamento.pais')
            ->where('estado', true)
            ->orderBy('id', 'desc')
            ->get();

        return view('clientes.edit', compact('cliente', 'tiposIdentificacion', 'direcciones'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'tipo_identificacion_id' => 'required|exists:tipos_identificacion,id',
            'numero_identificacion' => 'required|string|max:30|unique:clientes,numero_identificacion,' . $cliente->id,
            'direccion_id' => 'required|exists:direcciones,id',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'estado' => 'nullable|boolean',
        ]);

        $cliente->update([
            'nombre' => $validated['nombre'],
            'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
            'numero_identificacion' => $validated['numero_identificacion'],
            'direccion_id' => $validated['direccion_id'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'estado' => $request->has('estado') ? (bool) $request->estado : false,
        ]);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function cambiarEstado(Cliente $cliente)
    {
        $cliente->update([
            'estado' => !$cliente->estado,
        ]);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Estado del cliente actualizado correctamente.');
    }
}
