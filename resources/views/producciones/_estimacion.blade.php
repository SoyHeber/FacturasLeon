<div class="border rounded p-3 mb-4" id="estimacion-materiales">
    <h2 class="h5">Estimación de materiales</h2>
    <p class="text-muted mb-3">
        Usa la receta actual. Es una estimación y todavía no afecta inventario.
        No verifica stock disponible ni representa consumos históricos.
    </p>

    @isset($rutaEstimacion)
        <button type="button" class="btn btn-outline-primary mb-3" id="recalcular-estimacion"
            data-ruta="{{ $rutaEstimacion }}">Estimar materiales</button>
    @endisset

    <div id="resultado-estimacion" aria-live="polite">
        @if ($errorEstimacion)
            <div class="alert alert-warning mb-0">{{ $errorEstimacion }}</div>
        @elseif (count($estimacion))
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Material</th>
                            <th>Unidad</th>
                            <th>Cantidad por unidad</th>
                            <th>Cantidad total estimada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($estimacion as $linea)
                            <tr>
                                <td>{{ $linea['material'] }}</td>
                                <td>{{ $linea['unidad'] }}</td>
                                <td>{{ $linea['cantidad_por_unidad'] }}</td>
                                <td>{{ $linea['cantidad_total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">Selecciona un producto y una cantidad para estimar sus materiales.</p>
        @endif
    </div>
</div>

@isset($rutaEstimacion)
    @push('scripts')
        <script>
            document.getElementById('recalcular-estimacion').addEventListener('click', function () {
                const destino = new URL(this.dataset.ruta, window.location.origin);
                for (const campo of ['producto_id', 'cantidad', 'fecha_produccion', 'observacion']) {
                    destino.searchParams.set(campo, document.getElementById(campo).value);
                }
                window.location.assign(destino.toString());
            });

            for (const campo of ['producto_id', 'cantidad']) {
                document.getElementById(campo).addEventListener('input', function () {
                    document.getElementById('resultado-estimacion').textContent =
                        'Cambiaste el producto o la cantidad. Pulsa Estimar materiales para actualizar la estimación.';
                });
            }
        </script>
    @endpush
@endisset
