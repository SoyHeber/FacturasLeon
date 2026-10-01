@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Métodos de Pago
            </h1>

            <p class="text-muted mb-0">
                Administra los métodos de pago aceptados en las ventas de la joyería.
            </p>
        </div>

        @can('metodos_pago.crear')
            <a href="{{ route('metodos_pago.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo método de pago
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

            <form id="filtros" method="GET" action="{{ route('metodos_pago.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Nombre</th>

                            <th>Descripción</th>

                            <th>Estado</th>

                            <th>Fecha creación</th>

                            <th>Acciones</th>

                        </tr>


                        {{-- Filtros --}}
                        <tr>

                            <th>
                                <input type="number" name="id" value="{{ request('id') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="descripcion" value="{{ request('descripcion') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar descripción">
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

                            <th>
                                <input type="date" name="fecha" value="{{ request('fecha') }}" form="filtros"
                                    class="form-control form-control-sm">
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('metodos_pago.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($metodosPago as $metodoPago)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $metodoPago->id }}
                                </td>

                                <td class="fw-bold">
                                    {{ $metodoPago->nombre }}
                                </td>

                                <td>
                                    {{ $metodoPago->descripcion ?: 'Sin descripción' }}
                                </td>

                                <td>

                                    @if ($metodoPago->estado)
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
                                    {{ $metodoPago->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('metodos_pago.ver')
                                            <a href="{{ route('metodos_pago.show', $metodoPago->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('metodos_pago.modificar')
                                            <a href="{{ route('metodos_pago.edit', $metodoPago->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('metodos_pago.eliminar')
                                            <form method="POST"
                                                action="{{ route('metodos_pago.cambiar-estado', $metodoPago->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este método de pago?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($metodoPago->estado)
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

                                <td colspan="6" class="text-center text-muted py-4">

                                    No se encontraron métodos de pago.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $metodosPago->links() }}

            </div>

        </div>

    </div>
@endsection
