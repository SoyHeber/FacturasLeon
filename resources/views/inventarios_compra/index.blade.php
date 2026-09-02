@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Inventarios de Compra
            </h1>

            <p class="text-muted mb-0">
                Administra los materiales e insumos disponibles para producción.
            </p>
        </div>

        <a href="{{ route('inventarios_compra.create') }}" class="btn btn-warning fw-bold px-4 py-2">
            + Nuevo inventario
        </a>
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

            <form method="GET" action="{{ route('inventarios_compra.index') }}">

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead>

                            <tr class="table-dark">
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th>Cantidad</th>
                                <th>Stock mínimo</th>
                                <th>Stock máximo</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                            <tr>

                                <th>
                                    <input type="number" name="id" value="{{ request('id') }}"
                                        class="form-control form-control-sm" placeholder="ID">
                                </th>

                                <th>
                                    <input type="text" name="nombre" value="{{ request('nombre') }}"
                                        class="form-control form-control-sm" placeholder="Nombre">
                                </th>

                                <th>
                                    <input type="text" name="descripcion" value="{{ request('descripcion') }}"
                                        class="form-control form-control-sm" placeholder="Descripción">
                                </th>

                                <th>
                                    <input type="text" name="unidad_medida" value="{{ request('unidad_medida') }}"
                                        class="form-control form-control-sm" placeholder="Unidad">
                                </th>

                                <th>
                                    <input type="number" step="0.0001" name="cantidad" value="{{ request('cantidad') }}"
                                        class="form-control form-control-sm" placeholder="Cantidad">
                                </th>

                                <th>
                                    <input type="number" step="0.0001" name="stock_minimo"
                                        value="{{ request('stock_minimo') }}" class="form-control form-control-sm"
                                        placeholder="Mínimo">
                                </th>

                                <th>
                                    <input type="number" step="0.0001" name="stock_maximo"
                                        value="{{ request('stock_maximo') }}" class="form-control form-control-sm"
                                        placeholder="Máximo">
                                </th>

                                <th>
                                    <input type="text" name="ubicacion" value="{{ request('ubicacion') }}"
                                        class="form-control form-control-sm" placeholder="Ubicación">
                                </th>

                                <th>
                                    <select name="estado" class="form-select form-select-sm">

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

                                <th>
                                    <div class="d-flex gap-2">

                                        <button type="submit" class="btn btn-dark btn-sm">
                                            Filtrar
                                        </button>

                                        <a href="{{ route('inventarios_compra.index') }}"
                                            class="btn btn-outline-secondary btn-sm">
                                            Limpiar
                                        </a>

                                    </div>
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            @forelse ($inventariosCompra as $inventarioCompra)
                                <tr>

                                    <td class="fw-semibold">
                                        #{{ $inventarioCompra->id }}
                                    </td>

                                    <td class="fw-semibold">
                                        {{ $inventarioCompra->nombre }}
                                    </td>

                                    <td>
                                        {{ $inventarioCompra->descripcion ?: 'Sin descripción' }}
                                    </td>

                                    <td>
                                        <span class="badge bg-dark">
                                            {{ $inventarioCompra->unidad_medida }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ number_format($inventarioCompra->cantidad, 4) }}
                                    </td>

                                    <td>
                                        {{ number_format($inventarioCompra->stock_minimo, 4) }}
                                    </td>

                                    <td>
                                        @if ($inventarioCompra->stock_maximo !== null)
                                            {{ number_format($inventarioCompra->stock_maximo, 4) }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td>
                                        {{ $inventarioCompra->ubicacion ?: 'Sin ubicación' }}
                                    </td>

                                    <td>

                                        @if ($inventarioCompra->estado)
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

                                        <div class="d-flex gap-2 flex-wrap">

                                            <a href="{{ route('inventarios_compra.show', $inventarioCompra->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>

                                            <a href="{{ route('inventarios_compra.edit', $inventarioCompra->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                action="{{ route('inventarios_compra.cambiar-estado', $inventarioCompra->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este inventario?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($inventarioCompra->estado)
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No se encontraron registros.
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>

                <div class="mt-4">
                    {{ $inventariosCompra->links() }}
                </div>

            </form>

        </div>
    </div>
@endsection
