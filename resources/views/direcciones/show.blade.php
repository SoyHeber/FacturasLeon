@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Dirección</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $direccion->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">País</h5>
                        <p class="mb-0">{{ $direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Departamento</h5>
                        <p class="mb-0">{{ $direccion->municipio->departamento->nombre ?? 'Sin departamento' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Municipio</h5>
                        <p class="mb-0">{{ $direccion->municipio->nombre ?? 'Sin municipio' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Dirección</h5>
                        <p class="mb-0">{{ $direccion->direccion }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Referencia</h5>
                        <p class="mb-0">{{ $direccion->referencia ?: 'Sin referencia' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código Postal</h5>
                        <p class="mb-0">{{ $direccion->codigo_postal ?: 'Sin código postal' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($direccion->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $direccion->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $direccion->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('direcciones.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('direcciones.edit', $direccion->id) }}" class="btn btn-warning">Editar</a>

                        <form action="{{ route('direcciones.cambiar-estado', $direccion->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de esta dirección?')">
                            @csrf
                            @method('PATCH')

                            @if ($direccion->estado)
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
