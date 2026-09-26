@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Acciones</h1>
        @can('acciones.crear')
            <a href="{{ route('acciones.create') }}" class="btn btn-primary">Nueva acción</a>
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
            @if ($acciones->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Clave</th>
                                <th>Descripción</th>
                                <th>Opciones que la usan</th>
                                <th>Estado</th>
                                <th width="220">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($acciones as $accion)
                                <tr>
                                    <td>{{ $accion->id }}</td>
                                    <td>{{ $accion->nombre }}</td>
                                    <td><code>{{ $accion->clave }}</code></td>
                                    <td>{{ $accion->descripcion ?: 'Sin descripción' }}</td>
                                    <td>{{ $accion->opciones_count }}</td>
                                    <td>
                                        @if ($accion->estado)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('acciones.show', $accion->id) }}"
                                                class="btn btn-info btn-sm text-white">Ver</a>

                                            @can('acciones.modificar')
                                                <a href="{{ route('acciones.edit', $accion->id) }}"
                                                    class="btn btn-warning btn-sm">Editar</a>
                                            @endcan

                                            @can('acciones.eliminar')
                                                <form action="{{ route('acciones.cambiar-estado', $accion->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')

                                                    @if ($accion->estado)
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

                {{ $acciones->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay acciones registradas.
                </div>
            @endif
        </div>
    </div>
@endsection
