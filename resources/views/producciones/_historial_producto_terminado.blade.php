<h2 class="h5 mt-4">Movimientos de producto terminado</h2>
<div class="table-responsive mb-4">
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr><th>Movimiento</th><th>Inventario</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th><th>Origen</th><th>Motivo</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @foreach ($produccion->movimientosInventario->sortBy('id') as $movimiento)
                <tr>
                    <td>
                        @can('movimientos_inventario.ver')
                            <a href="{{ route('movimientos_inventario.show', $movimiento->id) }}">#{{ $movimiento->id }}</a>
                        @else
                            #{{ $movimiento->id }}
                        @endcan
                    </td>
                    <td>#{{ $movimiento->inventario_id }}</td>
                    <td>{{ $movimiento->tipo_movimiento === 'ENTRADA' ? 'ENTRADA - Confirmación' : 'SALIDA - Anulación' }}</td>
                    <td>{{ $movimiento->cantidad }}</td>
                    <td>{{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                    <td>Automático - Producción <span class="text-muted">(Solo lectura)</span></td>
                    <td>{{ $movimiento->motivo }}</td>
                    <td>{{ $movimiento->estado ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
