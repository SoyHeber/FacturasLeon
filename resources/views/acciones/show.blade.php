@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Acción</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $accion->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $accion->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Clave</h5>
                        <p class="mb-0"><code>{{ $accion->clave }}</code></p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $accion->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Opciones que la admiten</h5>
                        @forelse ($accion->opciones as $opcion)
                            <span class="badge bg-primary">{{ $opcion->modulo?->nombre }} / {{ $opcion->nombre }}</span>
                        @empty
                            <p class="mb-0 text-muted">Ninguna.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($accion->estado)
                            <span class="badge bg-success">Activa</span>
                        @else
                            <span class="badge bg-secondary">Inactiva</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $accion->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $accion->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('acciones.index') }}" class="btn btn-secondary">Volver</a>

                        @can('acciones.modificar')
                            <a href="{{ route('acciones.edit', $accion->id) }}" class="btn btn-warning">Editar</a>
                        @endcan

                        @can('acciones.eliminar')
                            <form action="{{ route('acciones.cambiar-estado', $accion->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de esta acción?')">
                                @csrf
                                @method('PATCH')

                                @if ($accion->estado)
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
