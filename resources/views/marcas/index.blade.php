@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Marcas</h1>
        <a href="{{ route('marcas.create') }}" class="btn btn-primary">Nueva marca</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($marcas->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="220">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($marcas as $marca)
                                <tr>
                                    <td>{{ $marca->id }}</td>
                                    <td>{{ $marca->nombre }}</td>
                                    <td>{{ $marca->descripcion ?: 'Sin descripción' }}</td>
                                    <td>
                                        @if ($marca->estado)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>{{ $marca->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('marcas.show', $marca->id) }}"
                                                class="btn btn-info btn-sm text-white">Ver</a>
                                            <a href="{{ route('marcas.edit', $marca->id) }}"
                                                class="btn btn-warning btn-sm">Editar</a>

                                            {{-- <form action="{{ route('marcas.destroy', $marca->id) }}" method="POST"
                                                onsubmit="return confirm('¿Deseas eliminar esta marca?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                            </form> --}}
                                            <form action="{{ route('marcas.cambiar-estado', $marca->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')

                                                @if ($marca->estado)
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

                {{ $marcas->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay marcas registradas.
                </div>
            @endif
        </div>
    </div>
@endsection
