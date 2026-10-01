@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Módulo</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $modulo->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $modulo->icono }} {{ $modulo->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $modulo->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Orden en el menú</h5>
                        <p class="mb-0">{{ $modulo->orden }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($modulo->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Opciones</h5>
                        @forelse ($modulo->opciones as $opcion)
                            <span class="badge {{ $opcion->estado ? 'bg-primary' : 'bg-secondary' }}">
                                {{ $opcion->icono }} {{ $opcion->nombre }}
                            </span>
                        @empty
                            <p class="mb-0 text-muted">El módulo no tiene opciones.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Creado por</h5>
                        <p class="mb-0">{{ $modulo->creador?->name ?? 'Sistema' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $modulo->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $modulo->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('modulos.index') }}" class="btn btn-secondary">Volver</a>

                        @can('modulos.modificar')
                            <a href="{{ route('modulos.edit', $modulo->id) }}" class="btn btn-warning">Editar</a>
                        @endcan

                        @can('modulos.eliminar')
                            <form action="{{ route('modulos.cambiar-estado', $modulo->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de este módulo?')">
                                @csrf
                                @method('PATCH')

                                @if ($modulo->estado)
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
