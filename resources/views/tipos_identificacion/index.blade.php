@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Tipos de Identificación</h1>
        <a href="{{ route('tipos_identificacion.create') }}" class="btn btn-primary">Nuevo tipo de identificación</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($tiposIdentificacion->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tiposIdentificacion as $tipoIdentificacion)
                                <tr>
                                    <td>{{ $tipoIdentificacion->id }}</td>
                                    <td>{{ $tipoIdentificacion->nombre }}</td>
                                    <td>{{ $tipoIdentificacion->codigo }}</td>
                                    <td>{{ $tipoIdentificacion->descripcion ?: 'Sin descripción' }}</td>
                                    <td>
                                        @if ($tipoIdentificacion->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $tipoIdentificacion->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('tipos_identificacion.show', $tipoIdentificacion->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('tipos_identificacion.edit', $tipoIdentificacion->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form
                                                action="{{ route('tipos_identificacion.cambiar-estado', $tipoIdentificacion->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este tipo de identificación?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($tipoIdentificacion->estado)
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $tiposIdentificacion->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay tipos de identificación registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
