@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Opciones</h1>
        @can('opciones.crear')
            <a href="{{ route('opciones.create') }}" class="btn btn-primary">Nueva opción</a>
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
            @if ($opciones->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Módulo</th>
                                <th>Nombre</th>
                                <th>Ruta</th>
                                <th>Acciones admitidas</th>
                                <th>Estado</th>
                                <th width="220">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($opciones as $opcion)
                                <tr>
                                    <td>{{ $opcion->id }}</td>
                                    <td>{{ $opcion->modulo?->nombre }}</td>
                                    <td>{{ $opcion->icono }} {{ $opcion->nombre }}</td>
                                    <td><code>{{ $opcion->ruta }}</code></td>
                                    <td>{{ $opcion->acciones->pluck('nombre')->implode(', ') ?: 'Ninguna' }}</td>
                                    <td>
                                        @if ($opcion->estado)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('opciones.show', $opcion->id) }}"
                                                class="btn btn-info btn-sm text-white">Ver</a>

                                            @can('opciones.modificar')
                                                <a href="{{ route('opciones.edit', $opcion->id) }}"
                                                    class="btn btn-warning btn-sm">Editar</a>
                                            @endcan

                                            @can('opciones.eliminar')
                                                <form action="{{ route('opciones.cambiar-estado', $opcion->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')

                                                    @if ($opcion->estado)
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

                {{ $opciones->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay opciones registradas.
                </div>
            @endif
        </div>
    </div>
@endsection
