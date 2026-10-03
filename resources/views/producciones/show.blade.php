@extends('layouts.app-bootstrap')

@section('content')
    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Detalle de Producción
        </h1>

        <p class="text-muted mb-0">
            Consulta la información del registro de producción.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <div class="row">

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        ID
                    </span>

                    <strong>
                        #{{ $produccion->id }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Producto
                    </span>

                    <strong>
                        {{ $produccion->producto->nombre ?? 'Sin producto' }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Cantidad producida
                    </span>

                    <strong class="fs-5">
                        {{ $produccion->cantidad }}
                    </strong>

                </div>

                <div class="col-md-3 mb-4">

                    <span class="text-muted d-block">
                        Estado
                    </span>

                    <span class="badge bg-secondary">{{ $produccion->estado_produccion }}</span>


                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Fecha de producción
                    </span>

                    <strong>
                        {{ optional($produccion->fecha_produccion)->format('d/m/Y H:i') }}
                    </strong>

                </div>

                <div class="col-md-6 mb-4">

                    <span class="text-muted d-block">
                        Registrado por
                    </span>

                    <strong>
                        {{ $produccion->usuario->name ?? 'Sin usuario' }}
                    </strong>

                    @if ($produccion->usuario?->email)
                        <div class="text-muted small">
                            {{ $produccion->usuario->email }}
                        </div>
                    @endif

                </div>

            </div>

            <hr>

            <div class="mb-4">

                <span class="text-muted d-block">
                    Observación
                </span>

                <p class="mb-0">
                    {{ $produccion->observacion ?: 'Sin observación' }}
                </p>

            </div>

            @if ($produccion->estado_produccion === 'LEGADA')
                <div class="alert alert-warning">Producción histórica / no conciliada. Solo consulta; no admite automatización ni conciliación de inventario desde este flujo.</div>
            @endif

            @if ($produccion->fecha_confirmacion)
                <p><strong>Confirmada:</strong> {{ $produccion->fecha_confirmacion->format('d/m/Y H:i') }} por {{ $produccion->confirmadoPor?->name ?? 'Usuario no disponible' }}.</p>
            @endif
            @if ($produccion->fecha_anulacion)
                <p><strong>Anulada:</strong> {{ $produccion->fecha_anulacion->format('d/m/Y H:i') }} por {{ $produccion->anuladoPor?->name ?? 'Usuario no disponible' }}.</p>
            @endif

            @if ($produccion->estado_produccion === 'BORRADOR')
                @include('producciones._estimacion')
            @endif
            @include('producciones._historial')

            <div class="d-flex gap-2">

                <a href="{{ route('producciones.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>

                @include('producciones._confirmar')
                @include('producciones._anular')

                @if ($produccion->esEditable())
                    @can('producciones.modificar')
                        <a href="{{ route('producciones.edit', $produccion->id) }}" class="btn btn-warning">
                            Editar
                        </a>
                    @endcan

                @endif

            </div>

        </div>
    </div>
@endsection
