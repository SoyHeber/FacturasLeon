@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Módulos</h1>
        @can('modulos.crear')
            <a href="{{ route('modulos.create') }}" class="btn btn-primary">Nuevo módulo</a>
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
            @if ($modulos->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Orden</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Opciones</th>
                                <th>Estado</th>
                                <th width="220">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modulos as $modulo)
                                <tr>
                                    <td>{{ $modulo->id }}</td>
                                    <td>{{ $modulo->orden }}</td>
                                    <td>{{ $modulo->icono }} {{ $modulo->nombre }}</td>
                                    <td>{{ $modulo->descripcion ?: 'Sin descripción' }}</td>
                                    <td>{{ $modulo->opciones_count }}</td>
                                    <td>
                                        @if ($modulo->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('modulos.show', $modulo->id) }}"
                                                class="btn btn-info btn-sm text-white">Ver</a>

                                            @can('modulos.modificar')
                                                <a href="{{ route('modulos.edit', $modulo->id) }}"
                                                    class="btn btn-warning btn-sm">Editar</a>
                                            @endcan

                                            @can('modulos.eliminar')
                                                <form action="{{ route('modulos.cambiar-estado', $modulo->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')

                                                    @if ($modulo->estado)
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

                {{ $modulos->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay módulos registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
