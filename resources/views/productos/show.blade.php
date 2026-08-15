@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">
                        Detalle de Producto
                    </h1>
                </div>

                <div class="card-body">

                    @if ($producto->img_path)
                        <div class="mb-4 text-center">

                            <img src="{{ asset('storage/' . $producto->img_path) }}" alt="{{ $producto->nombre }}"
                                width="250" height="250" class="rounded border" style="object-fit: cover;">

                        </div>
                    @endif

                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">
                            {{ $producto->id }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código</h5>
                        <p class="mb-0">
                            {{ $producto->codigo }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">
                            {{ $producto->nombre }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Categoría</h5>
                        <p class="mb-0">
                            {{ $producto->categoria->nombre ?? 'Sin categoría' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Marca</h5>
                        <p class="mb-0">
                            {{ $producto->marca->nombre ?? 'Sin marca' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">
                            {{ $producto->descripcion ?: 'Sin descripción' }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>

                        @if ($producto->estado)
                            <span class="badge bg-success">
                                Activo
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                Inactivo
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">
                            Fecha de creación
                        </h5>

                        <p class="mb-0">
                            {{ $producto->created_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">
                            Última actualización
                        </h5>

                        <p class="mb-0">
                            {{ $producto->updated_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="d-flex gap-2">

                        <a href="{{ route('productos.index') }}" class="btn btn-secondary">
                            Volver
                        </a>

                        <a href="{{ route('productos.edit', $producto->id) }}" class="btn btn-warning">
                            Editar
                        </a>

                        <form action="{{ route('productos.cambiar-estado', $producto->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este producto?')">
                            @csrf
                            @method('PATCH')

                            @if ($producto->estado)
                                <button type="submit" class="btn btn-secondary">
                                    Inactivar
                                </button>
                            @else
                                <button type="submit" class="btn btn-success">
                                    Activar
                                </button>
                            @endif

                        </form>

                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection
