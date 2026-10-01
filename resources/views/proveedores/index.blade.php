@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Proveedores
            </h1>

            <p class="text-muted mb-0">
                Administra los proveedores que abastecen de joyas y materiales a la joyería.
            </p>
        </div>

        @can('proveedores.crear')
            <a href="{{ route('proveedores.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo proveedor
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
            <form id="filtros" method="GET" action="{{ route('proveedores.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Nombre</th>

                            <th>Tipo Identificación</th>

                            <th>No. Identificación</th>

                            <th>Dirección</th>

                            <th>Teléfono</th>

                            <th>Correo</th>

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
                                <input type="text" name="direccion" value="{{ request('direccion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar dirección">
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

                                    <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($proveedores as $proveedor)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $proveedor->id }}
                                </td>

                                <td class="fw-bold">
                                    {{ $proveedor->nombre }}
                                </td>

                                <td>
                                    {{ $proveedor->tipoIdentificacion->nombre ?? 'Sin tipo' }}
                                </td>

                                <td>
                                    {{ $proveedor->numero_identificacion }}
                                </td>

                                <td>

                                    @if ($proveedor->direccion)
                                        {{ $proveedor->direccion->direccion }}
                                        <br>
                                        <small class="text-muted">
                                            {{ $proveedor->direccion->municipio->nombre ?? 'Sin municipio' }} -
                                            {{ $proveedor->direccion->municipio->departamento->nombre ?? 'Sin departamento' }}
                                            -
                                            {{ $proveedor->direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                                        </small>
                                    @else
                                        Sin dirección
                                    @endif

                                </td>

                                <td>
                                    {{ $proveedor->telefono ?: 'Sin teléfono' }}
                                </td>

                                <td>
                                    {{ $proveedor->correo ?: 'Sin correo' }}
                                </td>

                                <td>

                                    @if ($proveedor->estado)
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
                                    {{ $proveedor->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('proveedores.ver')
                                            <a href="{{ route('proveedores.show', $proveedor->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('proveedores.modificar')
                                            <a href="{{ route('proveedores.edit', $proveedor->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('proveedores.eliminar')
                                            <form method="POST"
                                                action="{{ route('proveedores.cambiar-estado', $proveedor->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este proveedor?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($proveedor->estado)
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

                                <td colspan="10" class="text-center text-muted py-4">

                                    No se encontraron proveedores.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $proveedores->links() }}

            </div>

        </div>

    </div>
@endsection
