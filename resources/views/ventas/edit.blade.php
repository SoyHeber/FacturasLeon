@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Editar venta #{{ $venta->id }}</h1>
            <p class="module-subtitle">Actualiza los productos de la venta en borrador.</p>
        </div>
        @can('ventas.ver')
            <a href="{{ route('ventas.show', $venta) }}" class="btn btn-outline-secondary rounded-3 fw-bold">← Volver</a>
        @endcan
    </div>
    <div class="module-card">
        <div class="module-card-body">
            <form action="{{ route('ventas.update', $venta) }}" method="POST">
                @csrf
                @method('PUT')
                @include('ventas._form')
                <div class="d-flex justify-content-end gap-2 mt-4">
                    @can('ventas.modificar')
                        <button type="submit" class="btn-gold">Guardar cambios</button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
@endsection
