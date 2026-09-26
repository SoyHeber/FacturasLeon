@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Opción</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $opcion->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Módulo</h5>
                        <p class="mb-0">{{ $opcion->modulo?->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $opcion->icono }} {{ $opcion->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Ruta</h5>
                        <p class="mb-0"><code>{{ $opcion->ruta }}</code></p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $opcion->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Orden en el menú</h5>
                        <p class="mb-0">{{ $opcion->orden }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Acciones que admite</h5>
                        @forelse ($opcion->acciones as $accion)
                            <span class="badge bg-primary">{{ $accion->nombre }}</span>
                        @empty
                            <p class="mb-0 text-muted">Ninguna.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($opcion->estado)
                            <span class="badge bg-success">Activa</span>
                        @else
                            <span class="badge bg-secondary">Inactiva</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Creado por</h5>
                        <p class="mb-0">{{ $opcion->creador?->name ?? 'Sistema' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $opcion->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $opcion->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('opciones.index') }}" class="btn btn-secondary">Volver</a>

                        @can('opciones.modificar')
                            <a href="{{ route('opciones.edit', $opcion->id) }}" class="btn btn-warning">Editar</a>
                        @endcan

                        @can('opciones.eliminar')
                            <form action="{{ route('opciones.cambiar-estado', $opcion->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de esta opción?')">
                                @csrf
                                @method('PATCH')

                                @if ($opcion->estado)
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
