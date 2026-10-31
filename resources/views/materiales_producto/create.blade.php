@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">Agregar materiales a la receta</h1>
        <p class="text-muted mb-0">Selecciona un producto y registra las materias primas necesarias para fabricar una unidad.</p>
    </div>
    <div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
        <form action="{{ route('materiales_producto.store') }}" method="POST">
            @csrf
            @include('materiales_producto._form')
        </form>
    </div></div>
@endsection
