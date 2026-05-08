@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Municipios</h1>
        <a href="{{ route('municipios.create') }}" class="btn btn-primary">Nuevo municipio</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($municipios->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>País</th>
                                <th>Departamento</th>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($municipios as $municipio)
                                <tr>
                                    <td>{{ $municipio->id }}</td>
                                    <td>{{ $municipio->departamento->pais->nombre ?? 'Sin país' }}</td>
                                    <td>{{ $municipio->departamento->nombre ?? 'Sin departamento' }}</td>
                                    <td>{{ $municipio->nombre }}</td>
                                    <td>{{ $municipio->codigo ?: 'Sin código' }}</td>
                                    <td>
                                        @if ($municipio->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $municipio->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('municipios.show', $municipio->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('municipios.edit', $municipio->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('municipios.cambiar-estado', $municipio->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este municipio?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($municipio->estado)
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

                {{ $municipios->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay municipios registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
