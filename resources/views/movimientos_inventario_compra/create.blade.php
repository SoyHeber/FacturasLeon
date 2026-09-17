@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Nuevo Movimiento de Inventario de Compra
        </h1>

        <p class="text-muted mb-0">
            Registra una entrada, salida o ajuste de materiales e insumos.
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

            <form action="{{ route('movimientos_inventario_compra.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="inventario_compra_id" class="form-label fw-semibold">
                            Material / Insumo
                        </label>

                        <select name="inventario_compra_id" id="inventario_compra_id" class="form-select" required>

                            <option value="">
                                Seleccione un material
                            </option>

                            @foreach ($inventariosCompra as $inventarioCompra)
                                <option value="{{ $inventarioCompra->id }}"
                                    {{ old('inventario_compra_id') == $inventarioCompra->id ? 'selected' : '' }}>

                                    {{ $inventarioCompra->nombre }}
                                    -
                                    Stock:
                                    {{ number_format($inventarioCompra->cantidad, 4) }}
                                    {{ $inventarioCompra->unidad_medida }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label for="tipo_movimiento" class="form-label fw-semibold">
                            Tipo de movimiento
                        </label>

                        <select name="tipo_movimiento" id="tipo_movimiento" class="form-select" required>

                            <option value="">
                                Seleccione
                            </option>

                            <option value="ENTRADA" {{ old('tipo_movimiento') === 'ENTRADA' ? 'selected' : '' }}>
                                ENTRADA
                            </option>

                            <option value="SALIDA" {{ old('tipo_movimiento') === 'SALIDA' ? 'selected' : '' }}>
                                SALIDA
                            </option>

                            <option value="AJUSTE" {{ old('tipo_movimiento') === 'AJUSTE' ? 'selected' : '' }}>
                                AJUSTE
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label for="cantidad" class="form-label fw-semibold">
                            Cantidad
                        </label>

                        <input type="number" name="cantidad" id="cantidad" class="form-control" step="0.0001"
                            value="{{ old('cantidad') }}" required>

                        <small class="text-muted">
                            En ajustes puedes usar valores negativos.
                        </small>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="detalle_compra_id" class="form-label fw-semibold">
                            Detalle de Compra
                        </label>

                        <select name="detalle_compra_id" id="detalle_compra_id" class="form-select">

                            <option value="">
                                Sin detalle de compra
                            </option>

                            @foreach ($detallesCompra as $detalleCompra)
                                <option value="{{ $detalleCompra->id }}"
                                    {{ old('detalle_compra_id') == $detalleCompra->id ? 'selected' : '' }}>

                                    Detalle #{{ $detalleCompra->id }}
                                    -
                                    Compra #{{ $detalleCompra->compra_id }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="produccion_id" class="form-label fw-semibold">
                            Producción
                        </label>

                        <select name="produccion_id" id="produccion_id" class="form-select">

                            <option value="">
                                Sin producción
                            </option>

                            @foreach ($producciones as $produccion)
                                <option value="{{ $produccion->id }}"
                                    {{ old('produccion_id') == $produccion->id ? 'selected' : '' }}>

                                    Producción #{{ $produccion->id }}
                                    -
                                    {{ $produccion->producto->nombre ?? 'Producto' }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="fecha_movimiento" class="form-label fw-semibold">
                            Fecha del movimiento
                        </label>

                        <input type="datetime-local" name="fecha_movimiento" id="fecha_movimiento" class="form-control"
                            value="{{ old('fecha_movimiento', now()->format('Y-m-d\TH:i')) }}" required>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="motivo" class="form-label fw-semibold">
                        Motivo
                    </label>

                    <input type="text" name="motivo" id="motivo" class="form-control" maxlength="150"
                        value="{{ old('motivo') }}"
                        placeholder="Ej. Compra de material, consumo en producción, corrección de inventario">

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4">{{ old('observacion') }}</textarea>

                </div>

                <div class="alert alert-light border">
                    <strong>Importante:</strong>
                    un movimiento no debe asociarse al mismo tiempo a un detalle de compra y a una producción.
                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', true) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Guardar
                    </button>

                    <a href="{{ route('movimientos_inventario_compra.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
