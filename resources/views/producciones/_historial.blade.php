<h2 class="h5 mt-4">Historial de consumos</h2>
@if ($produccion->consumos->isNotEmpty())
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr><th>Consumo</th><th>Material histórico</th><th>Unidad</th><th>Cantidad por unidad</th><th>Unidades producidas</th><th>Cantidad consumida</th></tr>
            </thead>
            <tbody>
                @foreach ($produccion->consumos as $consumo)
                    <tr>
                        <td>#{{ $consumo->id }}</td>
                        <td>{{ $consumo->nombre_material }}</td>
                        <td>{{ $consumo->unidad_medida }}</td>
                        <td>{{ $consumo->cantidad_requerida }}</td>
                        <td>{{ $consumo->cantidad_producida }}</td>
                        <td>{{ $consumo->cantidad_consumida }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted">Sin consumos registrados.</p>
@endif

<h2 class="h5">Historial de movimientos</h2>
@if ($produccion->movimientosInventarioCompra->isNotEmpty())
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr><th>Movimiento</th><th>Consumo</th><th>Inventario</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th><th>Motivo</th><th>Estado</th></tr>
            </thead>
            <tbody>
                @foreach ($produccion->movimientosInventarioCompra as $movimiento)
                    <tr>
                        <td>
                            @can('movimientos_inventario_compra.ver')
                                <a href="{{ route('movimientos_inventario_compra.show', $movimiento->id) }}">#{{ $movimiento->id }}</a>
                            @else
                                #{{ $movimiento->id }}
                            @endcan
                        </td>
                        <td>{{ $movimiento->consumo_produccion_id ? '#'.$movimiento->consumo_produccion_id : '-' }}</td>
                        <td>#{{ $movimiento->inventario_compra_id }}</td>
                        <td>{{ $movimiento->tipo_movimiento }}</td>
                        <td>{{ $movimiento->cantidad }}</td>
                        <td>{{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                        <td>{{ $movimiento->motivo }}</td>
                        <td>{{ $movimiento->estado ? 'Activo' : 'Inactivo' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted">Sin movimientos registrados.</p>
@endif
