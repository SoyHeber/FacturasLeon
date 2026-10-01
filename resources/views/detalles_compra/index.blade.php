@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Detalles de Compra
            </h1>

            <p class="text-muted mb-0">
                Administra los materiales e insumos asociados a cada compra.
            </p>
        </div>

        @can('detalles_compra.crear')
            <a href="{{ route('detalles_compra.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo detalle
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

            <form id="filtros" method="GET" action="{{ route('detalles_compra.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr class="table-dark">
                            <th>ID</th>
                            <th>Compra</th>
                            <th>Material / Insumo</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>% Descuento</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                        <tr>

                            <th>
                                <input form="filtros" type="number" name="id" value="{{ request('id') }}"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input form="filtros" type="number" name="compra_id" value="{{ request('compra_id') }}"
                                    class="form-control form-control-sm" placeholder="Compra">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="inventario_compra"
                                    value="{{ request('inventario_compra') }}" class="form-control form-control-sm"
                                    placeholder="Material">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.0001" name="cantidad" value="{{ request('cantidad') }}"
                                    class="form-control form-control-sm" placeholder="Cantidad">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.000001" name="precio_unitario"
                                    value="{{ request('precio_unitario') }}" class="form-control form-control-sm"
                                    placeholder="Precio">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.0001" name="porcentaje_descuento"
                                    value="{{ request('porcentaje_descuento') }}" class="form-control form-control-sm"
                                    placeholder="%">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.01" name="importe_total"
                                    value="{{ request('importe_total') }}" class="form-control form-control-sm"
                                    placeholder="Total">
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

                                    <a href="{{ route('detalles_compra.index') }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($detallesCompra as $detalleCompra)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $detalleCompra->id }}
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        Compra #{{ $detalleCompra->compra_id }}
                                    </div>

                                    <small class="text-muted">
                                        {{ $detalleCompra->compra->serie ?? '' }}
                                        -
                                        {{ $detalleCompra->compra->numero ?? '' }}
                                    </small>
                                </td>

                                <td>
                                    {{ $detalleCompra->inventarioCompra->nombre ?? 'Sin material' }}
                                </td>

                                <td>
                                    {{ number_format($detalleCompra->cantidad, 4) }}

                                    <small class="text-muted">
                                        {{ $detalleCompra->inventarioCompra->unidad_medida ?? '' }}
                                    </small>
                                </td>

                                <td>
                                    {{ number_format($detalleCompra->precio_unitario, 6) }}
                                </td>

                                <td>
                                    {{ number_format($detalleCompra->porcentaje_descuento, 4) }}%
                                </td>

                                <td class="fw-bold">
                                    {{ $detalleCompra->compra->moneda ?? 'GTQ' }}
                                    {{ number_format($detalleCompra->importe_total, 2) }}
                                </td>

                                <td>

                                    @if ($detalleCompra->estado)
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

                                        @can('detalles_compra.ver')
                                            <a href="{{ route('detalles_compra.show', $detalleCompra->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('detalles_compra.modificar')
                                            <a href="{{ route('detalles_compra.edit', $detalleCompra->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan

                                        @can('detalles_compra.eliminar')
                                            <form method="POST"
                                                action="{{ route('detalles_compra.cambiar-estado', $detalleCompra->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este detalle?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($detalleCompra->estado)
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
                                    No se encontraron detalles de compra.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-4">
                {{ $detallesCompra->links() }}
            </div>

        </div>
    </div>
@endsection
