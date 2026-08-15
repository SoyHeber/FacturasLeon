@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Inventarios</h1>

        <a href="{{ route('inventarios.create') }}" class="btn btn-primary">
            Nuevo inventario
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert">
            </button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($inventarios->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Cantidad</th>
                                <th>Stock mínimo</th>
                                <th>Stock máximo</th>
                                <th>Ubicación</th>
                                <th>Estado stock</th>
                                <th>Estado</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($inventarios as $inventario)
                                <tr>
                                    <td>
                                        {{ $inventario->id }}
                                    </td>

                                    <td>
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
                                            <span class="badge bg-success">
                                                Activo
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">

                                            <a href="{{ route('inventarios.show', $inventario->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('inventarios.edit', $inventario->id) }}"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('inventarios.cambiar-estado', $inventario->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este inventario?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($inventario->estado)
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                {{ $inventarios->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay inventarios registrados.
                </div>
            @endif

        </div>
    </div>
@endsection
