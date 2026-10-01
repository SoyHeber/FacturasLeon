@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Opciones
            </h1>

            <p class="text-muted mb-0">
                Administra las opciones de cada módulo y las acciones que pueden asignarse como permisos.
            </p>
        </div>

        @can('opciones.crear')
            <a href="{{ route('opciones.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nueva opción
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

            <form id="filtros" method="GET" action="{{ route('opciones.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Módulo</th>

                            <th>Nombre</th>

                            <th>Ruta</th>

                            <th>Acciones admitidas</th>

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
                                <input type="text" name="modulo" value="{{ request('modulo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar módulo">
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="ruta" value="{{ request('ruta') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar ruta">
                            </th>

                            <th>
                                <input type="text" name="accion" value="{{ request('accion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar acción">
                            </th>

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

                                    <a href="{{ route('opciones.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($opciones as $opcion)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $opcion->id }}
                                </td>

                                <td>
                                    {{ $opcion->modulo?->nombre }}
                                </td>

                                <td class="fw-bold">
                                    {{ $opcion->icono }} {{ $opcion->nombre }}
                                </td>

                                <td>
                                    <code>{{ $opcion->ruta }}</code>
                                </td>

                                <td>
                                    {{ $opcion->acciones->pluck('nombre')->implode(', ') ?: 'Ninguna' }}
                                </td>

                                <td>

                                    @if ($opcion->estado)
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

                                        @can('opciones.ver')
                                            <a href="{{ route('opciones.show', $opcion->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('opciones.modificar')
                                            <a href="{{ route('opciones.edit', $opcion->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('opciones.eliminar')
                                            <form method="POST"
                                                action="{{ route('opciones.cambiar-estado', $opcion->id) }}">

                                                @csrf
                                                @method('PATCH')

                                                @if ($opcion->estado)
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

                                    No se encontraron opciones.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $opciones->links() }}

            </div>

        </div>

    </div>
@endsection
