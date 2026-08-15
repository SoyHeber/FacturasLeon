@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">

        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">
                        Detalle de Inventario
                    </h1>
                </div>

                <div class="card-body">

                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>

                        <p class="mb-0">
                            {{ $inventario->id }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Producto</h5>

                        <p class="mb-0">
                            {{ $inventario->producto->nombre ?? 'Sin producto' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código del producto</h5>

                        <p class="mb-0">
                            {{ $inventario->producto->codigo ?? 'N/A' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Cantidad actual</h5>

                        <p class="mb-0">
                            {{ $inventario->cantidad }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Stock mínimo</h5>

                        <p class="mb-0">
                            {{ $inventario->stock_minimo }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Stock máximo</h5>

                        <p class="mb-0">
                            {{ $inventario->stock_maximo ?? 'Sin límite' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Ubicación</h5>

                        <p class="mb-0">
                            {{ $inventario->ubicacion ?: 'Sin ubicación' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado del stock</h5>

                        @if ($inventario->cantidad < $inventario->stock_minimo)
                            <span class="badge bg-danger">
                                Stock bajo
                            </span>
                        @elseif ($inventario->stock_maximo !== null && $inventario->cantidad > $inventario->stock_maximo)
                            <span class="badge bg-warning text-dark">
                                Sobre stock
                            </span>
                        @else
                            <span class="badge bg-success">
                                Stock normal
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>

                        @if ($inventario->estado)
                            <span class="badge bg-success">
                                Activo
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                Inactivo
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">
                            Fecha de creación
                        </h5>

                        <p class="mb-0">
                            {{ $inventario->created_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">
                            Última actualización
                        </h5>

                        <p class="mb-0">
                            {{ $inventario->updated_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="d-flex gap-2">

                        <a href="{{ route('inventarios.index') }}" class="btn btn-secondary">
                            Volver
                        </a>

                        <a href="{{ route('inventarios.edit', $inventario->id) }}" class="btn btn-warning">
                            Editar
                        </a>

                        <form action="{{ route('inventarios.cambiar-estado', $inventario->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este inventario?')">

                            @csrf
                            @method('PATCH')

                            @if ($inventario->estado)
                                <button type="submit" class="btn btn-secondary">
                                    Inactivar
                                </button>
                            @else
                                <button type="submit" class="btn btn-success">
                                    Activar
                                </button>
                            @endif

                        </form>

                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection
