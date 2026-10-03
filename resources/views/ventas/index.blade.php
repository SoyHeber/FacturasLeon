@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div><h1 class="fw-bold mb-1">Ventas</h1><p class="text-muted mb-0">Administra las ventas y sus productos.</p></div>
        @can('ventas.crear')
            <a href="{{ route('ventas.create') }}" class="btn btn-warning fw-bold px-4 py-2">+ Nueva venta</a>
        @endcan
    </div>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
        <form id="filtros" method="GET" action="{{ route('ventas.index') }}"></form>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead>
                <tr class="table-dark"><th>ID</th><th>Fecha</th><th>Cliente</th><th>Identificación</th><th>Documento</th><th>Método de pago</th><th>Moneda</th><th>Total</th><th>Estado</th><th>Referencia</th><th>Observación</th><th>Creador</th><th>Fecha creación</th><th>Acciones</th></tr>
                <tr>
                    <th><input form="filtros" type="number" name="id" value="{{ request('id') }}" class="form-control form-control-sm" aria-label="Filtrar ID"></th>
                    <th><input form="filtros" type="date" name="fecha" value="{{ request('fecha') }}" class="form-control form-control-sm" aria-label="Filtrar fecha"></th>
                    <th><input form="filtros" type="text" name="receptor_nombre" value="{{ request('receptor_nombre') }}" class="form-control form-control-sm" aria-label="Filtrar cliente"></th>
                    <th><input form="filtros" type="text" name="receptor_identificacion" value="{{ request('receptor_identificacion') }}" class="form-control form-control-sm" aria-label="Filtrar identificación"></th>
                    <th><input form="filtros" type="text" name="tipo_documento" value="{{ request('tipo_documento') }}" class="form-control form-control-sm" aria-label="Filtrar documento"></th>
                    <th><input form="filtros" type="text" name="metodo_pago" value="{{ request('metodo_pago') }}" class="form-control form-control-sm" aria-label="Filtrar método de pago"></th>
                    <th><select form="filtros" name="moneda" class="form-select form-select-sm" aria-label="Filtrar moneda"><option value="">Todas</option><option value="GTQ" @selected(request('moneda') === 'GTQ')>GTQ</option></select></th>
                    <th><input form="filtros" type="text" name="importe_total" value="{{ request('importe_total') }}" class="form-control form-control-sm" aria-label="Filtrar total"></th>
                    <th><select form="filtros" name="estado_venta" class="form-select form-select-sm" aria-label="Filtrar estado"><option value="">Todos</option>@foreach (\App\Models\Venta::ESTADOS as $estado)<option value="{{ $estado }}" @selected(request('estado_venta') === $estado)>{{ $estado }}</option>@endforeach</select></th>
                    <th><input form="filtros" type="text" name="referencia" value="{{ request('referencia') }}" class="form-control form-control-sm" aria-label="Filtrar referencia"></th>
                    <th><input form="filtros" type="text" name="observacion" value="{{ request('observacion') }}" class="form-control form-control-sm" aria-label="Filtrar observación"></th>
                    <th><input form="filtros" type="text" name="creador" value="{{ request('creador') }}" class="form-control form-control-sm" aria-label="Filtrar creador"></th>
                    <th><input form="filtros" type="date" name="fecha_creacion" value="{{ request('fecha_creacion') }}" class="form-control form-control-sm" aria-label="Filtrar fecha de creación"></th>
                    <th><div class="d-flex gap-2"><button type="submit" form="filtros" class="btn btn-dark btn-sm">Filtrar</button><a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a></div></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ventas as $venta)
                    <tr>
                        <td class="fw-semibold">#{{ $venta->id }}</td><td>{{ $venta->fecha->format('d/m/Y') }}</td><td class="fw-bold">{{ $venta->receptor_nombre }}</td><td>{{ $venta->receptor_identificacion }}</td>
                        <td>{{ $venta->tipoDocumento->codigo }}</td><td>{{ $venta->metodoPago->nombre }}</td><td>{{ $venta->moneda }}</td><td class="text-end">{{ $venta->importe_total }}</td>
                        <td><span class="badge rounded-pill {{ $venta->estado_venta === 'BORRADOR' ? 'bg-secondary' : ($venta->estado_venta === 'CONFIRMADA' ? 'bg-success' : 'bg-danger') }}">{{ $venta->estado_venta }}</span></td>
                        <td>{{ $venta->documentoFel?->referencia }}</td><td>{{ \Illuminate\Support\Str::limit($venta->observacion, 60) }}</td><td>{{ $venta->usuarioCreador->name }}</td><td>{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                        <td><div class="d-flex gap-2">
                            @can('ventas.ver')<a href="{{ route('ventas.show', $venta) }}" class="btn btn-outline-info btn-sm">Ver</a>@endcan
                            @if ($venta->estado_venta === 'BORRADOR')
                                @can('ventas.modificar')
                                    <a href="{{ route('ventas.edit', $venta) }}" class="btn btn-outline-warning btn-sm">Editar</a>
                                    <form method="POST" action="{{ route('ventas.confirmar', $venta) }}" onsubmit="return confirm('¿Confirmar la venta y descontar los productos del inventario?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-warning btn-sm text-nowrap" type="submit">Confirmar Venta</button>
                                    </form>
                                @endcan
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-muted py-4">No se encontraron ventas.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="mt-4">{{ $ventas->links() }}</div>
    </div></div>
@endsection
