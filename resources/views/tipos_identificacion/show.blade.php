@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Tipo de Identificación</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->codigo }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($tipoIdentificacion->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $tipoIdentificacion->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('tipos_identificacion.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('tipos_identificacion.edit', $tipoIdentificacion->id) }}"
                            class="btn btn-warning">Editar</a>

                        <form action="{{ route('tipos_identificacion.cambiar-estado', $tipoIdentificacion->id) }}"
                            method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este tipo de identificación?')">
                            @csrf
                            @method('PATCH')

                            @if ($tipoIdentificacion->estado)
                                <button type="submit" class="btn btn-secondary">Inactivar</button>
                            @else
                                <button type="submit" class="btn btn-success">Activar</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
