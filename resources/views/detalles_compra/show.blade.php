@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle de Compra
        </h1>

        <p class="text-muted mb-0">
            Consulta la información completa de esta línea de compra.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $detalleCompra->id }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Compra
                    </span>

                    <strong>
                        #{{ $detalleCompra->compra_id }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        DTE
                    </span>

                    <strong>
                        {{ $detalleCompra->compra->tipoDocumento->codigo }}
                        {{ $detalleCompra->compra->serie ?? '' }}
                        -
                        {{ $detalleCompra->compra->numero ?? '' }}
                    </strong>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Material / Insumo
                    </span>

                    <strong>
                        {{ $detalleCompra->inventarioCompra->nombre ?? 'Sin material' }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Cantidad
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->cantidad, 4) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Unidad
                    </span>

                    <span class="badge bg-dark">
                        {{ $detalleCompra->inventarioCompra->unidad_medida ?? '' }}
                    </span>

                </div>

            </div>

            <hr>

            <h5 class="fw-bold mb-4">
                Valores de la Línea
            </h5>

            <div class="row">

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Precio Unitario
                    </span>

                    <strong>
                        {{ $detalleCompra->compra->moneda ?? 'GTQ' }}
                        {{ number_format($detalleCompra->precio_unitario, 6) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Descuento
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->porcentaje_descuento, 4) }}%
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe Bruto
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_bruto, 2) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe Descuento
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_descuento, 2) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe Exento
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_exento, 2) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe Otros
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_otros, 2) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe Neto
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_neto, 2) }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Importe IVA
                    </span>

                    <strong>
                        {{ number_format($detalleCompra->importe_iva, 2) }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Importe Total
                    </span>

                    <strong class="fs-5">
                        {{ $detalleCompra->compra->moneda ?? 'GTQ' }}
                        {{ number_format($detalleCompra->importe_total, 2) }}
                    </strong>

                </div>

                <div class="col-md-4 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    @if ($detalleCompra->estado)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                            Activo
                        </span>
                    @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                            Inactivo
                        </span>
                    @endif

                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted d-block">
                    Observación
                </span>

                <p class="mb-0">
                    {{ $detalleCompra->observacion ?: 'Sin observación' }}
                </p>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('detalles_compra.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>



            </div>

        </div>
    </div>
@endsection
