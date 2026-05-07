@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Países</h1>
        <a href="{{ route('paises.create') }}" class="btn btn-primary">Nuevo país</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($paises->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paises as $pais)
                                <tr>
                                    <td>{{ $pais->id }}</td>
                                    <td>{{ $pais->nombre }}</td>
                                    <td>{{ $pais->codigo ?: 'Sin código' }}</td>
                                    <td>
                                        @if ($pais->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $pais->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('paises.show', $pais->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('paises.edit', $pais->id) }}" class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('paises.cambiar-estado', $pais->id) }}" method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este país?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($pais->estado)
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

                {{ $paises->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay países registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
