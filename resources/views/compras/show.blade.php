@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle de Compra
        </h1>

        <p class="text-muted mb-0">
            Consulta la información completa del DTE registrado.
        </p>
    </div>


    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ====================================================== --}}
    {{-- ENCABEZADO --}}
    {{-- ====================================================== --}}

    <div class="card shadow-sm border-0 rounded-4 mb-4">

        <div class="card-body p-4">

            <h5 class="fw-bold mb-4">
                Encabezado
            </h5>

            <div class="row">

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $compra->id }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Proveedor
                    </span>

                    <strong>
                        {{ $compra->proveedor->nombre ?? 'Sin proveedor' }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Identificación
                    </span>

                    <strong>
                        {{ $compra->proveedor->numero_identificacion ?? 'No registrada' }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Usuario que registró
                    </span>

                    <strong>
                        {{ $compra->user->name ?? 'Sin usuario' }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Tipo DTE
                    </span>

                    <span class="badge bg-dark">
                        {{ $compra->tipoDocumento->codigo }} - {{ $compra->tipoDocumento->nombre }}
                    </span>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Serie
                    </span>

                    <strong>
                        {{ $compra->serie }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Número
                    </span>

                    <strong>
                        {{ $compra->numero }}
                    </strong>
                </div>

                <div class="col-md-3 mb-4">
                    <span class="text-muted d-block">
                        Moneda
                    </span>

                    <strong>
                        {{ $compra->moneda }}
                    </strong>
                </div>

                <div class="col-md-12 mb-4">
                    <span class="text-muted d-block">
                        Número de Autorización / UUID
                    </span>

                    <strong>
                        {{ $compra->numero_autorizacion }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Fecha de Emisión
                    </span>

                    <strong>
                        {{ $compra->fecha_emision?->format('d/m/Y H:i') }}
                    </strong>
                </div>

                <div class="col-md-6 mb-4">
                    <span class="text-muted d-block">
                        Fecha de Certificación
                    </span>

                    <strong>
                        {{ $compra->fecha_certificacion?->format('d/m/Y H:i') ?? 'No registrada' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Teléfono proveedor
                    </span>

                    <strong>
                        {{ $compra->proveedor->telefono ?? 'No registrado' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Correo proveedor
                    </span>

                    <strong>
                        {{ $compra->proveedor->correo ?? 'No registrado' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Dirección proveedor
                    </span>

                    <strong>
                        {{ $compra->proveedor->direccion->direccion ?? 'No registrada' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Otros
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_otros, 2) }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Neto
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_neto, 2) }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe IVA
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_iva, 2) }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Total
                    </span>

                    <strong class="fs-5">
                        Q {{ number_format($compra->importe_total, 2) }}
                    </strong>
                </div>

            </div>

            <div class="mb-4">
                <span class="text-muted d-block">
                    Observación
                </span>

                <p class="mb-0">
                    {{ $compra->observacion ?: 'Sin observación' }}
                </p>
            </div>

            <div class="mb-4">
                <span class="text-muted d-block">
                    Estado
                </span>

                @if ($compra->estado)
                    <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                        Activo
                    </span>
                @else
                    <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                        Anulada
                    </span>
                @endif

                @if (!$compra->inventario_aplicado)
                    <small class="text-muted d-block mt-2">
                        Compra histórica: sin inventario aplicado automáticamente. Requiere conciliación antes de anularse.
                    </small>
                @endif
            </div>

            @if (!$compra->estado && $compra->fecha_anulacion)
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <span class="text-muted d-block">Fecha de anulación</span>
                        <strong>{{ $compra->fecha_anulacion->format('d/m/Y H:i') }}</strong>
                    </div>
                    <div class="col-md-6 mb-4">
                        <span class="text-muted d-block">Usuario que anuló</span>
                        <strong>{{ $compra->anuladoPor?->name ?? 'Sin usuario' }}</strong>
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2">

                <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @if ($compra->estado)
                    @can('compras.eliminar')
                        <form method="POST" action="{{ route('compras.cambiar-estado', $compra->id) }}"
                            onsubmit="return confirm('¿Deseas anular definitivamente esta compra? Esta acción no puede revertirse.')">

                            @csrf
                            @method('PATCH')

                            <button type="submit" class="btn btn-outline-danger">
                                Anular
                            </button>
                        </form>
                    @endcan
                @endif

            </div>

        </div>

    </div>


    {{-- ====================================================== --}}
    {{-- DETALLE --}}
    {{-- ====================================================== --}}

    <div class="card shadow-sm border-0 rounded-4 mb-4">

        <div class="card-body p-4">

            <div class="mb-4">
                <h5 class="fw-bold mb-1">
                    Detalle
                </h5>

                <p class="text-muted mb-0">
                    Materiales o insumos incluidos en la compra.
                </p>
            </div>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead class="table-dark">

                        <tr>
                            <th>Línea</th>
                            <th>Material</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>% Desc.</th>
                            <th>Bruto</th>
                            <th>Descuento</th>
                            <th>Exento</th>
                            <th>Otros</th>
                            <th>Neto</th>
                            <th>IVA</th>
                            <th>Total</th>
                            <th>Observación</th>
                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($compra->detalles->sortBy('numero_linea') as $detalle)
                            <tr>

                                <td class="fw-semibold">
                                    {{ $detalle->numero_linea }}
                                </td>

                                <td>
                                    {{ $detalle->inventarioCompra->nombre ?? 'Sin material' }}
                                </td>

                                <td>
                                    <span class="badge bg-dark">
                                        {{ $detalle->inventarioCompra->unidad_medida ?? '' }}
                                    </span>
                                </td>

                                <td>
                                    {{ number_format($detalle->cantidad, 4) }}
                                </td>

                                <td>
                                    {{ $compra->moneda }}
                                    {{ number_format($detalle->precio_unitario, 6) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->porcentaje_descuento, 4) }}%
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_bruto, 2) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_descuento, 2) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_exento, 2) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_otros, 2) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_neto, 2) }}
                                </td>

                                <td>
                                    {{ number_format($detalle->importe_iva, 2) }}
                                </td>

                                <td class="fw-bold">
                                    {{ $compra->moneda }}
                                    {{ number_format($detalle->importe_total, 2) }}
                                </td>

                                <td>
                                    {{ $detalle->observacion ?: '-' }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="14" class="text-center text-muted py-4">

                                    Esta compra no tiene detalles registrados.

                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    {{-- ====================================================== --}}
    {{-- TOTALES --}}
    {{-- ====================================================== --}}

    <div class="card shadow-sm border-0 rounded-4 mb-4">

        <div class="card-body p-4">

            <div class="row">

                <div class="col-lg-7">

                    <h5 class="fw-bold mb-3">
                        Información adicional
                    </h5>

                    <div class="mb-4">

                        <span class="text-muted d-block">
                            Observación
                        </span>

                        <p class="mb-0">
                            {{ $compra->observacion ?: 'Sin observación' }}
                        </p>

                    </div>

                    <div class="mb-4">

                        <span class="text-muted d-block">
                            Estado
                        </span>

                        @if ($compra->estado)
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

                <div class="col-lg-5">

                    <div class="border rounded-3 p-4 bg-light">

                        <h5 class="fw-bold mb-4">
                            Totales
                        </h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Importe Bruto
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_bruto, 2) }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Descuento
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_descuento, 2) }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Exento
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_exento, 2) }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Otros
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_otros, 2) }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Neto
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_neto, 2) }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>
                                IVA
                            </span>

                            <strong>
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_iva, 2) }}
                            </strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">

                            <span class="fw-bold fs-5">
                                GRAN TOTAL
                            </span>

                            <strong class="fs-4">
                                {{ $compra->moneda }}
                                {{ number_format($compra->importe_total, 2) }}
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ====================================================== --}}
    {{-- ACCIONES --}}
    {{-- ====================================================== --}}

    <div class="d-flex gap-2">

        <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">
            Volver
        </a>

        @if ($compra->estado)
            @can('compras.eliminar')
                <form method="POST" action="{{ route('compras.cambiar-estado', $compra->id) }}"
                    onsubmit="return confirm('¿Deseas anular definitivamente esta compra? Esta acción no puede revertirse.')">

                    @csrf
                    @method('PATCH')

                    <button type="submit" class="btn btn-outline-danger">
                        Anular
                    </button>
                </form>
            @endcan
        @endif

    </div>
@endsection
