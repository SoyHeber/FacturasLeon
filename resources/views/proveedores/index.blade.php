@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Proveedores</h1>
        <a href="{{ route('proveedores.create') }}" class="btn btn-primary">Nuevo proveedor</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($proveedores->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Tipo Identificación</th>
                                <th>No. Identificación</th>
                                <th>Dirección</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($proveedores as $proveedor)
                                <tr>
                                    <td>{{ $proveedor->id }}</td>
                                    <td>{{ $proveedor->nombre }}</td>
                                    <td>{{ $proveedor->tipoIdentificacion->nombre ?? 'Sin tipo' }}</td>
                                    <td>{{ $proveedor->numero_identificacion }}</td>
                                    <td>
                                        @if ($proveedor->direccion)
                                            {{ $proveedor->direccion->direccion }}
                                            <br>
                                            <small class="text-muted">
                                                {{ $proveedor->direccion->municipio->nombre ?? 'Sin municipio' }} -
                                                {{ $proveedor->direccion->municipio->departamento->nombre ?? 'Sin departamento' }}
                                                -
                                                {{ $proveedor->direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                                            </small>
                                        @else
                                            Sin dirección
                                        @endif
                                    </td>
                                    <td>{{ $proveedor->telefono ?: 'Sin teléfono' }}</td>
                                    <td>{{ $proveedor->correo ?: 'Sin correo' }}</td>
                                    <td>
                                        @if ($proveedor->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $proveedor->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('proveedores.show', $proveedor->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('proveedores.edit', $proveedor->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('proveedores.cambiar-estado', $proveedor->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este proveedor?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($proveedor->estado)
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

                {{ $proveedores->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay proveedores registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
