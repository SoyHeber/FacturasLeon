@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de País</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $pais->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $pais->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Código</h5>
                        <p class="mb-0">{{ $pais->codigo ?: 'Sin código' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($pais->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $pais->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $pais->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('paises.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('paises.edit', $pais->id) }}" class="btn btn-warning">Editar</a>

                        <form action="{{ route('paises.cambiar-estado', $pais->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este país?')">
                            @csrf
                            @method('PATCH')

                            @if ($pais->estado)
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
