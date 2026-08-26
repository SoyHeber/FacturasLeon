@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Clientes</h1>

        <a href="{{ route('clientes.create') }}" class="btn btn-primary">
            Nuevo cliente
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($clientes->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Nombre</th>
                                <th>Tipo Identificación</th>
                                <th>No. Identificación</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($clientes as $cliente)
                                <tr>
                                    <td>{{ $cliente->id }}</td>

                                    <td>
                                        @if ($cliente->persona)
                                            <span class="badge bg-primary">
                                                Persona Individual
                                            </span>
                                        @elseif ($cliente->sociedad)
                                            <span class="badge bg-info text-dark">
                                                Sociedad / Empresa
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                Sin clasificación
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($cliente->persona)
                                            {{ collect([
                                                $cliente->persona->nombre1,
                                                $cliente->persona->nombre2,
                                                $cliente->persona->nombre3,
                                                $cliente->persona->apellido1,
                                                $cliente->persona->apellido2,
                                                $cliente->persona->apellido_casada,
                                            ])->filter()->implode(' ') }}
                                        @elseif ($cliente->sociedad)
                                            {{ $cliente->sociedad->nombre }}
                                        @else
                                            Sin nombre
                                        @endif
                                    </td>

                                    <td>
                                        {{ $cliente->tipoIdentificacion->nombre ?? 'Sin tipo' }}
                                    </td>

                                    <td>
                                        {{ $cliente->numero_identificacion }}
                                    </td>

                                    <td>
                                        {{ $cliente->telefono ?: 'Sin teléfono' }}
                                    </td>

                                    <td>
                                        {{ $cliente->correo ?: 'Sin correo' }}
                                    </td>

                                    <td>
                                        @if ($cliente->estado)
                                            <span class="badge bg-success">
                                                Activo
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">

                                            <a href="{{ route('clientes.show', $cliente->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('clientes.edit', $cliente->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('clientes.cambiar-estado', $cliente->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este cliente?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($cliente->estado)
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif

                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                {{ $clientes->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay clientes registrados.
                </div>
            @endif

        </div>
    </div>
@endsection
