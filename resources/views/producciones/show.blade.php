@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle de Producción
        </h1>

        <p class="text-muted mb-0">
            Consulta la información del registro de producción.
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
                        #{{ $produccion->id }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Producto
                    </span>

                    <strong>
                        {{ $produccion->producto->nombre ?? 'Sin producto' }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Cantidad producida
                    </span>

                    <strong class="fs-5">
                        {{ $produccion->cantidad }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($produccion->estado)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Activo
                        </span>
                    @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Inactivo
                        </span>
                    @endif

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Fecha de producción
                    </span>

                    <strong>
                        {{ optional($produccion->fecha_produccion)->format('d/m/Y H:i') }}
                    </strong>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Registrado por
                    </span>

                    <strong>
                        {{ $produccion->usuario->name ?? 'Sin usuario' }}
                    </strong>

                    @if ($produccion->usuario?->email)
                        <div class="text-muted small">
                            {{ $produccion->usuario->email }}
                        </div>
                    @endif

                </div>

            </div>

            <hr>

            <div class="mb-4">

                <span class="text-muted d-block">
                    Observación
                </span>

                <p class="mb-0">
                    {{ $produccion->observacion ?: 'Sin observación' }}
                </p>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('producciones.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @can('producciones.modificar')
                    <a href="{{ route('producciones.edit', $produccion->id) }}" class="btn btn-warning">
                        Editar
                    </a>
                @endcan

                @can('producciones.eliminar')
                    <form method="POST" action="{{ route('producciones.cambiar-estado', $produccion->id) }}"
                        onsubmit="return confirm('¿Deseas cambiar el estado de esta producción?')">

                        @csrf
                        @method('PATCH')

                        @if ($produccion->estado)
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
