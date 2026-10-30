@php
    $anulacion = $venta->anulacion;
    $ultimoIntentoAnulacion = $anulacion?->ultimoIntento?->id ?? 0;
@endphp
@if ($anulacion)
    <div class="module-card mb-4"><div class="module-card-body">
        <h2 class="h5">Historial de anulación</h2>
        <p><strong>Estado:</strong> {{ $anulacion->estado }} · {{ $anulacion->tipo === 'LOCAL' ? 'Cancelación sin emisión FEL' : 'Anulación FEL' }}</p>
        <p><strong>Motivo:</strong> {{ $protectorFel->protegerTexto($anulacion->motivo) }}</p>
        <p><strong>Solicitada por:</strong> {{ $anulacion->usuario?->name }} · <strong>Fecha solicitada:</strong> {{ $anulacion->fecha_anulacion->format('d/m/Y') }}</p>
        @if ($anulacion->estado === 'APLICADA')
            <div class="alert alert-success">{{ $anulacion->tipo === 'LOCAL' ? 'Venta cancelada sin emitir una anulación fiscal.' : 'Anulación confirmada por Ainnova y reintegro aplicado.' }}</div>
            <p>Aplicada el {{ $anulacion->fecha_aplicacion?->timezone('America/Guatemala')->format('d/m/Y H:i:s') }}.</p>
        @elseif ($anulacion->estado === 'CONFIRMADA')
            <div class="alert alert-warning">La confirmación está guardada. Falta completar la aplicación local del reintegro; no se debe volver a enviar a Ainnova.</div>
        @elseif (in_array($anulacion->estado, ['EN_PROCESO', 'INCIERTA']))
            <div class="alert alert-warning">La anulación todavía no está resuelta. El inventario se conserva y no se reenviará automáticamente la solicitud.</div>
        @endif
        @if ($anulacion->ultimo_error)<p class="text-danger">{{ $protectorFel->protegerTexto($anulacion->ultimo_error) }}</p>@endif
        @if ($anulacion->origen_confirmacion === 'MANUAL')
            <p>Conciliada con evidencia aportada por {{ $anulacion->confirmadoPor?->name }}. Referencia: {{ $protectorFel->protegerTexto($anulacion->referencia_soporte) }}.</p>
        @endif
        @if ($anulacion->confirmacion_hash)<p class="small text-muted text-break">Huella del acuse: {{ $anulacion->confirmacion_hash }}</p>@endif
        @if ($anulacion->intentos->isNotEmpty())
            <div class="table-responsive"><table class="table table-bordered">
                <thead><tr><th>Intento</th><th>Inicio</th><th>Fin</th><th>Resultado</th><th>Motivo enviado</th><th>Mensaje</th><th>Usuario</th></tr></thead>
                <tbody>@foreach ($anulacion->intentos->sortBy('id') as $intento)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $intento->fecha_inicio?->timezone('America/Guatemala')->format('d/m/Y H:i:s') }}</td><td>{{ $intento->fecha_fin?->timezone('America/Guatemala')->format('d/m/Y H:i:s') ?? 'Sin finalizar' }}</td><td>{{ $intento->resultado }}</td><td>{{ $protectorFel->protegerTexto($intento->solicitud['motivo'] ?? '') }}</td><td>{{ $protectorFel->protegerTexto($intento->mensaje) }}</td><td>{{ $intento->usuario?->name }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
        @can('ventas.eliminar')
            @if ($anulacion->estado === 'CONFIRMADA')
                <form method="POST" action="{{ route('ventas.aplicar_anulacion', $venta) }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button class="btn btn-warning" type="submit">Aplicar reintegro pendiente</button>
                </form>
            @endif
            @if ($anulacion->puedeConciliar())
                <details class="mt-3"><summary class="fw-bold">Conciliar con evidencia de Ainnova</summary>
                    <p class="mt-3">Obtenga del certificador la respuesta XML que confirma esta anulación. Se comprobarán el resultado y la identidad de la factura. La procedencia del acuse debe ser verificada por el operador responsable.</p>
                    <form method="POST" action="{{ route('ventas.conciliar_anulacion', $venta) }}" onsubmit="if (!confirm('¿Confirma que el acuse procede de Ainnova y autoriza el reintegro?')) return false; this.querySelector('button').disabled = true">
                        @csrf
                        <input type="hidden" name="ultimo_intento_anulacion_id" value="{{ $ultimoIntentoAnulacion }}">
                        <label for="referencia_soporte" class="form-label">Referencia de soporte o comprobante</label>
                        <input id="referencia_soporte" name="referencia_soporte" class="form-control mb-3" minlength="5" maxlength="200" value="{{ old('referencia_soporte') }}" required>
                        <label for="respuesta_ainnova" class="form-label">Respuesta XML de Ainnova</label>
                        <textarea id="respuesta_ainnova" name="respuesta_ainnova" class="form-control mb-3" rows="6" maxlength="1048576" required></textarea>
                        <div class="form-check mb-3"><input id="confirmacion_ainnova" type="checkbox" name="confirmacion_ainnova" value="1" class="form-check-input" required><label for="confirmacion_ainnova" class="form-check-label">Verifiqué que la evidencia fue proporcionada por Ainnova para esta factura.</label></div>
                        <button class="btn btn-warning" type="submit">Conciliar y aplicar reintegro</button>
                    </form>
                </details>
            @endif
        @endcan
    </div></div>
@endif
@can('ventas.eliminar')
    @if (in_array($venta->estado_venta, ['BORRADOR', 'CONFIRMADA']) && in_array($venta->documentoFel?->estado_fel, ['PENDIENTE', 'ERROR', 'CERTIFICADA']) && (!$anulacion || $anulacion->estado === 'RECHAZADA'))
        <details class="module-card mb-4"><summary class="module-card-body fw-bold">{{ $venta->documentoFel?->estado_fel === 'CERTIFICADA' ? 'Anular factura y venta' : 'Cancelar venta sin emisión' }}</summary>
            <div class="module-card-body pt-0">
                <details class="mb-3">
                    <summary class="fw-bold">OBSERVACIONES:</summary>
                    <ul class="mt-2 mb-0">
                        <li>La anulación es total.</li>
                        <li>Las cantidades se toman de las salidas originales y se reintegran una sola vez.</li>
                        <li>Un borrador sin movimientos no modifica inventario.</li>
                        <li>La fecha de anulación debe ser igual o posterior a la fecha del documento, dentro del mismo mes y año, y no puede ser futura.</li>
                        @if ($venta->documentoFel?->estado_fel === 'CERTIFICADA')
                            <li>Se solicitará la anulación a Ainnova.</li>
                            <li>El reintegro solo se aplicará cuando la respuesta confirme esta factura.</li>
                        @endif
                    </ul>
                </details>
                @php
                    $fechaMaximaAnulacion = min(now('America/Guatemala')->toDateString(), $venta->fecha->copy()->endOfMonth()->toDateString());
                @endphp
                <form method="POST" action="{{ route('ventas.anular', $venta) }}" onsubmit="if (!confirm('¿Desea continuar con la cancelación o solicitud de anulación total?')) return false; this.querySelector('button').disabled = true">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="ultimo_intento_anulacion_id" value="{{ $ultimoIntentoAnulacion }}">
                    <label for="fecha_anulacion" class="form-label">Fecha de anulación</label>
                    <input id="fecha_anulacion" name="fecha_anulacion" type="date" class="form-control mb-3" min="{{ $venta->fecha->format('Y-m-d') }}" max="{{ $fechaMaximaAnulacion }}" value="{{ old('fecha_anulacion', $fechaMaximaAnulacion) }}" required>
                    <label for="motivo_anulacion" class="form-label">Motivo</label>
                    <textarea id="motivo_anulacion" name="motivo" class="form-control mb-3" maxlength="200" required>{{ old('motivo', $anulacion?->motivo) }}</textarea>
                    <button class="btn btn-outline-danger" type="submit">{{ $venta->documentoFel?->estado_fel === 'CERTIFICADA' ? 'Solicitar anulación FEL' : 'Cancelar venta' }}</button>
                </form>
            </div>
        </details>
    @endif
@endcan
