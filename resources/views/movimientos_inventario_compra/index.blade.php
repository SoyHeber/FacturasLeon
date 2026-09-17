@extends('layouts.app-bootstrap')

@section('content')

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Movimientos de Inventario de Compra
            </h1>

            <p class="text-muted mb-0">
                Administra las entradas, salidas y ajustes de materiales e insumos.
            </p>
        </div>

        <a href="{{ route('movimientos_inventario_compra.create') }}" class="btn btn-warning fw-bold px-4 py-2">
            + Nuevo movimiento
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert">
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <form method="GET" action="{{ route('movimientos_inventario_compra.index') }}">

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead>

                            <tr class="table-dark">
                                <th>ID</th>
                                <th>Material / Insumo</th>
                                <th>Detalle Compra</th>
                                <th>Producción</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Observación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                            <tr>

                                <th>
                                    <input type="number" name="id" value="{{ request('id') }}"
                                        class="form-control form-control-sm" placeholder="ID">
                                </th>

                                <th>
                                    <input type="text" name="inventario_compra"
                                        value="{{ request('inventario_compra') }}" class="form-control form-control-sm"
                                        placeholder="Material">
                                </th>

                                <th>
                                    <input type="number" name="detalle_compra_id"
                                        value="{{ request('detalle_compra_id') }}" class="form-control form-control-sm"
                                        placeholder="Detalle">
                                </th>

                                <th>
                                    <input type="number" name="produccion_id" value="{{ request('produccion_id') }}"
                                        class="form-control form-control-sm" placeholder="Producción">
                                </th>

                                <th>
                                    <select name="tipo_movimiento" class="form-select form-select-sm">

                                        <option value="">
                                            Todos
                                        </option>

                                        <option value="ENTRADA"
                                            {{ request('tipo_movimiento') === 'ENTRADA' ? 'selected' : '' }}>
                                            ENTRADA
                                        </option>

                                        <option value="SALIDA"
                                            {{ request('tipo_movimiento') === 'SALIDA' ? 'selected' : '' }}>
                                            SALIDA
                                        </option>

                                        <option value="AJUSTE"
                                            {{ request('tipo_movimiento') === 'AJUSTE' ? 'selected' : '' }}>
                                            AJUSTE
                                        </option>

                                    </select>
                                </th>

                                <th>
                                    <input type="number" step="0.0001" name="cantidad" value="{{ request('cantidad') }}"
                                        class="form-control form-control-sm" placeholder="Cantidad">
                                </th>

                                <th>
                                    <input type="date" name="fecha_movimiento" value="{{ request('fecha_movimiento') }}"
                                        class="form-control form-control-sm">
                                </th>

                                <th>
                                    <input type="text" name="motivo" value="{{ request('motivo') }}"
                                        class="form-control form-control-sm" placeholder="Motivo">
                                </th>

                                <th>
                                    <input type="text" name="observacion" value="{{ request('observacion') }}"
                                        class="form-control form-control-sm" placeholder="Observación">
                                </th>

                                <th>
                                    <select name="estado" class="form-select form-select-sm">

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

                                        <button type="submit" class="btn btn-dark btn-sm">
                                            Filtrar
                                        </button>

                                        <a href="{{ route('movimientos_inventario_compra.index') }}"
                                            class="btn btn-outline-secondary btn-sm">
                                            Limpiar
                                        </a>

                                    </div>
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            @forelse ($movimientosInventarioCompra as $movimiento)
                                <tr>

                                    <td class="fw-semibold">
                                        #{{ $movimiento->id }}
                                    </td>

                                    <td>
                                        {{ $movimiento->inventarioCompra->nombre ?? 'Sin material' }}
                                    </td>

                                    <td>
                                        @if ($movimiento->detalle_compra_id)
                                            #{{ $movimiento->detalle_compra_id }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td>
                                        @if ($movimiento->produccion_id)
                                            #{{ $movimiento->produccion_id }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td>
                                        @if ($movimiento->tipo_movimiento === 'ENTRADA')
                                            <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                                ENTRADA
                                            </span>
                                        @elseif ($movimiento->tipo_movimiento === 'SALIDA')
                                            <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                                SALIDA
                                            </span>
                                        @else
                                            <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">
                                                AJUSTE
                                            </span>
                                        @endif
                                    </td>

                                    <td class="fw-semibold">
                                        {{ number_format($movimiento->cantidad, 4) }}
                                        <small class="text-muted">
                                            {{ $movimiento->inventarioCompra->unidad_medida ?? '' }}
                                        </small>
                                    </td>

                                    <td>
                                        {{ optional($movimiento->fecha_movimiento)->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        {{ $movimiento->motivo ?: '-' }}
                                    </td>

                                    <td>
                                        {{ $movimiento->observacion ?: '-' }}
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

                                            <a href="{{ route('movimientos_inventario_compra.show', $movimiento->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>

                                            <a href="{{ route('movimientos_inventario_compra.edit', $movimiento->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                action="{{ route('movimientos_inventario_compra.cambiar-estado', $movimiento->id) }}"
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

                                        </div>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        No se encontraron movimientos registrados.
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>

                <div class="mt-4">
                    {{ $movimientosInventarioCompra->links() }}
                </div>

            </form>

        </div>
    </div>

@endsection
