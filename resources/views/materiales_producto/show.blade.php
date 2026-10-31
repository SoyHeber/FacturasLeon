@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="fw-bold mb-1">Receta del producto</h1>
            <p class="text-muted mb-0">{{ $producto->nombre }} · {{ $producto->estado ? 'Producto activo' : 'Producto inactivo' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('materiales_producto.ver')<a href="{{ route('materiales_producto.index') }}" class="btn btn-outline-secondary">Volver</a>@endcan
            @can('materiales_producto.modificar')<a href="{{ route('materiales_producto.edit', $materialProducto->id) }}" class="btn btn-warning">Editar receta</a>@endcan
            @can('materiales_producto.eliminar')
                <form method="POST" action="{{ route('materiales_producto.cambiar-estado', $materialProducto->id) }}" onsubmit="return confirm('¿Deseas cambiar el estado de toda la receta?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn {{ $producto->receta_activa ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $producto->receta_activa ? 'Inactivar receta' : 'Activar receta' }}</button>
                </form>
            @endcan
        </div>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <p>Estado de la receta: <span class="badge {{ $producto->receta_activa ? 'bg-success' : 'bg-secondary' }}">{{ $producto->receta_activa ? 'Activa' : 'Inactiva' }}</span></p>
            <p>Las cantidades corresponden a una unidad del producto. El estado se aplica a toda la receta.</p>
            <div class="table-responsive mb-4">
                <table class="table align-middle mb-0">
                    <thead><tr class="table-dark"><th>ID línea</th><th>Materia prima</th><th>Cantidad por unidad</th><th>Unidad</th><th>Observación</th></tr></thead>
                    <tbody>
                        @forelse ($producto->materialesProducto->where('estado', true) as $linea)
                            <tr>
                                <td>#{{ $linea->id }}</td>
                                <td>{{ $linea->inventarioCompra?->nombre ?? 'Material no disponible' }}@if ($linea->inventarioCompra && !$linea->inventarioCompra->estado)<span class="badge bg-secondary ms-1">Insumo inactivo</span>@endif</td>
                                <td>{{ $linea->getRawOriginal('cantidad_requerida') }}</td>
                                <td>{{ $linea->inventarioCompra?->unidad_medida }}</td>
                                <td>{{ $linea->observacion ?: 'Sin observación' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">La receta no tiene componentes vigentes. Agrégalos desde Editar receta antes de activarla.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($producto->materialesProducto->where('estado', false)->isNotEmpty())
                <details class="mb-4">
                    <summary>Componentes retirados de la receta</summary>
                    <ul class="mt-2 mb-0">
                        @foreach ($producto->materialesProducto->where('estado', false) as $linea)
                            <li>#{{ $linea->id }} · {{ $linea->inventarioCompra?->nombre ?? 'Material no disponible' }}: {{ $linea->getRawOriginal('cantidad_requerida') }} {{ $linea->inventarioCompra?->unidad_medida }} · {{ $linea->observacion ?: 'Sin observación' }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <p class="text-muted mb-0">Las producciones confirmadas conservan sus consumos históricos. Editar o inactivar la receta no modifica existencias.</p>
        </div>
    </div>
@endsection
