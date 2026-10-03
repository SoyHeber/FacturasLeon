@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Nueva venta</h1>
            <p class="module-subtitle">Registra los productos y guarda la venta como borrador.</p>
        </div>
        @can('ventas.ver')
            <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">← Volver</a>
        @endcan
    </div>
    <div class="module-card">
        <div class="module-card-body">
            <form action="{{ route('ventas.store') }}" method="POST">
                @csrf
                @include('ventas._form')
                <div class="d-flex justify-content-end gap-2 mt-4">
                    @can('ventas.crear')
                        <button type="submit" class="btn-gold">Guardar borrador</button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
@endsection
