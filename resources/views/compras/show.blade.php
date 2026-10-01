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

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $compra->id }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Proveedor
                    </span>

                    <strong>
                        {{ $compra->proveedor->nombre ?? 'Sin proveedor' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Usuario que registró
                    </span>

                    <strong>
                        {{ $compra->user->name ?? 'Sin usuario' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Tipo DTE
                    </span>

                    <span class="badge bg-dark">
                        {{ $compra->tipo_dte }}
                    </span>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Serie
                    </span>

                    <strong>
                        {{ $compra->serie }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Número
                    </span>

                    <strong>
                        {{ $compra->numero }}
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

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Fecha de Emisión
                    </span>

                    <strong>
                        {{ $compra->fecha_emision?->format('d/m/Y H:i') }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Fecha de Certificación
                    </span>

                    <strong>
                        {{ $compra->fecha_certificacion?->format('d/m/Y H:i') ?? 'No registrada' }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Moneda
                    </span>

                    <strong>
                        {{ $compra->moneda }}
                    </strong>
                </div>

            </div>

            <hr>

            <h5 class="fw-bold mb-4">
                Importes
            </h5>

            <div class="row">

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Bruto
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_bruto, 2) }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Descuento
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_descuento, 2) }}
                    </strong>
                </div>

                <div class="col-md-4 mb-4">
                    <span class="text-muted d-block">
                        Importe Exento
                    </span>

                    <strong>
                        Q {{ number_format($compra->importe_exento, 2) }}
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
                        Inactivo
                    </span>
                @endif
            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @can('compras.modificar')
                    <a href="{{ route('compras.edit', $compra->id) }}" class="btn btn-warning">
                        Editar
                    </a>
                @endcan

                @can('compras.eliminar')
                    <form method="POST" action="{{ route('compras.cambiar-estado', $compra->id) }}"
                        onsubmit="return confirm('¿Deseas cambiar el estado de esta compra?')">

                        @csrf
                        @method('PATCH')

                        @if ($compra->estado)
                            <button type="submit" class="btn btn-outline-secondary">
                                Inactivar
                            </button>
                        @else
                            <button type="submit" class="btn btn-outline-success">
                                Activar
                            </button>
                        @endif

                    </form>
                @endcan

            </div>

        </div>

    </div>
@endsection
