@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Inventario de Compra
        </h1>

        <p class="text-muted mb-0">
            Modifica la información del material o insumo.
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

            <form action="{{ route('inventarios_compra.update', $inventarioCompra->id) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="nombre" class="form-label fw-semibold">
                            Nombre
                        </label>

                        <input type="text" name="nombre" id="nombre" class="form-control"
                            value="{{ old('nombre', $inventarioCompra->nombre) }}" maxlength="100" required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="unidad_medida" class="form-label fw-semibold">
                            Unidad de medida
                        </label>

                        <select name="unidad_medida" id="unidad_medida" class="form-select" required>

                            <option value="">
                                Seleccione una unidad
                            </option>

                            <option value="GRAMO"
                                {{ old('unidad_medida', $inventarioCompra->unidad_medida) === 'GRAMO' ? 'selected' : '' }}>
                                Gramo
                            </option>

                            <option value="UNIDAD"
                                {{ old('unidad_medida', $inventarioCompra->unidad_medida) === 'UNIDAD' ? 'selected' : '' }}>
                                Unidad
                            </option>

                            <option value="QUILATE"
                                {{ old('unidad_medida', $inventarioCompra->unidad_medida) === 'QUILATE' ? 'selected' : '' }}>
                                Quilate
                            </option>

                        </select>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="descripcion" class="form-label fw-semibold">
                        Descripción
                    </label>

                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="255">{{ old('descripcion', $inventarioCompra->descripcion) }}</textarea>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <span class="form-label fw-semibold d-block">Cantidad actual</span>
                        <p class="mb-1">{{ $inventarioCompra->cantidad }}</p>
                        <small class="text-muted">El stock se modifica mediante movimientos de inventario.</small>
                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="stock_minimo" class="form-label fw-semibold">
                            Stock mínimo
                        </label>

                        <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" step="0.0001"
                            min="0" value="{{ old('stock_minimo', $inventarioCompra->stock_minimo) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="stock_maximo" class="form-label fw-semibold">
                            Stock máximo
                        </label>

                        <input type="number" name="stock_maximo" id="stock_maximo" class="form-control" step="0.0001"
                            min="0" value="{{ old('stock_maximo', $inventarioCompra->stock_maximo) }}">

                    </div>

                </div>

                <div class="mb-3">

                    <label for="ubicacion" class="form-label fw-semibold">
                        Ubicación
                    </label>

                    <input type="text" name="ubicacion" id="ubicacion" class="form-control"
                        value="{{ old('ubicacion', $inventarioCompra->ubicacion) }}" maxlength="100">

                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', $inventarioCompra->estado) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('inventarios_compra.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
