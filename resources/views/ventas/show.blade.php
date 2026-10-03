@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div><h1 class="module-title">Venta #{{ $venta->id }}</h1><p class="module-subtitle">{{ $venta->fecha->format('d/m/Y') }} · {{ $venta->moneda }} · {{ $venta->estado_venta }}</p></div>
        <div class="d-flex gap-2">
            @can('ventas.ver')<a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">← Volver</a>@endcan
            @if ($venta->estado_venta === 'BORRADOR')
                @can('ventas.modificar')
                    <a href="{{ route('ventas.edit', $venta) }}" class="btn btn-outline-warning rounded-3 fw-bold">Editar</a>
                    <form method="POST" action="{{ route('ventas.confirmar', $venta) }}" onsubmit="return confirm('¿Confirmar la venta y descontar los productos del inventario?')">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-warning rounded-3 fw-bold" type="submit">Confirmar Venta</button>
                    </form>
                @endcan
            @endif
            @if ($venta->estado_venta === 'CONFIRMADA' && $venta->documentoFel?->estado_fel === 'PENDIENTE')
                @can('ventas.modificar')
                    <form method="POST" action="{{ route('ventas.certificar', $venta) }}" onsubmit="this.querySelector('button').disabled = true">
                        @csrf
                        <button class="btn btn-warning rounded-3 fw-bold" type="submit">Certificar FEL</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="module-card mb-4"><div class="module-card-body">
        <h2 class="h5 fw-bold">Cliente</h2>
        <div class="row g-3">
            <div class="col-md-6"><strong>Nombre fiscal</strong><div>{{ $venta->receptor_nombre }}</div></div>
            <div class="col-md-6"><strong>Identificación</strong><div>{{ $venta->receptor_tipo_identificacion_nombre }} ({{ $venta->receptor_tipo_identificacion_codigo }}) · {{ $venta->receptor_identificacion }}</div></div>
            <div class="col-md-6"><strong>Dirección</strong><div>{{ $venta->receptor_direccion }}</div><div>{{ $venta->receptor_municipio }}, {{ $venta->receptor_departamento }}, {{ $venta->receptor_pais }}</div></div>
            <div class="col-md-3"><strong>Código postal</strong><div>{{ $venta->receptor_codigo_postal ?: 'Sin registrar' }}</div></div>
            <div class="col-md-3"><strong>Correo</strong><div>{{ $venta->receptor_correo ?: 'Sin registrar' }}</div></div>
            <div class="col-md-6"><strong>Documento / pago</strong><div>{{ $venta->tipoDocumento->codigo }} · {{ $venta->metodoPago->nombre }}</div></div>
            <div class="col-md-6"><strong>Observación</strong><div>{{ $venta->observacion ?: 'Sin observación' }}</div></div>
        </div>
    </div></div>
    <div class="module-card mb-4"><div class="module-card-body">
        <h2 class="h5 fw-bold">Productos</h2>
        <div class="table-responsive"><table class="table table-bordered align-middle">
            <thead class="table-dark"><tr><th>Línea</th><th>Código / producto</th><th>Descripción</th><th>Unidad / clase</th><th>Cantidad</th><th>Precio</th><th>Descuento %</th><th>Tributación</th><th>Bruto</th><th>Descuento</th><th>Exento</th><th>Neto</th><th>IVA</th><th>Total</th></tr></thead>
            <tbody>@foreach ($venta->detalles as $detalle)
                <tr><td>{{ $detalle->numero_linea }}</td><td>{{ $detalle->producto_codigo }} · {{ $detalle->producto_nombre }}</td><td>{{ $detalle->descripcion }}</td><td>{{ $detalle->unidad_medida }} · {{ $detalle->bien_servicio }}</td><td>{{ $detalle->cantidad }}</td><td>{{ $detalle->precio_unitario }}</td><td>{{ $detalle->porcentaje_descuento }}</td><td>{{ $detalle->tratamiento_tributario }}</td><td>{{ $detalle->importe_bruto }}</td><td>{{ $detalle->importe_descuento }}</td><td>{{ $detalle->importe_exento }}</td><td>{{ $detalle->importe_neto }}</td><td>{{ $detalle->importe_iva }}</td><td class="fw-bold">{{ $detalle->importe_total }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="row g-3 mt-2">@foreach (['bruto' => 'Bruto', 'descuento' => 'Descuento', 'exento' => 'Exento', 'neto' => 'Neto gravado', 'iva' => 'IVA', 'total' => 'Total'] as $campo => $nombre)
            <div class="col-md-2"><strong>{{ $nombre }}</strong><div>{{ $venta->{'importe_'.$campo} }}</div></div>
        @endforeach</div>
    </div></div>
    <div class="module-card"><div class="module-card-body">
        <h2 class="h5 fw-bold">Datos del documento</h2>
        <p>Referencia: <span class="text-break">{{ $venta->documentoFel?->referencia }}</span></p>
        <p>Estado FEL: {{ $venta->documentoFel?->estado_fel }}</p>
        @if ($venta->documentoFel?->estado_fel === 'CERTIFICADA')
            <div class="row g-3 mb-3">
                <div class="col-md-6"><strong>UUID / Número de autorización</strong><div class="text-break">{{ $venta->documentoFel->fel_uuid }}</div></div>
                <div class="col-md-3"><strong>Serie</strong><div>{{ $venta->documentoFel->fel_serie }}</div></div>
                <div class="col-md-3"><strong>Número</strong><div>{{ $venta->documentoFel->fel_numero }}</div></div>
                <div class="col-md-6"><strong>Fecha certificación</strong><div>{{ $venta->documentoFel->fecha_certificacion?->timezone('America/Guatemala')->format('d/m/Y H:i:s') }}</div></div>
                <div class="col-md-6"><strong>Certificador</strong><div>{{ $venta->documentoFel->nombre_certificador }} · {{ $venta->documentoFel->nit_certificador }}</div></div>
            </div>
            @can('ventas.ver')
                <div class="d-flex gap-2 mb-3">
                    <a class="btn btn-outline-primary" href="{{ route('ventas.consultar_representacion', [$venta, 'tipo' => 'pdf']) }}" target="_blank" rel="noopener noreferrer">Ver PDF</a>
                    <a class="btn btn-outline-secondary" href="{{ route('ventas.consultar_representacion', [$venta, 'tipo' => 'xml']) }}" target="_blank" rel="noopener noreferrer">Consultar XML</a>
                </div>
            @endcan
        @elseif ($venta->documentoFel?->estado_fel === 'ERROR')
            <div class="alert alert-danger">{{ $protectorFel->protegerTexto($venta->documentoFel->ultimoIntento?->mensaje_error) ?: 'Ainnova rechazó el documento.' }}</div>
        @elseif ($venta->documentoFel?->estado_fel === 'INCIERTA')
            <div class="alert alert-warning">La certificación requiere conciliación. El reintento verifica primero las respuestas guardadas y usa exactamente la misma referencia y XML; no crea otra Venta ni descuenta inventario.</div>
            @if ($venta->estado_venta === 'CONFIRMADA' && !$venta->documentoFel->intentos->contains(fn ($intento) => $intento->resultado === 'EN_PROCESO' && $intento->fecha_fin === null))
                @can('ventas.modificar')
                    @if (trim($venta->documentoFel->ultimoIntentoConRespuesta?->respuesta_raw ?? '') !== '')
                        <p class="text-muted">Esta acción reprocesa la respuesta guardada localmente. No realiza una nueva llamada a Ainnova.</p>
                        <form method="POST" action="{{ route('ventas.conciliar_respuesta', $venta) }}" class="mb-3" onsubmit="this.querySelector('button').disabled = true">
                            @csrf
                            <button class="btn btn-outline-warning rounded-3 fw-bold" type="submit">Conciliar respuesta</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('ventas.reintentar_certificacion', $venta) }}" class="mb-3"
                        onsubmit="if (!confirm('¿Reintentar FEL usando la misma referencia y el mismo XML congelado?')) return false; this.querySelector('button').disabled = true">
                        @csrf
                        <input type="hidden" name="ultimo_intento_id" value="{{ $venta->documentoFel->ultimoIntento?->id ?? 0 }}">
                        <button class="btn btn-warning" type="submit">Reintentar certificación</button>
                    </form>
                @endcan
            @endif
        @elseif ($venta->documentoFel?->estado_fel === 'EN_PROCESO')
            <div class="alert alert-info">Certificación en proceso. Si la operación se interrumpió, requiere revisión antes de otro envío.</div>
            @if ($venta->estado_venta === 'CONFIRMADA' && $venta->documentoFel->puedeRecuperarCertificacion())
                @can('ventas.modificar')
                    <form method="POST" action="{{ route('ventas.recuperar_certificacion', $venta) }}" class="mb-3" onsubmit="this.querySelector('button').disabled = true">
                        @csrf
                        <button class="btn btn-outline-warning" type="submit">Recuperar certificación</button>
                    </form>
                @endcan
            @endif
        @endif
        @if ($venta->documentoFel?->intentos->isNotEmpty())
            <h3 class="h6 mt-4">Historial de intentos FEL</h3>
            <div class="table-responsive mb-3"><table class="table table-bordered">
                <thead><tr><th>Intento</th><th>Inicio</th><th>Fin</th><th>Resultado</th><th>Mensaje</th><th>Error técnico</th><th>Usuario</th></tr></thead>
                <tbody>
                    @foreach ($venta->documentoFel->intentos->sortBy('id')->values() as $intento)
                        <tr><td>Intento {{ $loop->iteration }}</td><td>{{ $intento->fecha_inicio?->timezone('America/Guatemala')->format('d/m/Y H:i:s') }}</td><td>{{ $intento->fecha_fin?->timezone('America/Guatemala')->format('d/m/Y H:i:s') ?? 'Sin finalizar' }}</td><td>{{ $intento->resultado }}</td><td>{{ $protectorFel->protegerTexto($intento->mensaje_error) }}</td><td>{{ $protectorFel->protegerTexto($intento->error_tecnico) }}</td><td>{{ $intento->usuario?->name ?? 'Sin usuario' }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
        <p class="mb-0 text-muted">Creada por {{ $venta->usuarioCreador->name }} el {{ $venta->created_at->format('d/m/Y H:i') }}@if ($venta->usuarioModificador) · Última actualización por {{ $venta->usuarioModificador->name }} el {{ $venta->updated_at->format('d/m/Y H:i') }}@endif</p>
    </div></div>
@endsection
