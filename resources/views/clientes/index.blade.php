@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Clientes
            </h1>

            <p class="text-muted mb-0">
                Administra los clientes individuales y empresas que compran en la joyería.
            </p>
        </div>

        @can('clientes.crear')
            <a href="{{ route('clientes.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo cliente
            </a>
        @endcan

    </div>


    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert">
            </button>
        </div>
    @endif


    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            {{-- Formulario de filtros (los inputs se asocian con form="filtros") --}}
            <form id="filtros" method="GET" action="{{ route('clientes.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Tipo</th>

                            <th>Nombre</th>

                            <th>Tipo Identificación</th>

                            <th>No. Identificación</th>

                            <th>Teléfono</th>

                            <th>Correo</th>

                            <th>Estado</th>

                            <th>Acciones</th>

                        </tr>


                        {{-- Filtros --}}
                        <tr>

                            <th>
                                <input type="number" name="id" value="{{ request('id') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <select name="tipo" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="persona" {{ request('tipo') === 'persona' ? 'selected' : '' }}>
                                        Persona Individual
                                    </option>

                                    <option value="sociedad" {{ request('tipo') === 'sociedad' ? 'selected' : '' }}>
                                        Sociedad / Empresa
                                    </option>

                                </select>
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="tipo_identificacion" value="{{ request('tipo_identificacion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar tipo">
                            </th>

                            <th>
                                <input type="text" name="numero_identificacion" value="{{ request('numero_identificacion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar identificación">
                            </th>

                            <th>
                                <input type="text" name="telefono" value="{{ request('telefono') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar teléfono">
                            </th>

                            <th>
                                <input type="text" name="correo" value="{{ request('correo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar correo">
                            </th>

                            <th>
                                <select name="estado" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>
                                        Activo
                                    </option>

                                    <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>
                                        Inactivo
                                    </option>

                                </select>
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($clientes as $cliente)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $cliente->id }}
                                </td>

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

                                <td class="fw-bold">

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
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Inactivo
                                        </span>
                                    @endif

                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('clientes.ver')
                                            <a href="{{ route('clientes.show', $cliente->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('clientes.modificar')
                                            <a href="{{ route('clientes.edit', $cliente->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('clientes.eliminar')
                                            <form method="POST"
                                                action="{{ route('clientes.cambiar-estado', $cliente->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este cliente?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($cliente->estado)
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif

                                            </form>
                                        @endcan

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="9" class="text-center text-muted py-4">

                                    No se encontraron clientes.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $clientes->links() }}

            </div>

        </div>

    </div>
@endsection
