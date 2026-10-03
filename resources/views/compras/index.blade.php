@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Compras
            </h1>

            <p class="text-muted mb-0">
                Administra los documentos de compra registrados en el sistema.
            </p>
        </div>

        @can('compras.crear')
            <a href="{{ route('compras.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nueva compra
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert">
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            <form id="filtros" method="GET" action="{{ route('compras.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr class="table-dark">
                            <th>ID</th>
                            <th>Proveedor</th>
                            <th>Tipo DTE</th>
                            <th>Serie</th>
                            <th>Número</th>
                            <th>Autorización / UUID</th>
                            <th>Fecha emisión</th>
                            <th>Moneda</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                        <tr>

                            <th>
                                <input form="filtros" type="number" name="id" value="{{ request('id') }}"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="proveedor" value="{{ request('proveedor') }}"
                                    class="form-control form-control-sm" placeholder="Proveedor">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="tipo_documento" value="{{ request('tipo_documento') }}"
                                    class="form-control form-control-sm" placeholder="Tipo DTE">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="serie" value="{{ request('serie') }}"
                                    class="form-control form-control-sm" placeholder="Serie">
                            </th>

                            <th>
                                <input form="filtros" type="number" name="numero" value="{{ request('numero') }}"
                                    class="form-control form-control-sm" placeholder="Número">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="numero_autorizacion"
                                    value="{{ request('numero_autorizacion') }}" class="form-control form-control-sm"
                                    placeholder="UUID">
                            </th>

                            <th>
                                <input form="filtros" type="date" name="fecha_emision" value="{{ request('fecha_emision') }}"
                                    class="form-control form-control-sm">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="moneda" value="{{ request('moneda') }}"
                                    class="form-control form-control-sm" placeholder="GTQ">
                            </th>

                            <th>
                                <input form="filtros" type="number" step="0.01" name="importe_total"
                                    value="{{ request('importe_total') }}" class="form-control form-control-sm"
                                    placeholder="Total">
                            </th>

                            <th>
                                <select form="filtros" name="estado" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>
                                        Activo
                                    </option>

                                    <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>
                                        Anulada
                                    </option>

                                </select>
                            </th>

                            <th>
                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($compras as $compra)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $compra->id }}
                                </td>

                                <td>
                                    {{ $compra->proveedor->nombre ?? 'Sin proveedor' }}
                                </td>

                                <td>
                                    <span class="badge bg-dark">
                                        {{ $compra->tipoDocumento->codigo }} - {{ $compra->tipoDocumento->nombre }}
                                    </span>
                                </td>

                                <td>
                                    {{ $compra->serie }}
                                </td>

                                <td>
                                    {{ $compra->numero }}
                                </td>

                                <td>
                                    <small>
                                        {{ $compra->numero_autorizacion }}
                                    </small>
                                </td>

                                <td>
                                    {{ $compra->fecha_emision?->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    {{ $compra->moneda }}
                                </td>

                                <td class="fw-bold">
                                    Q {{ number_format($compra->importe_total, 2) }}
                                </td>

                                <td>
                                    @if ($compra->estado)
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Anulada
                                        </span>
                                    @endif

                                    @if (!$compra->estado && $compra->fecha_anulacion)
                                        <small class="text-muted d-block mt-1">
                                            {{ $compra->fecha_anulacion->format('d/m/Y H:i') }}
                                            · {{ $compra->anuladoPor?->name ?? 'Sin usuario' }}
                                        </small>
                                    @endif

                                    @if (!$compra->inventario_aplicado)
                                        <small class="text-muted d-block mt-1">
                                            Histórica: sin inventario automático.
                                        </small>
                                    @endif
                                </td>

                                <td>

                                    <div class="d-flex gap-2 flex-wrap">

                                        @can('compras.ver')
                                            <a href="{{ route('compras.show', $compra->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @if ($compra->estado)
                                            @can('compras.eliminar')
                                                <form method="POST"
                                                    action="{{ route('compras.cambiar-estado', $compra->id) }}"
                                                    onsubmit="return confirm('¿Deseas anular definitivamente esta compra? Esta acción no puede revertirse.')">

                                                    @csrf
                                                    @method('PATCH')

                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Anular
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    No se encontraron compras.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-4">
                {{ $compras->links() }}
            </div>

        </div>

    </div>
@endsection
