@php
    $materialSeleccionado = $linea['inventario_compra_id'] ?? '';
    $materialSeleccionado = is_scalar($materialSeleccionado) ? $materialSeleccionado : '';
    $cantidad = $linea['cantidad_requerida'] ?? '';
    $cantidad = is_scalar($cantidad) ? $cantidad : '';
    $observacion = $linea['observacion'] ?? '';
    $observacion = is_scalar($observacion) ? $observacion : '';
    $idLinea = $linea['id'] ?? null;
    $idLinea = is_scalar($idLinea) ? $idLinea : null;
    $persistida = $edicion && $idLinea;
    $inventarioActual = $inventariosCompra->firstWhere('id', $materialSeleccionado);
    $unidad = $inventarioActual?->unidad_medida ?? '—';
@endphp
<tr>
    <td data-linea>{{ is_int($indice) ? $indice + 1 : '' }}</td>
    <td style="min-width: 230px">
        @if ($persistida)
            <input type="hidden" data-campo="id" name="materiales[{{ $indice }}][id]" value="{{ $idLinea }}">
            <input type="hidden" data-campo="inventario_compra_id" data-unidad="{{ $unidad }}" name="materiales[{{ $indice }}][inventario_compra_id]" value="{{ $materialSeleccionado }}">
            <strong>{{ $inventarioActual?->nombre ?? 'Material no disponible' }}</strong>
            @if ($inventarioActual && !$inventarioActual->estado)<span class="badge bg-secondary">Insumo inactivo</span>@endif
        @else
            <input type="search" data-buscar-material class="form-control form-control-sm mb-1" placeholder="Buscar por nombre" aria-label="Buscar materia prima" autocomplete="off">
            <select data-campo="inventario_compra_id" name="materiales[{{ $indice }}][inventario_compra_id]" class="form-select form-select-sm" aria-label="Materia prima" required>
                <option value="">Seleccione un material</option>
                @foreach ($inventariosCompra->where('estado', true) as $inventarioCompra)
                    <option value="{{ $inventarioCompra->id }}" data-nombre="{{ $inventarioCompra->nombre }}" data-unidad="{{ $inventarioCompra->unidad_medida }}" @selected($materialSeleccionado == $inventarioCompra->id)>{{ $inventarioCompra->id }} · {{ $inventarioCompra->nombre }}</option>
                @endforeach
            </select>
            <small data-total-materiales class="text-muted" aria-live="polite">{{ $inventariosCompra->where('estado', true)->count() }} materiales activos disponibles</small>
        @endif
    </td>
    <td data-unidad>{{ $unidad }}</td>
    <td><input type="text" inputmode="decimal" data-campo="cantidad_requerida" name="materiales[{{ $indice }}][cantidad_requerida]" value="{{ $cantidad }}" class="form-control form-control-sm" style="min-width: 150px" aria-label="Cantidad requerida por unidad del producto" required></td>
    <td><input type="text" data-campo="observacion" name="materiales[{{ $indice }}][observacion]" value="{{ $observacion }}" maxlength="255" class="form-control form-control-sm" style="min-width: 180px" aria-label="Observación del material"></td>
    <td>
        @can($edicion ? 'materiales_producto.modificar' : 'materiales_producto.crear')
            <button type="button" data-quitar class="btn btn-outline-danger btn-sm">Quitar fila</button>
        @endcan
    </td>
</tr>
