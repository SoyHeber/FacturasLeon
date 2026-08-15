@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Productos</h1>
        <a href="{{ route('productos.create') }}" class="btn btn-primary">
            Nuevo producto
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($productos->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Imagen</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Marca</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($productos as $producto)
                                <tr>
                                    <td>{{ $producto->id }}</td>

                                    <td>
                                        @if ($producto->img_path)
                                            <img src="{{ asset('storage/' . $producto->img_path) }}"
                                                alt="{{ $producto->nombre }}" width="70" height="70"
                                                class="rounded border" style="object-fit: cover;">
                                        @else
                                            <span class="text-muted">Sin imagen</span>
                                        @endif
                                    </td>

                                    <td>{{ $producto->codigo }}</td>

                                    <td>{{ $producto->nombre }}</td>

                                    <td>
                                        {{ $producto->categoria->nombre ?? 'Sin categoría' }}
                                    </td>

                                    <td>
                                        {{ $producto->marca->nombre ?? 'Sin marca' }}
                                    </td>

                                    <td>
                                        {{ $producto->descripcion ?: 'Sin descripción' }}
                                    </td>

                                    <td>
                                        @if ($producto->estado)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $producto->created_at?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">

                                            <a href="{{ route('productos.show', $producto->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('productos.edit', $producto->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('productos.cambiar-estado', $producto->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este producto?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($producto->estado)
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

                {{ $productos->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay productos registrados.
                </div>
            @endif
        </div>
    </div>
@endsection
