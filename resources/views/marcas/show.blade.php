@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Marca</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $marca->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $marca->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Descripción</h5>
                        <p class="mb-0">{{ $marca->descripcion ?: 'Sin descripción' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($marca->estado)
                            <span class="badge bg-success">Activa</span>
                        @else
                            <span class="badge bg-secondary">Inactiva</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $marca->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $marca->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('marcas.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('marcas.edit', $marca->id) }}" class="btn btn-warning">Editar</a>

                        {{-- <form action="{{ route('marcas.destroy', $marca->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas eliminar esta marca?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form> --}}

                        <form action="{{ route('marcas.cambiar-estado', $marca->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de esta marca?')">
                            @csrf
                            @method('PATCH')

                            @if ($marca->estado)
                                <button type="submit" class="btn btn-secondary">Inactivar</button>
                            @else
                                <button type="submit" class="btn btn-success">Activar</button>
                            @endif
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
