@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">Recetas por producto</h1>
            <p class="text-muted mb-0">Consulta y administra la receta completa de cada producto.</p>
        </div>
        @can('materiales_producto.crear')
            <a href="{{ route('materiales_producto.create') }}" class="btn btn-warning fw-bold px-4 py-2">+ Agregar materiales</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <form id="filtros" method="GET" action="{{ route('materiales_producto.index') }}"></form>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="table-dark">
                            <th>ID material</th>
                            <th>Producto</th>
                            <th>Materias primas</th>
                            <th>Observación del material</th>
                            <th>Estado de la receta</th>
                            <th>Acciones</th>
                        </tr>
                        <tr>
                            <th><input type="number" name="id" value="{{ request('id') }}" form="filtros" class="form-control form-control-sm" placeholder="ID" aria-label="Filtrar ID material" min="1"></th>
                            <th><input type="text" name="producto" value="{{ request('producto') }}" form="filtros" class="form-control form-control-sm" placeholder="Filtrar producto" aria-label="Filtrar producto"></th>
                            <th><input type="text" name="material" value="{{ request('material') }}" form="filtros" class="form-control form-control-sm" placeholder="Filtrar materia prima" aria-label="Filtrar materia prima"></th>
                            <th><input type="text" name="observacion" value="{{ request('observacion') }}" form="filtros" class="form-control form-control-sm" placeholder="Filtrar observación" aria-label="Filtrar observación del material"></th>
                            <th>
                                <select name="estado" form="filtros" class="form-select form-select-sm" aria-label="Filtrar estado de la receta">
                                    <option value="">Todos</option>
                                    <option value="1" @selected(request('estado') === '1')>Activa</option>
                                    <option value="0" @selected(request('estado') === '0')>Inactiva</option>
                                </select>
                            </th>
                            <th class="text-nowrap">
                                <button type="submit" form="filtros" class="btn btn-dark btn-sm">Filtrar</button>
                                <a href="{{ route('materiales_producto.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recetas as $producto)
                            @php
                                $ancla = $producto->materialesProducto->first();
                                $componentes = $producto->materialesProducto->where('estado', true)->values();
                                $filas = $componentes->isEmpty() ? collect([null]) : $componentes;
                            @endphp
                            @foreach ($filas as $linea)
                            <tr>
                                <td>{{ $linea ? '#'.$linea->id : '—' }}</td>
                                @if ($loop->first)
                                <td rowspan="{{ $filas->count() }}" class="fw-semibold">
                                    {{ $producto->nombre }}
                                    @if (!$producto->estado)<span class="badge bg-secondary ms-1">Producto inactivo</span>@endif
                                </td>
                                @endif
                                <td>{{ $linea ? ($linea->inventarioCompra?->nombre ?? 'Material no disponible') : 'Sin componentes vigentes' }}</td>
                                <td>{{ $linea ? ($linea->observacion ?: 'Sin observación') : '—' }}</td>
                                @if ($loop->first)
                                <td rowspan="{{ $filas->count() }}"><span class="badge {{ $producto->receta_activa ? 'bg-success' : 'bg-secondary' }}">{{ $producto->receta_activa ? 'Activa' : 'Inactiva' }}</span></td>
                                <td rowspan="{{ $filas->count() }}">
                                    <div class="d-flex gap-2">
                                        @can('materiales_producto.ver')<a href="{{ route('materiales_producto.show', $ancla->id) }}" class="btn btn-outline-info btn-sm text-nowrap">Ver receta</a>@endcan
                                        @can('materiales_producto.modificar')<a href="{{ route('materiales_producto.edit', $ancla->id) }}" class="btn btn-outline-warning btn-sm text-nowrap">Editar receta</a>@endcan
                                        @can('materiales_producto.eliminar')
                                            <form method="POST" action="{{ route('materiales_producto.cambiar-estado', $ancla->id) }}" onsubmit="return confirm('¿Deseas cambiar el estado de toda la receta?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm text-nowrap {{ $producto->receta_activa ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $producto->receta_activa ? 'Inactivar receta' : 'Activar receta' }}</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron recetas de productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $recetas->links() }}</div>
        </div>
    </div>
@endsection
