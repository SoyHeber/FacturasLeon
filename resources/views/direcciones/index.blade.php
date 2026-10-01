@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Direcciones
            </h1>

            <p class="text-muted mb-0">
                Administra las direcciones registradas para clientes y proveedores de la joyería.
            </p>
        </div>

        @can('direcciones.crear')
            <a href="{{ route('direcciones.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nueva dirección
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
            <form id="filtros" method="GET" action="{{ route('direcciones.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>País</th>

                            <th>Departamento</th>

                            <th>Municipio</th>

                            <th>Dirección</th>

                            <th>Código Postal</th>

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
                                <input type="text" name="departamento" value="{{ request('departamento') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar departamento">
                            </th>

                            <th>
                                <input type="text" name="municipio" value="{{ request('municipio') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar municipio">
                            </th>

                            <th>
                                <input type="text" name="direccion" value="{{ request('direccion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar dirección">
                            </th>

                            <th>
                                <input type="text" name="codigo_postal" value="{{ request('codigo_postal') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar código postal">
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

                                    <a href="{{ route('direcciones.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($direcciones as $direccion)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $direccion->id }}
                                </td>

                                <td>
                                    {{ $direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                                </td>

                                <td>
                                    {{ $direccion->municipio->departamento->nombre ?? 'Sin departamento' }}
                                </td>

                                <td>
                                    {{ $direccion->municipio->nombre ?? 'Sin municipio' }}
                                </td>

                                <td class="fw-bold">
                                    {{ $direccion->direccion }}
                                </td>

                                <td>
                                    {{ $direccion->codigo_postal ?: 'Sin código postal' }}
                                </td>

                                <td>

                                    @if ($direccion->estado)
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
                                    {{ $direccion->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('direcciones.ver')
                                            <a href="{{ route('direcciones.show', $direccion->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('direcciones.modificar')
                                            <a href="{{ route('direcciones.edit', $direccion->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('direcciones.eliminar')
                                            <form method="POST"
                                                action="{{ route('direcciones.cambiar-estado', $direccion->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de esta dirección?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($direccion->estado)
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

                                    No se encontraron direcciones.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $direcciones->links() }}

            </div>

        </div>

    </div>
@endsection
