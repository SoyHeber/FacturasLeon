@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Municipio</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $municipio->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">País</h5>
                        <p class="mb-0">{{ $municipio->departamento->pais->nombre ?? 'Sin país' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Departamento</h5>
                        <p class="mb-0">{{ $municipio->departamento->nombre ?? 'Sin departamento' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $municipio->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código</h5>
                        <p class="mb-0">{{ $municipio->codigo ?: 'Sin código' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($municipio->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $municipio->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $municipio->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('municipios.index') }}" class="btn btn-secondary">Volver</a>
                        @can('municipios.modificar')
                            <a href="{{ route('municipios.edit', $municipio->id) }}" class="btn btn-warning">Editar</a>
                        @endcan

                        @can('municipios.eliminar')
                            <form action="{{ route('municipios.cambiar-estado', $municipio->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de este municipio?')">
                                @csrf
                                @method('PATCH')

                                @if ($municipio->estado)
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
