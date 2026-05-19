@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Direcciones</h1>
        <a href="{{ route('direcciones.create') }}" class="btn btn-primary">Nueva dirección</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($direcciones->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>País</th>
                                <th>Departamento</th>
                                <th>Municipio</th>
                                <th>Dirección</th>
                                <th>Código Postal</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($direcciones as $direccion)
                                <tr>
                                    <td>{{ $direccion->id }}</td>
                                    <td>{{ $direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}</td>
                                    <td>{{ $direccion->municipio->departamento->nombre ?? 'Sin departamento' }}</td>
                                    <td>{{ $direccion->municipio->nombre ?? 'Sin municipio' }}</td>
                                    <td>{{ $direccion->direccion }}</td>
                                    <td>{{ $direccion->codigo_postal ?: 'Sin código postal' }}</td>
                                    <td>
                                        @if ($direccion->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $direccion->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('direcciones.show', $direccion->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('direcciones.edit', $direccion->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('direcciones.cambiar-estado', $direccion->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de esta dirección?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($direccion->estado)
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

                {{ $direcciones->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay direcciones registradas.
                </div>
            @endif
        </div>
    </div>
@endsection
