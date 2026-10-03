@php
    $precio = (string) ($detalle['precio_unitario'] ?? '0');
    if (str_contains($precio, '.')) {
        $precio = rtrim(rtrim($precio, '0'), '.');
    }
@endphp
<tr>
    <td data-linea></td>
    <td style="min-width: 230px">
        @if (!empty($detalle['id']))
            <input type="hidden" data-campo="id" name="detalles[{{ $indice }}][id]" value="{{ $detalle['id'] }}">
        @endif
        <select data-campo="producto_id" name="detalles[{{ $indice }}][producto_id]" class="form-select form-select-sm" aria-label="Producto" required>
            <option value="">Seleccione</option>
            @if (!empty($detalle['producto_id']) && !$productos->contains('id', $detalle['producto_id']))
                <option value="{{ $detalle['producto_id'] }}" selected>{{ $detalle['producto_nombre'] ?? 'Producto inactivo' }} · Inactivo</option>
            @endif
            @foreach ($productos as $producto)
                <option value="{{ $producto->id }}" @selected(($detalle['producto_id'] ?? '') == $producto->id)>{{ ($detalle['producto_id'] ?? '') == $producto->id ? ($detalle['producto_codigo'] ?? $producto->codigo) : $producto->codigo }} · {{ ($detalle['producto_id'] ?? '') == $producto->id ? ($detalle['producto_nombre'] ?? $producto->nombre) : $producto->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" data-campo="cantidad" name="detalles[{{ $indice }}][cantidad]" value="{{ $detalle['cantidad'] ?? '1' }}" min="1" step="1" class="form-control form-control-sm" style="min-width: 100px" aria-label="Cantidad" required></td>
    <td><input type="number" data-campo="precio_unitario" name="detalles[{{ $indice }}][precio_unitario]" value="{{ $precio }}" min="0" step="0.000001" class="form-control form-control-sm" style="min-width: 140px" aria-label="Precio unitario" required></td>
    <td><input type="number" data-campo="porcentaje_descuento" name="detalles[{{ $indice }}][porcentaje_descuento]" value="{{ $detalle['porcentaje_descuento'] ?? '0' }}" min="0" max="100" step="0.0001" class="form-control form-control-sm" style="min-width: 110px" aria-label="Porcentaje de descuento" required></td>
    <td><select data-campo="tratamiento_tributario" name="detalles[{{ $indice }}][tratamiento_tributario]" class="form-select form-select-sm" aria-label="Tratamiento tributario" required><option value="GRAVADO" @selected(($detalle['tratamiento_tributario'] ?? 'GRAVADO') === 'GRAVADO')>Gravado IVA 12%</option><option value="EXENTO" @selected(($detalle['tratamiento_tributario'] ?? '') === 'EXENTO')>Exento</option></select></td>
    <td><button type="button" data-quitar class="btn btn-outline-danger btn-sm">Quitar</button></td>
</tr>
