@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Rol</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $rol->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $rol->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $rol->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($rol->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Usuarios con este rol</h5>
                        @forelse ($rol->usuarios as $usuario)
                            <span class="badge bg-primary">{{ $usuario->name }}</span>
                        @empty
                            <p class="mb-0 text-muted">Ningún usuario tiene este rol.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-2">Permisos</h5>
                        @forelse ($permisos as $modulo => $opciones)
                            <div class="mb-2">
                                <strong>{{ $modulo }}</strong>
                                <ul class="mb-0">
                                    @foreach ($opciones as $opcion => $acciones)
                                        <li>
                                            {{ $opcion }}:
                                            {{ $acciones->pluck('accion')->implode(', ') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <p class="mb-0 text-muted">El rol no tiene permisos asignados.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Creado por</h5>
                        <p class="mb-0">{{ $rol->creador?->name ?? 'Sistema' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $rol->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $rol->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Volver</a>

                        @can('roles.modificar')
                            <a href="{{ route('roles.edit', $rol->id) }}" class="btn btn-warning">Editar</a>
                            <a href="{{ route('roles.permisos', $rol->id) }}" class="btn btn-dark">Permisos</a>
                        @endcan

                        @can('roles.eliminar')
                            <form action="{{ route('roles.cambiar-estado', $rol->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de este rol?')">
                                @csrf
                                @method('PATCH')

                                @if ($rol->estado)
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
