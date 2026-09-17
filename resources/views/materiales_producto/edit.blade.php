@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Material por Producto
        </h1>

        <p class="text-muted mb-0">
            Modifica la receta de materiales del producto.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Corrige los siguientes errores:</strong>

                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('materiales_producto.update', $materialProducto->id) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="producto_id" class="form-label fw-semibold">
                            Producto
                        </label>

                        <select name="producto_id" id="producto_id" class="form-select" required>

                            @foreach ($productos as $producto)
                                <option value="{{ $producto->id }}"
                                    {{ old('producto_id', $materialProducto->producto_id) == $producto->id ? 'selected' : '' }}>
                                    {{ $producto->nombre }}
                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="inventario_compra_id" class="form-label fw-semibold">
                            Material / Insumo
                        </label>

                        <select name="inventario_compra_id" id="inventario_compra_id" class="form-select" required>

                            @foreach ($inventariosCompra as $inventarioCompra)
                                <option value="{{ $inventarioCompra->id }}"
                                    {{ old('inventario_compra_id', $materialProducto->inventario_compra_id) == $inventarioCompra->id ? 'selected' : '' }}>

                                    {{ $inventarioCompra->nombre }}
                                    -
                                    {{ $inventarioCompra->unidad_medida }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="cantidad_requerida" class="form-label fw-semibold">
                            Cantidad requerida
                        </label>

                        <input type="number" name="cantidad_requerida" id="cantidad_requerida" class="form-control"
                            step="0.0001" min="0.0001"
                            value="{{ old('cantidad_requerida', $materialProducto->cantidad_requerida) }}" required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="observacion" class="form-label fw-semibold">
                            Observación
                        </label>

                        <input type="text" name="observacion" id="observacion" class="form-control" maxlength="255"
                            value="{{ old('observacion', $materialProducto->observacion) }}">

                    </div>

                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', $materialProducto->estado) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('materiales_producto.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
