@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Acciones
            </h1>

            <p class="text-muted mb-0">
                Administra las acciones que pueden habilitarse en cada opción para definir los permisos.
            </p>
        </div>

        @can('acciones.crear')
            <a href="{{ route('acciones.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nueva acción
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

            <form id="filtros" method="GET" action="{{ route('acciones.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Nombre</th>

                            <th>Clave</th>

                            <th>Descripción</th>

                            <th>Opciones que la usan</th>

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
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="clave" value="{{ request('clave') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar clave">
                            </th>

                            <th>
                                <input type="text" name="descripcion" value="{{ request('descripcion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar descripción">
                            </th>

                            {{-- Opciones que la usan no tiene filtro --}}
                            <th></th>

                            <th>
                                <select name="estado" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>
                                        Activa
                                    </option>

                                    <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>
                                        Inactiva
                                    </option>

                                </select>
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('acciones.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($acciones as $accion)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $accion->id }}
                                </td>

                                <td class="fw-bold">
                                    {{ $accion->nombre }}
                                </td>

                                <td>
                                    <code>{{ $accion->clave }}</code>
                                </td>

                                <td>
                                    {{ $accion->descripcion ?: 'Sin descripción' }}
                                </td>

                                <td>
                                    {{ $accion->opciones_count }}
                                </td>

                                <td>

                                    @if ($accion->estado)
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Activa
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Inactiva
                                        </span>
                                    @endif

                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('acciones.ver')
                                            <a href="{{ route('acciones.show', $accion->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('acciones.modificar')
                                            <a href="{{ route('acciones.edit', $accion->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('acciones.eliminar')
                                            <form method="POST"
                                                action="{{ route('acciones.cambiar-estado', $accion->id) }}">

                                                @csrf
                                                @method('PATCH')

                                                @if ($accion->estado)
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

                                <td colspan="7" class="text-center text-muted py-4">

                                    No se encontraron acciones.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $acciones->links() }}

            </div>

        </div>

    </div>
@endsection
