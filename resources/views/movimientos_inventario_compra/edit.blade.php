@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Movimiento de Inventario de Compra
        </h1>

        <p class="text-muted mb-0">
            Modifica la información del movimiento registrado.
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

            <form action="{{ route('movimientos_inventario_compra.update', $movimientoInventarioCompra->id) }}"
                method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="inventario_compra_id" class="form-label fw-semibold">
                            Material / Insumo
                        </label>

                        <select name="inventario_compra_id" id="inventario_compra_id" class="form-select" required>

                            @foreach ($inventariosCompra as $inventarioCompra)
                                <option value="{{ $inventarioCompra->id }}"
                                    {{ old('inventario_compra_id', $movimientoInventarioCompra->inventario_compra_id) == $inventarioCompra->id
                                        ? 'selected'
                                        : '' }}>

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

                            <option value="ENTRADA"
                                {{ old('tipo_movimiento', $movimientoInventarioCompra->tipo_movimiento) === 'ENTRADA' ? 'selected' : '' }}>
                                ENTRADA
                            </option>

                            <option value="SALIDA"
                                {{ old('tipo_movimiento', $movimientoInventarioCompra->tipo_movimiento) === 'SALIDA' ? 'selected' : '' }}>
                                SALIDA
                            </option>

                            <option value="AJUSTE"
                                {{ old('tipo_movimiento', $movimientoInventarioCompra->tipo_movimiento) === 'AJUSTE' ? 'selected' : '' }}>
                                AJUSTE
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label for="cantidad" class="form-label fw-semibold">
                            Cantidad
                        </label>

                        <input type="number" name="cantidad" id="cantidad" class="form-control" step="0.0001"
                            value="{{ old('cantidad', $movimientoInventarioCompra->cantidad) }}" required>

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
                                    {{ old('detalle_compra_id', $movimientoInventarioCompra->detalle_compra_id) == $detalleCompra->id
                                        ? 'selected'
                                        : '' }}>

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
                                    {{ old('produccion_id', $movimientoInventarioCompra->produccion_id) == $produccion->id ? 'selected' : '' }}>

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
                            value="{{ old('fecha_movimiento', optional($movimientoInventarioCompra->fecha_movimiento)->format('Y-m-d\TH:i')) }}"
                            required>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="motivo" class="form-label fw-semibold">
                        Motivo
                    </label>

                    <input type="text" name="motivo" id="motivo" class="form-control" maxlength="150"
                        value="{{ old('motivo', $movimientoInventarioCompra->motivo) }}">

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4">{{ old('observacion', $movimientoInventarioCompra->observacion) }}</textarea>

                </div>

                <div class="alert alert-light border">
                    Al actualizar un movimiento activo, el sistema revierte el efecto anterior y aplica el nuevo movimiento.
                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', $movimientoInventarioCompra->estado) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('movimientos_inventario_compra.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
