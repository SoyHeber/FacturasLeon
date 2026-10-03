@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Movimientos de Inventario
            </h1>

            <p class="text-muted mb-0">
                Administra las entradas, salidas y ajustes realizados sobre el inventario.
            </p>
        </div>

        @can('movimientos_inventario.crear')
            <a href="{{ route('movimientos_inventario.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo movimiento
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

            <form id="filtros" method="GET" action="{{ route('movimientos_inventario.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr class="table-dark">
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                        {{-- Filtros --}}
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
                                <select form="filtros" name="tipo_movimiento" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="ENTRADA"
                                        {{ request('tipo_movimiento') === 'ENTRADA' ? 'selected' : '' }}>
                                        Entrada
                                    </option>

                                    <option value="SALIDA"
                                        {{ request('tipo_movimiento') === 'SALIDA' ? 'selected' : '' }}>
                                        Salida
                                    </option>

                                    <option value="AJUSTE"
                                        {{ request('tipo_movimiento') === 'AJUSTE' ? 'selected' : '' }}>
                                        Ajuste
                                    </option>

                                </select>
                            </th>

                            <th>
                                <input form="filtros" type="number" name="cantidad" value="{{ request('cantidad') }}"
                                    class="form-control form-control-sm" placeholder="Cantidad">
                            </th>

                            <th>
                                <input form="filtros" type="date" name="fecha" value="{{ request('fecha') }}"
                                    class="form-control form-control-sm">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="motivo" value="{{ request('motivo') }}"
                                    class="form-control form-control-sm" placeholder="Motivo">
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

                                    <a href="{{ route('movimientos_inventario.index') }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($movimientos as $movimiento)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $movimiento->id }}
                                </td>

                                <td>
                                    <strong>
                                        {{ $movimiento->inventario->producto->nombre ?? 'Sin producto' }}
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        {{ $movimiento->inventario->producto->codigo ?? 'Sin código' }}
                                    </small>
                                </td>

                                <td>
                                    @if ($movimiento->tipo_movimiento === 'ENTRADA')
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Entrada
                                        </span>
                                    @elseif ($movimiento->tipo_movimiento === 'SALIDA')
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Salida
                                        </span>
                                    @else
                                        <span
                                            class="badge rounded-pill bg-warning-subtle text-warning-emphasis px-3 py-2">
                                            Ajuste
                                        </span>
                                    @endif
                                </td>

                                <td class="fw-semibold">
                                    {{ $movimiento->cantidad }}
                                </td>

                                <td>
                                    {{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    {{ $movimiento->motivo ?: 'Sin motivo' }}
                                </td>

                                <td>
                                    @if ($movimiento->estado)
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

                                        @can('movimientos_inventario.ver')
                                            <a href="{{ route('movimientos_inventario.show', $movimiento->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @if (! $movimiento->esAutomatico())
                                            @can('movimientos_inventario.modificar')
                                                <a href="{{ route('movimientos_inventario.edit', $movimiento->id) }}"
                                                    class="btn btn-outline-warning btn-sm">
                                                    Editar
                                                </a>
                                            @endcan

                                            @can('movimientos_inventario.eliminar')
                                                <form method="POST"
                                                    action="{{ route('movimientos_inventario.cambiar-estado', $movimiento->id) }}"
                                                    onsubmit="return confirm('¿Deseas cambiar el estado de este movimiento?')">

                                                    @csrf
                                                    @method('PATCH')

                                                    @if ($movimiento->estado)
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
                                        @elseif ($movimiento->detalle_venta_id !== null)
                                            <span class="text-muted">Automático - Venta · Detalle #{{ $movimiento->detalle_venta_id }} · Solo lectura</span>
                                        @else
                                            <span class="text-muted">Producción #{{ $movimiento->produccion_id }} · Solo lectura</span>
                                        @endif

                                    </div>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron movimientos de inventario.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-4">
                {{ $movimientos->links() }}
            </div>

        </div>

    </div>
@endsection
