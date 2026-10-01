@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Departamentos
            </h1>

            <p class="text-muted mb-0">
                Administra los departamentos de cada país utilizados en las direcciones de la joyería.
            </p>
        </div>

        @can('departamentos.crear')
            <a href="{{ route('departamentos.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo departamento
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

            <form id="filtros" method="GET" action="{{ route('departamentos.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>País</th>

                            <th>Nombre</th>

                            <th>Código</th>

                            <th>Estado</th>

                            <th>Fecha creación</th>

                            <th>Acciones</th>

                        </tr>


                        {{-- Filtros --}}
                        <tr>

                            <th>
                                <input type="number" name="id" value="{{ request('id') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input type="text" name="pais" value="{{ request('pais') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar país">
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="codigo" value="{{ request('codigo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar código">
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

                            <th>
                                <input type="date" name="fecha" value="{{ request('fecha') }}" form="filtros"
                                    class="form-control form-control-sm">
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($departamentos as $departamento)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $departamento->id }}
                                </td>

                                <td>
                                    {{ $departamento->pais->nombre ?? 'Sin país' }}
                                </td>

                                <td class="fw-bold">
                                    {{ $departamento->nombre }}
                                </td>

                                <td>
                                    {{ $departamento->codigo ?: 'Sin código' }}
                                </td>

                                <td>

                                    @if ($departamento->estado)
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
                                    {{ $departamento->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('departamentos.ver')
                                            <a href="{{ route('departamentos.show', $departamento->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('departamentos.modificar')
                                            <a href="{{ route('departamentos.edit', $departamento->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('departamentos.eliminar')
                                            <form method="POST"
                                                action="{{ route('departamentos.cambiar-estado', $departamento->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este departamento?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($departamento->estado)
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

                                    No se encontraron departamentos.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $departamentos->links() }}

            </div>

        </div>

    </div>
@endsection
