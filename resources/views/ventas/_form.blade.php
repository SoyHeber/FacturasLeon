@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@php
    $ventaActual = $venta ?? null;
    $lineas = old('detalles', $ventaActual?->detalles->toArray() ?? [[
        'cantidad' => '1', 'precio_unitario' => '0', 'porcentaje_descuento' => '0', 'tratamiento_tributario' => 'GRAVADO',
    ]]);
    $lineas = is_array($lineas) ? array_slice(array_values(array_filter($lineas, 'is_array')), 0, 200) : [];
    if ($lineas === []) {
        $lineas = [[]];
    }
@endphp
<div class="row g-4">
    <div class="col-md-6">
        <label for="cliente_id" class="form-label fw-bold">Cliente</label>
        <select name="cliente_id" id="cliente_id" class="form-select rounded-3" required>
            <option value="">Seleccione un cliente</option>
            @if ($ventaActual && !$clientes->contains('id', $ventaActual->cliente_id))
                <option value="{{ $ventaActual->cliente_id }}" selected>{{ $ventaActual->receptor_nombre }} · Inactivo</option>
            @endif
            @foreach ($clientes as $cliente)
                @php
                    $nombreCliente = $ventaActual && $cliente->id === $ventaActual->cliente_id
                        ? $ventaActual->receptor_nombre
                        : ($cliente->sociedad?->nombre ?? trim(implode(' ', array_filter([
                            $cliente->persona?->nombre1, $cliente->persona?->nombre2, $cliente->persona?->nombre3,
                            $cliente->persona?->apellido1, $cliente->persona?->apellido2, $cliente->persona?->apellido_casada,
                        ]))));
                @endphp
                <option value="{{ $cliente->id }}" @selected(old('cliente_id', $ventaActual?->cliente_id) == $cliente->id)>
                    {{ $nombreCliente ?: 'Sin nombre fiscal' }} · {{ $cliente->numero_identificacion }}
                </option>
            @endforeach
        </select>
        @if ($ventaActual)
            <small class="text-muted">Los datos históricos se conservan al mantener el mismo cliente.</small>
        @endif
    </div>
    <div class="col-md-3">
        <label for="tipo_documento_id" class="form-label fw-bold">Tipo de documento</label>
        <select name="tipo_documento_id" id="tipo_documento_id" class="form-select rounded-3" required>
            @forelse ($tiposDocumento as $tipoDocumento)
                <option value="{{ $tipoDocumento->id }}" @selected(old('tipo_documento_id', $ventaActual?->tipo_documento_id) == $tipoDocumento->id)>{{ $tipoDocumento->codigo }} · {{ $tipoDocumento->nombre }}</option>
            @empty
                <option value="">No hay FACT activo</option>
            @endforelse
        </select>
    </div>
    <div class="col-md-3">
        <label for="metodo_pago_id" class="form-label fw-bold">Método de pago</label>
        <select name="metodo_pago_id" id="metodo_pago_id" class="form-select rounded-3" required>
            <option value="">Seleccione</option>
            @foreach ($metodosPago as $metodo)
                <option value="{{ $metodo->id }}" @selected(old('metodo_pago_id', $ventaActual?->metodo_pago_id) == $metodo->id)>{{ $metodo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="fecha" class="form-label fw-bold">Fecha</label>
        <input type="date" name="fecha" id="fecha" value="{{ old('fecha', $ventaActual?->fecha->format('Y-m-d') ?? now('America/Guatemala')->format('Y-m-d')) }}" class="form-control rounded-3" required>
    </div>
    <div class="col-md-2">
        <label for="moneda" class="form-label fw-bold">Moneda</label>
        <select name="moneda" id="moneda" class="form-select rounded-3"><option value="GTQ">GTQ</option></select>
    </div>
    <div class="col-md-6">
        <label for="observacion" class="form-label fw-bold">Observación</label>
        <textarea name="observacion" id="observacion" rows="2" maxlength="5000" class="form-control rounded-3">{{ old('observacion', $ventaActual?->observacion) }}</textarea>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h2 class="h5 fw-bold mb-0">Productos</h2>
    <button type="button" id="agregar-producto" class="btn btn-outline-warning btn-sm">+ Agregar producto</button>
</div>
<p class="text-muted">Cantidades enteras, unidad UNI y precios con IVA incluido. Los importes se calculan al guardar.</p>
<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead class="table-dark"><tr><th>Línea</th><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><th>Descuento %</th><th>Tributación</th><th>Acciones</th></tr></thead>
        <tbody id="lineas-venta">
            @foreach ($lineas as $indice => $detalle)
                @include('ventas._linea', compact('indice', 'detalle'))
            @endforeach
        </tbody>
    </table>
</div>
<template id="plantilla-producto">
    @include('ventas._linea', ['indice' => '__INDICE__', 'detalle' => []])
</template>

@push('scripts')
<script>
    (() => {
        const cuerpo = document.getElementById('lineas-venta');
        const ordenar = () => {
            cuerpo.querySelectorAll('tr').forEach((fila, indice) => {
                fila.querySelector('[data-linea]').textContent = String(indice + 1);
                fila.querySelectorAll('[data-campo]').forEach(campo => {
                    campo.name = `detalles[${indice}][${campo.dataset.campo}]`;
                });
            });
        };
        document.getElementById('agregar-producto').addEventListener('click', () => {
            if (cuerpo.children.length >= 200) return;
            cuerpo.appendChild(document.getElementById('plantilla-producto').content.cloneNode(true));
            ordenar();
        });
        cuerpo.addEventListener('click', evento => {
            const boton = evento.target.closest('[data-quitar]');
            if (boton && cuerpo.children.length > 1) {
                boton.closest('tr').remove();
                ordenar();
            }
        });
        ordenar();
    })();
</script>
@endpush
