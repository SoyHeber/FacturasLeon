@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle de Material por Producto
        </h1>

        <p class="text-muted mb-0">
            Consulta la receta asociada al producto.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $materialProducto->id }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Producto
                    </span>

                    <strong>
                        {{ $materialProducto->producto->nombre ?? 'Sin producto' }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Material / Insumo
                    </span>

                    <strong>
                        {{ $materialProducto->inventarioCompra->nombre ?? 'Sin material' }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Cantidad requerida
                    </span>

                    <strong class="fs-5">
                        {{ number_format($materialProducto->cantidad_requerida, 4) }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Unidad de medida
                    </span>

                    <span class="badge bg-dark">
                        {{ $materialProducto->inventarioCompra->unidad_medida ?? '' }}
                    </span>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($materialProducto->estado)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Activo
                        </span>
                    @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Inactivo
                        </span>
                    @endif

                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted d-block">
                    Observación
                </span>

                <p class="mb-0">
                    {{ $materialProducto->observacion ?: 'Sin observación' }}
                </p>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('materiales_producto.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @can('materiales_producto.modificar')
                    <a href="{{ route('materiales_producto.edit', $materialProducto->id) }}" class="btn btn-warning">
                        Editar
                    </a>
                @endcan

                @can('materiales_producto.eliminar')
                    <form method="POST" action="{{ route('materiales_producto.cambiar-estado', $materialProducto->id) }}"
                        onsubmit="return confirm('¿Deseas cambiar el estado de este material?')">

                        @csrf
                        @method('PATCH')

                        @if ($materialProducto->estado)
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

            </div>

        </div>
    </div>
@endsection
