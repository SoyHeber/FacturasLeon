@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Inventarios
            </h1>

            <p class="text-muted mb-0">
                Controla las existencias, niveles de stock y ubicación de las joyas en la joyería.
            </p>
        </div>

        @can('inventarios.crear')
            <a href="{{ route('inventarios.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo inventario
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


    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            {{-- Formulario de filtros (los inputs se asocian con form="filtros") --}}
            <form id="filtros" method="GET" action="{{ route('inventarios.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Producto</th>

                            <th>Código</th>

                            <th>Cantidad</th>

                            <th>Stock mínimo</th>

                            <th>Stock máximo</th>

                            <th>Ubicación</th>

                            <th>Estado stock</th>

                            <th>Estado</th>

                            <th>Acciones</th>

                        </tr>


                        {{-- Filtros --}}
                        <tr>

                            <th>
                                <input type="number" name="id" value="{{ request('id') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input type="text" name="producto" value="{{ request('producto') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar producto">
                            </th>

                            <th>
                                <input type="text" name="codigo" value="{{ request('codigo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar código">
                            </th>

                            <th>
                                <input type="number" name="cantidad" value="{{ request('cantidad') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Cantidad">
                            </th>

                            <th>
                                <input type="number" name="stock_minimo" value="{{ request('stock_minimo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Mínimo">
                            </th>

                            <th>
                                <input type="number" name="stock_maximo" value="{{ request('stock_maximo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Máximo">
                            </th>

                            <th>
                                <input type="text" name="ubicacion" value="{{ request('ubicacion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar ubicación">
                            </th>

                            <th>
                                <select name="estado_stock" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="bajo" {{ request('estado_stock') === 'bajo' ? 'selected' : '' }}>
                                        Stock bajo
                                    </option>

                                    <option value="sobre" {{ request('estado_stock') === 'sobre' ? 'selected' : '' }}>
                                        Sobre stock
                                    </option>

                                    <option value="normal" {{ request('estado_stock') === 'normal' ? 'selected' : '' }}>
                                        Stock normal
                                    </option>

                                </select>
                            </th>

                            <th>
                                <select name="estado" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>
                                        Activo
                                    </option>

                                    <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>
                                        Inactivo
                                    </option>

                                </select>
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('inventarios.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($inventarios as $inventario)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $inventario->id }}
                                </td>

                                <td class="fw-bold">
                                    {{ $inventario->producto->nombre ?? 'Sin producto' }}
                                </td>

                                <td>
                                    {{ $inventario->producto->codigo ?? 'N/A' }}
                                </td>

                                <td>
                                    {{ $inventario->cantidad }}
                                </td>

                                <td>
                                    {{ $inventario->stock_minimo }}
                                </td>

                                <td>
                                    {{ $inventario->stock_maximo ?? 'Sin límite' }}
                                </td>

                                <td>
                                    {{ $inventario->ubicacion ?: 'Sin ubicación' }}
                                </td>

                                <td>

                                    @if ($inventario->cantidad < $inventario->stock_minimo)
                                        <span class="badge bg-danger">
                                            Stock bajo
                                        </span>
                                    @elseif ($inventario->stock_maximo !== null && $inventario->cantidad > $inventario->stock_maximo)
                                        <span class="badge bg-warning text-dark">
                                            Sobre stock
                                        </span>
                                    @else
                                        <span class="badge bg-success">
                                            Stock normal
                                        </span>
                                    @endif

                                </td>

                                <td>

                                    @if ($inventario->estado)
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Inactivo
                                        </span>
                                    @endif

                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('inventarios.ver')
                                            <a href="{{ route('inventarios.show', $inventario->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('inventarios.modificar')
                                            <a href="{{ route('inventarios.edit', $inventario->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('inventarios.eliminar')
                                            <form method="POST"
                                                action="{{ route('inventarios.cambiar-estado', $inventario->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este inventario?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($inventario->estado)
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif

                                            </form>
                                        @endcan

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="10" class="text-center text-muted py-4">

                                    No se encontraron inventarios.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $inventarios->links() }}

            </div>

        </div>

    </div>
@endsection
