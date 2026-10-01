@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle del Inventario de Compra
        </h1>

        <p class="text-muted mb-0">
            Consulta la información del material o insumo.
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
                        #{{ $inventarioCompra->id }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Nombre
                    </span>

                    <strong>
                        {{ $inventarioCompra->nombre }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Unidad de medida
                    </span>

                    <span class="badge bg-dark">
                        {{ $inventarioCompra->unidad_medida }}
                    </span>

                </div>

                <div class="col-md-12 mb-4">

                    <span class="text-muted d-block">
                        Descripción
                    </span>

                    <p class="mb-0">
                        {{ $inventarioCompra->descripcion ?: 'Sin descripción' }}
                    </p>

                </div>

            </div>

            <hr>

            <h5 class="fw-bold mb-4">
                Existencias
            </h5>

            <div class="row">

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Cantidad actual
                    </span>

                    <strong class="fs-5">
                        {{ number_format($inventarioCompra->cantidad, 4) }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Stock mínimo
                    </span>

                    <strong>
                        {{ number_format($inventarioCompra->stock_minimo, 4) }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Stock máximo
                    </span>

                    <strong>
                        @if ($inventarioCompra->stock_maximo !== null)
                            {{ number_format($inventarioCompra->stock_maximo, 4) }}
                        @else
                            No definido
                        @endif
                    </strong>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Ubicación
                    </span>

                    <strong>
                        {{ $inventarioCompra->ubicacion ?: 'Sin ubicación' }}
                    </strong>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($inventarioCompra->estado)
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

            <div class="d-flex gap-2">

                <a href="{{ route('inventarios_compra.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @can('inventarios_compra.modificar')
                    <a href="{{ route('inventarios_compra.edit', $inventarioCompra->id) }}" class="btn btn-warning">
                        Editar
                    </a>
                @endcan

                @can('inventarios_compra.eliminar')
                    <form method="POST" action="{{ route('inventarios_compra.cambiar-estado', $inventarioCompra->id) }}"
                        onsubmit="return confirm('¿Deseas cambiar el estado de este inventario?')">

                        @csrf
                        @method('PATCH')

                        @if ($inventarioCompra->estado)
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
