@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Métodos de Pago</h1>
        <a href="{{ route('metodos_pago.create') }}" class="btn btn-primary">Nuevo método de pago</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($metodosPago->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metodosPago as $metodoPago)
                                <tr>
                                    <td>{{ $metodoPago->id }}</td>
                                    <td>{{ $metodoPago->nombre }}</td>
                                    <td>{{ $metodoPago->descripcion ?: 'Sin descripción' }}</td>
                                    <td>
                                        @if ($metodoPago->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $metodoPago->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('metodos_pago.show', $metodoPago->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('metodos_pago.edit', $metodoPago->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('metodos_pago.cambiar-estado', $metodoPago->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este método de pago?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($metodoPago->estado)
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

                {{ $metodosPago->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay métodos de pago registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
