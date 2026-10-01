@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Módulos
            </h1>

            <p class="text-muted mb-0">
                Administra los módulos del menú que agrupan las opciones a las que acceden los usuarios.
            </p>
        </div>

        @can('modulos.crear')
            <a href="{{ route('modulos.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo módulo
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

            <form id="filtros" method="GET" action="{{ route('modulos.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Orden</th>

                            <th>Nombre</th>

                            <th>Descripción</th>

                            <th>Opciones</th>

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
                                <input type="number" name="orden" value="{{ request('orden') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Orden">
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="descripcion" value="{{ request('descripcion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar descripción">
                            </th>

                            {{-- Opciones no tiene filtro --}}
                            <th></th>

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

                                    <a href="{{ route('modulos.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($modulos as $modulo)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $modulo->id }}
                                </td>

                                <td>
                                    {{ $modulo->orden }}
                                </td>

                                <td class="fw-bold">
                                    {{ $modulo->icono }} {{ $modulo->nombre }}
                                </td>

                                <td>
                                    {{ $modulo->descripcion ?: 'Sin descripción' }}
                                </td>

                                <td>
                                    {{ $modulo->opciones_count }}
                                </td>

                                <td>

                                    @if ($modulo->estado)
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

                                        @can('modulos.ver')
                                            <a href="{{ route('modulos.show', $modulo->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('modulos.modificar')
                                            <a href="{{ route('modulos.edit', $modulo->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('modulos.eliminar')
                                            <form method="POST"
                                                action="{{ route('modulos.cambiar-estado', $modulo->id) }}">

                                                @csrf
                                                @method('PATCH')

                                                @if ($modulo->estado)
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

                                    No se encontraron módulos.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $modulos->links() }}

            </div>

        </div>

    </div>
@endsection
