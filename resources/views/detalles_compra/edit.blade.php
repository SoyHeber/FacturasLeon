@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Detalle de Compra
        </h1>

        <p class="text-muted mb-0">
            Modifica la información del material o insumo comprado.
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

            <form action="{{ route('detalles_compra.update', $detalleCompra->id) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="compra_id" class="form-label fw-semibold">
                            Compra
                        </label>

                        <select name="compra_id" id="compra_id" class="form-select" required>

                            @foreach ($compras as $compra)
                                <option value="{{ $compra->id }}"
                                    {{ old('compra_id', $detalleCompra->compra_id) == $compra->id ? 'selected' : '' }}>

                                    #{{ $compra->id }}
                                    -
                                    {{ $compra->tipoDocumento->codigo }}
                                    {{ $compra->serie }}
                                    -
                                    {{ $compra->numero }}

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
                                    {{ old('inventario_compra_id', $detalleCompra->inventario_compra_id) == $inventarioCompra->id ? 'selected' : '' }}>

                                    {{ $inventarioCompra->nombre }}
                                    -
                                    {{ $inventarioCompra->unidad_medida }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="cantidad" class="form-label fw-semibold">
                            Cantidad
                        </label>

                        <input type="number" name="cantidad" id="cantidad" class="form-control" step="0.0001"
                            min="0.0001" value="{{ old('cantidad', $detalleCompra->cantidad) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="precio_unitario" class="form-label fw-semibold">
                            Precio Unitario
                        </label>

                        <input type="number" name="precio_unitario" id="precio_unitario" class="form-control"
                            step="0.000001" min="0"
                            value="{{ old('precio_unitario', $detalleCompra->precio_unitario) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="porcentaje_descuento" class="form-label fw-semibold">
                            % Descuento
                        </label>

                        <input type="number" name="porcentaje_descuento" id="porcentaje_descuento" class="form-control"
                            step="0.0001" min="0" max="100"
                            value="{{ old('porcentaje_descuento', $detalleCompra->porcentaje_descuento) }}" required>

                    </div>

                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3">
                    Importes
                </h5>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Bruto
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_bruto" class="form-control"
                            value="{{ old('importe_bruto', $detalleCompra->importe_bruto) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Descuento
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_descuento" class="form-control"
                            value="{{ old('importe_descuento', $detalleCompra->importe_descuento) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Exento
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_exento" class="form-control"
                            value="{{ old('importe_exento', $detalleCompra->importe_exento) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Otros
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_otros" class="form-control"
                            value="{{ old('importe_otros', $detalleCompra->importe_otros) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Neto
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_neto" class="form-control"
                            value="{{ old('importe_neto', $detalleCompra->importe_neto) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe IVA
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_iva" class="form-control"
                            value="{{ old('importe_iva', $detalleCompra->importe_iva) }}" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Importe Total
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_total" class="form-control"
                            value="{{ old('importe_total', $detalleCompra->importe_total) }}" required>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4">{{ old('observacion', $detalleCompra->observacion) }}</textarea>

                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', $detalleCompra->estado) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('detalles_compra.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
