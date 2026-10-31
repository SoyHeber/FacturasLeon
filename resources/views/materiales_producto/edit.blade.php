@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">Editar receta del producto</h1>
        <p class="text-muted mb-0">Modifica la receta completa de {{ $producto->nombre }}. Los cambios se aplican a futuras producciones.</p>
    </div>
    <div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
        <form action="{{ route('materiales_producto.update', $materialProducto->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('materiales_producto._form')
        </form>
    </div></div>
@endsection
