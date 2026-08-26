@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Compra
        </h1>

        <p class="text-muted mb-0">
            Modifica la información general del DTE registrado.
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

            <form action="{{ route('compras.update', $compra->id) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="proveedor_id" class="form-label fw-semibold">
                            Proveedor
                        </label>

                        <select name="proveedor_id" id="proveedor_id" class="form-select" required>

                            @foreach ($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}"
                                    {{ old('proveedor_id', $compra->proveedor_id) == $proveedor->id ? 'selected' : '' }}>

                                    {{ $proveedor->nombre }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="tipo_dte" class="form-label fw-semibold">
                            Tipo DTE
                        </label>

                        <select name="tipo_dte" id="tipo_dte" class="form-select" required>

                            <option value="">
                                Seleccione un tipo de DTE
                            </option>

                            <option value="FACT" {{ old('tipo_dte', $compra->tipo_dte) === 'FACT' ? 'selected' : '' }}>
                                FACT - Factura
                            </option>

                        </select>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="serie" class="form-label fw-semibold">
                            Serie
                        </label>

                        <input type="text" name="serie" id="serie" class="form-control"
                            value="{{ old('serie', $compra->serie) }}" maxlength="20" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="numero" class="form-label fw-semibold">
                            Número
                        </label>

                        <input type="number" name="numero" id="numero" class="form-control"
                            value="{{ old('numero', $compra->numero) }}" min="0" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="moneda" class="form-label fw-semibold">
                            Moneda
                        </label>

                        <select name="moneda" id="moneda" class="form-select" required>

                            <option value="">
                                Seleccione una moneda
                            </option>

                            <option value="GTQ" {{ old('moneda', $compra->moneda) === 'GTQ' ? 'selected' : '' }}>
                                GTQ - Quetzales
                            </option>

                            <option value="USD" {{ old('moneda', $compra->moneda) === 'USD' ? 'selected' : '' }}>
                                USD - Dólares
                            </option>

                        </select>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="numero_autorizacion" class="form-label fw-semibold">
                        Número de Autorización / UUID
                    </label>

                    <input type="text" name="numero_autorizacion" id="numero_autorizacion" class="form-control"
                        value="{{ old('numero_autorizacion', $compra->numero_autorizacion) }}" maxlength="36" required>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="fecha_emision" class="form-label fw-semibold">
                            Fecha de Emisión
                        </label>

                        <input type="datetime-local" name="fecha_emision" id="fecha_emision" class="form-control"
                            value="{{ old('fecha_emision', $compra->fecha_emision?->format('Y-m-d\TH:i')) }}" required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="fecha_certificacion" class="form-label fw-semibold">
                            Fecha de Certificación
                        </label>

                        <input type="datetime-local" name="fecha_certificacion" id="fecha_certificacion"
                            class="form-control"
                            value="{{ old('fecha_certificacion', $compra->fecha_certificacion?->format('Y-m-d\TH:i')) }}">

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
                            value="{{ old('importe_bruto', $compra->importe_bruto) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe Descuento
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_descuento" class="form-control"
                            value="{{ old('importe_descuento', $compra->importe_descuento) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe Exento
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_exento" class="form-control"
                            value="{{ old('importe_exento', $compra->importe_exento) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe Otros
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_otros" class="form-control"
                            value="{{ old('importe_otros', $compra->importe_otros) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe Neto
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_neto" class="form-control"
                            value="{{ old('importe_neto', $compra->importe_neto) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe IVA
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_iva" class="form-control"
                            value="{{ old('importe_iva', $compra->importe_iva) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Importe Total
                        </label>

                        <input type="number" step="0.01" min="0" name="importe_total" class="form-control"
                            value="{{ old('importe_total', $compra->importe_total) }}" required>
                    </div>

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4">{{ old('observacion', $compra->observacion) }}</textarea>

                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', $compra->estado) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>

    </div>

@endsection
