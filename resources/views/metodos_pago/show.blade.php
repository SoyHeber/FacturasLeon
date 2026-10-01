@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Método de Pago</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $metodoPago->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $metodoPago->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $metodoPago->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($metodoPago->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $metodoPago->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $metodoPago->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('metodos_pago.index') }}" class="btn btn-secondary">Volver</a>
                        @can('metodos_pago.modificar')
                            <a href="{{ route('metodos_pago.edit', $metodoPago->id) }}" class="btn btn-warning">Editar</a>
                        @endcan

                        @can('metodos_pago.eliminar')
                            <form action="{{ route('metodos_pago.cambiar-estado', $metodoPago->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de este método de pago?')">
                                @csrf
                                @method('PATCH')

                                @if ($metodoPago->estado)
                                    <button type="submit" class="btn btn-secondary">Inactivar</button>
                                @else
                                    <button type="submit" class="btn btn-success">Activar</button>
                                @endif
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
