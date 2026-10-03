@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Materiales por Producto
            </h1>

            <p class="text-muted mb-0">
                Administra los materiales e insumos requeridos para fabricar cada producto.
            </p>
        </div>

        @can('materiales_producto.crear')
            <a href="{{ route('materiales_producto.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo material
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

            <form id="filtros" method="GET" action="{{ route('materiales_producto.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr class="table-dark">
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Material / Insumo</th>
                            <th>Cantidad requerida</th>
                            <th>Unidad</th>
                            <th>Observación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                        <tr>

                            <th>
                                <input form="filtros" type="number" name="id" value="{{ request('id') }}"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="producto" value="{{ request('producto') }}"
                                    class="form-control form-control-sm" placeholder="Producto">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="material" value="{{ request('material') }}"
                                    class="form-control form-control-sm" placeholder="Material">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.0000000001" name="cantidad_requerida"
                                    value="{{ request('cantidad_requerida') }}" class="form-control form-control-sm"
                                    placeholder="Cantidad">
                            </th>

                            <th>
                                <input type="text" class="form-control form-control-sm" placeholder="Unidad"
                                    disabled>
                            </th>

                            <th>
                                <input form="filtros" type="text" name="observacion" value="{{ request('observacion') }}"
                                    class="form-control form-control-sm" placeholder="Observación">
                            </th>

                            <th>
                                <select form="filtros" name="estado" class="form-select form-select-sm">

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
                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('materiales_producto.index') }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($materialesProducto as $materialProducto)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $materialProducto->id }}
                                </td>

                                <td>
                                    {{ $materialProducto->producto->nombre ?? 'Sin producto' }}
                                </td>

                                <td>
                                    {{ $materialProducto->inventarioCompra->nombre ?? 'Sin material' }}
                                </td>

                                <td class="fw-semibold">
                                    {{ $materialProducto->cantidad_requerida }}
                                </td>

                                <td>
                                    <span class="badge bg-dark">
                                        {{ $materialProducto->inventarioCompra->unidad_medida ?? '' }}
                                    </span>
                                </td>

                                <td>
                                    {{ $materialProducto->observacion ?: 'Sin observación' }}
                                </td>

                                <td>

                                    @if ($materialProducto->estado)
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

                                    <div class="d-flex gap-2 flex-wrap">

                                        @can('materiales_producto.ver')
                                            <a href="{{ route('materiales_producto.show', $materialProducto->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('materiales_producto.modificar')
                                            <a href="{{ route('materiales_producto.edit', $materialProducto->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan

                                        @can('materiales_producto.eliminar')
                                            <form method="POST"
                                                action="{{ route('materiales_producto.cambiar-estado', $materialProducto->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este material?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($materialProducto->estado)
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
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron materiales asociados a productos.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-4">
                {{ $materialesProducto->links() }}
            </div>

        </div>
    </div>
@endsection
