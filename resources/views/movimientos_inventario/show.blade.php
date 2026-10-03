@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle del Movimiento
        </h1>

        <p class="text-muted mb-0">
            Consulta la información completa del movimiento de inventario.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $movimiento->id }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Producto
                    </span>

                    <strong>
                        {{ $movimiento->inventario->producto->nombre ?? 'Sin producto' }}
                    </strong>

                    <div class="text-muted">
                        {{ $movimiento->inventario->producto->codigo ?? 'Sin código' }}
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Tipo de Movimiento
                    </span>

                    @if ($movimiento->tipo_movimiento === 'ENTRADA')
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Entrada
                        </span>
                    @elseif ($movimiento->tipo_movimiento === 'SALIDA')
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Salida
                        </span>
                    @else
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis px-3 py-2">
                            Ajuste
                        </span>
                    @endif
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Cantidad
                    </span>

                    <strong>
                        {{ $movimiento->cantidad }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Fecha del Movimiento
                    </span>

                    <strong>
                        {{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($movimiento->estado)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Activo
                        </span>
                    @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Inactivo
                        </span>
                    @endif
                </div>

                <div class="col-md-12 mb-4">
                    <span class="text-muted d-block">
                        Motivo
                    </span>

                    <strong>
                        {{ $movimiento->motivo ?: 'Sin motivo' }}
                    </strong>
                </div>

                <div class="col-md-12 mb-4">
                    <span class="text-muted d-block">
                        Observación
                    </span>

                    <p class="mb-0">
                        {{ $movimiento->observacion ?: 'Sin observación' }}
                    </p>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Fecha de creación
                    </span>

                    <strong>
                        {{ $movimiento->created_at?->format('d/m/Y H:i') }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Última actualización
                    </span>

                    <strong>
                        {{ $movimiento->updated_at?->format('d/m/Y H:i') }}
                    </strong>
                </div>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('movimientos_inventario.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @if (! $movimiento->esAutomatico())
                    @can('movimientos_inventario.modificar')
                        <a href="{{ route('movimientos_inventario.edit', $movimiento->id) }}" class="btn btn-warning">
                            Editar
                        </a>
                    @endcan

                    @can('movimientos_inventario.eliminar')
                        <form method="POST" action="{{ route('movimientos_inventario.cambiar-estado', $movimiento->id) }}"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este movimiento?')">

                            @csrf
                            @method('PATCH')

                            @if ($movimiento->estado)
                                <button type="submit" class="btn btn-outline-secondary">
                                    Inactivar
                                </button>
                            @else
                                <button type="submit" class="btn btn-outline-success">
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

        </div>

    </div>
@endsection
