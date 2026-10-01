<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\TipoIdentificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::with([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais',
            'persona',
            'sociedad',
        ]);

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por tipo de cliente
        if ($request->filled('tipo')) {
            if ($request->tipo === 'persona') {
                $query->whereHas('persona');
            } elseif ($request->tipo === 'sociedad') {
                $query->whereHas('sociedad');
            }
        }

        // Filtro por nombre (persona o sociedad)
        if ($request->filled('nombre')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('persona', function ($p) use ($request) {
                    $p->where('nombre1', 'like', '%' . $request->nombre . '%')
                        ->orWhere('nombre2', 'like', '%' . $request->nombre . '%')
                        ->orWhere('nombre3', 'like', '%' . $request->nombre . '%')
                        ->orWhere('apellido1', 'like', '%' . $request->nombre . '%')
                        ->orWhere('apellido2', 'like', '%' . $request->nombre . '%')
                        ->orWhere('apellido_casada', 'like', '%' . $request->nombre . '%');
                })->orWhereHas('sociedad', function ($s) use ($request) {
                    $s->where('nombre', 'like', '%' . $request->nombre . '%');
                });
            });
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

        $clientes = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

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

        return view(
            'clientes.create',
            compact('tiposIdentificacion', 'direcciones')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_cliente' => [
                'required',
                Rule::in(['PERSONA', 'SOCIEDAD']),
            ],

            'tipo_identificacion_id' => [
                'required',
                'exists:tipos_identificacion,id',
            ],

            'numero_identificacion' => [
                'required',
                'string',
                'max:30',
                'unique:clientes,numero_identificacion',
            ],

            'direccion_id' => [
                'required',
                'exists:direcciones,id',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'correo' => [
                'nullable',
                'email',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],

            'nombre1' => [
                'required_if:tipo_cliente,PERSONA',
                'nullable',
                'string',
                'max:100',
            ],

            'nombre2' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombre3' => [
                'nullable',
                'string',
                'max:100',
            ],

            'apellido1' => [
                'required_if:tipo_cliente,PERSONA',
                'nullable',
                'string',
                'max:100',
            ],

            'apellido2' => [
                'nullable',
                'string',
                'max:100',
            ],

            'apellido_casada' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombre_sociedad' => [
                'required_if:tipo_cliente,SOCIEDAD',
                'nullable',
                'string',
                'max:200',
            ],
        ]);

        DB::transaction(function () use ($validated, $request) {

            $cliente = Cliente::create([
                'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
                'numero_identificacion' => $validated['numero_identificacion'],
                'direccion_id' => $validated['direccion_id'],
                'telefono' => $validated['telefono'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'estado' => $request->has('estado'),
            ]);

            if ($validated['tipo_cliente'] === 'PERSONA') {
                $cliente->persona()->create([
                    'nombre1' => $validated['nombre1'],
                    'nombre2' => $validated['nombre2'] ?? null,
                    'nombre3' => $validated['nombre3'] ?? null,
                    'apellido1' => $validated['apellido1'],
                    'apellido2' => $validated['apellido2'] ?? null,
                    'apellido_casada' => $validated['apellido_casada'] ?? null,
                ]);
            }

            if ($validated['tipo_cliente'] === 'SOCIEDAD') {
                $cliente->sociedad()->create([
                    'nombre' => $validated['nombre_sociedad'],
                ]);
            }
        });

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'tipoIdentificacion',
            'direccion.municipio.departamento.pais',
            'persona',
            'sociedad',
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        $cliente->load([
            'persona',
            'sociedad',
        ]);

        $tiposIdentificacion = TipoIdentificacion::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $direcciones = Direccion::with('municipio.departamento.pais')
            ->where('estado', true)
            ->orderBy('id', 'desc')
            ->get();

        return view(
            'clientes.edit',
            compact(
                'cliente',
                'tiposIdentificacion',
                'direcciones'
            )
        );
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'tipo_cliente' => [
                'required',
                Rule::in(['PERSONA', 'SOCIEDAD']),
            ],

            'tipo_identificacion_id' => [
                'required',
                'exists:tipos_identificacion,id',
            ],

            'numero_identificacion' => [
                'required',
                'string',
                'max:30',

                Rule::unique(
                    'clientes',
                    'numero_identificacion'
                )->ignore($cliente->id),
            ],

            'direccion_id' => [
                'required',
                'exists:direcciones,id',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'correo' => [
                'nullable',
                'email',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],

            'nombre1' => [
                'required_if:tipo_cliente,PERSONA',
                'nullable',
                'string',
                'max:100',
            ],

            'nombre2' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombre3' => [
                'nullable',
                'string',
                'max:100',
            ],

            'apellido1' => [
                'required_if:tipo_cliente,PERSONA',
                'nullable',
                'string',
                'max:100',
            ],

            'apellido2' => [
                'nullable',
                'string',
                'max:100',
            ],

            'apellido_casada' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombre_sociedad' => [
                'required_if:tipo_cliente,SOCIEDAD',
                'nullable',
                'string',
                'max:200',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $request,
            $cliente
        ) {

            $cliente->update([
                'tipo_identificacion_id' => $validated['tipo_identificacion_id'],
                'numero_identificacion' => $validated['numero_identificacion'],
                'direccion_id' => $validated['direccion_id'],
                'telefono' => $validated['telefono'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'estado' => $request->has('estado'),
            ]);

            if ($validated['tipo_cliente'] === 'PERSONA') {

                if ($cliente->sociedad) {
                    $cliente->sociedad()->delete();
                }

                $cliente->persona()->updateOrCreate(
                    [
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'nombre1' => $validated['nombre1'],
                        'nombre2' => $validated['nombre2'] ?? null,
                        'nombre3' => $validated['nombre3'] ?? null,
                        'apellido1' => $validated['apellido1'],
                        'apellido2' => $validated['apellido2'] ?? null,
                        'apellido_casada' => $validated['apellido_casada'] ?? null,
                    ]
                );
            }

            if ($validated['tipo_cliente'] === 'SOCIEDAD') {

                if ($cliente->persona) {
                    $cliente->persona()->delete();
                }

                $cliente->sociedad()->updateOrCreate(
                    [
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'nombre' => $validated['nombre_sociedad'],
                    ]
                );
            }
        });

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
            ->with(
                'success',
                'Estado del cliente actualizado correctamente.'
            );
    }
}