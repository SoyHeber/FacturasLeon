@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle del Movimiento
        </h1>

        <p class="text-muted mb-0">
            Consulta la información del movimiento de inventario.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $movimientoInventarioCompra->id }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Material / Insumo
                    </span>

                    <strong>
                        {{ $movimientoInventarioCompra->inventarioCompra->nombre ?? 'Sin material' }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Tipo de movimiento
                    </span>

                    @if ($movimientoInventarioCompra->tipo_movimiento === 'ENTRADA')
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            ENTRADA
                        </span>
                    @elseif ($movimientoInventarioCompra->tipo_movimiento === 'SALIDA')
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            SALIDA
                        </span>
                    @else
                        <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">
                            AJUSTE
                        </span>
                    @endif

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($movimientoInventarioCompra->estado)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Activo
                        </span>
                    @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Inactivo
                        </span>
                    @endif

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Cantidad
                    </span>

                    <strong class="fs-5">
                        {{ number_format($movimientoInventarioCompra->cantidad, 4) }}
                        {{ $movimientoInventarioCompra->inventarioCompra->unidad_medida ?? '' }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Fecha del movimiento
                    </span>

                    <strong>
                        {{ optional($movimientoInventarioCompra->fecha_movimiento)->format('d/m/Y H:i') }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Existencia actual
                    </span>

                    <strong>
                        {{ number_format($movimientoInventarioCompra->inventarioCompra->cantidad ?? 0, 4) }}
                    </strong>

                </div>

            </div>

            <hr>

            <h5 class="fw-bold mb-4">
                Origen del Movimiento
            </h5>

            <div class="row">

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Detalle de Compra
                    </span>

                    @if ($movimientoInventarioCompra->detalleCompra)
                        <strong>
                            Detalle #{{ $movimientoInventarioCompra->detalleCompra->id }}
                        </strong>

                        <div class="text-muted small">
                            Compra #{{ $movimientoInventarioCompra->detalleCompra->compra_id }}
                        </div>
                    @else
                        <span>
                            No asociado
                        </span>
                    @endif

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Producción
                    </span>

                    @if ($movimientoInventarioCompra->produccion)
                        <strong>
                            Producción #{{ $movimientoInventarioCompra->produccion->id }}
                        </strong>

                        <div class="text-muted small">
                            {{ $movimientoInventarioCompra->produccion->producto->nombre ?? '' }}
                        </div>
                    @else
                        <span>
                            No asociada
                        </span>
                    @endif

                </div>

            </div>

            <div class="row">

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Motivo
                    </span>

                    <p class="mb-0">
                        {{ $movimientoInventarioCompra->motivo ?: 'Sin motivo' }}
                    </p>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Observación
                    </span>

                    <p class="mb-0">
                        {{ $movimientoInventarioCompra->observacion ?: 'Sin observación' }}
                    </p>

                </div>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('movimientos_inventario_compra.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                <a href="{{ route('movimientos_inventario_compra.edit', $movimientoInventarioCompra->id) }}"
                    class="btn btn-warning">
                    Editar
                </a>

                <form method="POST"
                    action="{{ route('movimientos_inventario_compra.cambiar-estado', $movimientoInventarioCompra->id) }}"
                    onsubmit="return confirm('¿Deseas cambiar el estado de este movimiento?')">

                    @csrf
                    @method('PATCH')

                    @if ($movimientoInventarioCompra->estado)
                        <button type="submit" class="btn btn-outline-secondary">
                            Inactivar
                        </button>
                    @else
                        <button type="submit" class="btn btn-outline-success">
                            Activar
                        </button>
                    @endif

                </form>

            </div>

        </div>
    </div>
@endsection
