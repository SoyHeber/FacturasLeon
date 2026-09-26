@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Roles</h1>
        @can('roles.crear')
            <a href="{{ route('roles.create') }}" class="btn btn-primary">Nuevo rol</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($roles->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Usuarios</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $rol)
                                <tr>
                                    <td>{{ $rol->id }}</td>
                                    <td>{{ $rol->nombre }}</td>
                                    <td>{{ $rol->descripcion ?: 'Sin descripción' }}</td>
                                    <td>{{ $rol->usuarios_count }}</td>
                                    <td>
                                        @if ($rol->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $rol->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('roles.show', $rol->id) }}"
                                                class="btn btn-info btn-sm text-white">Ver</a>

                                            @can('roles.modificar')
                                                <a href="{{ route('roles.edit', $rol->id) }}"
                                                    class="btn btn-warning btn-sm">Editar</a>
                                                <a href="{{ route('roles.permisos', $rol->id) }}"
                                                    class="btn btn-dark btn-sm">Permisos</a>
                                            @endcan

                                            @can('roles.eliminar')
                                                <form action="{{ route('roles.cambiar-estado', $rol->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')

                                                    @if ($rol->estado)
                                                        <button type="submit" class="btn btn-secondary btn-sm">
                                                            Inactivar
                                                        </button>
                                                    @else
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            Activar
                                                        </button>
                                                    @endif
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $roles->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay roles registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
