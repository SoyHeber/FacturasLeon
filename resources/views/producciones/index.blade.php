@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Producciones
            </h1>

            <p class="text-muted mb-0">
                Administra los registros de fabricación de productos.
            </p>
        </div>

        @can('producciones.crear')
            <a href="{{ route('producciones.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nueva producción
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
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <form id="filtros" method="GET" action="{{ route('producciones.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr class="table-dark">
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Usuario</th>
                            <th>Cantidad</th>
                            <th>Fecha producción</th>
                            <th>Observación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                        <tr>

                            <th>
                                <input form="filtros" type="number" name="id" value="{{ request('id') }}"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="producto" value="{{ request('producto') }}"
                                    class="form-control form-control-sm" placeholder="Producto">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="usuario" value="{{ request('usuario') }}"
                                    class="form-control form-control-sm" placeholder="Usuario">
                            </th>

                            <th>
                                <input form="filtros" type="number" name="cantidad" value="{{ request('cantidad') }}"
                                    class="form-control form-control-sm" placeholder="Cantidad">
                            </th>

                            <th>
                                <input form="filtros" type="date" name="fecha_produccion" value="{{ request('fecha_produccion') }}"
                                    class="form-control form-control-sm">
                            </th>

                            <th>
                                <input form="filtros" type="text" name="observacion" value="{{ request('observacion') }}"
                                    class="form-control form-control-sm" placeholder="Observación">
                            </th>

                            <th>
                                <select form="filtros" name="estado_produccion" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    @foreach (['LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA'] as $estadoProduccion)
                                        <option value="{{ $estadoProduccion }}" {{ request('estado_produccion') === $estadoProduccion ? 'selected' : '' }}>{{ $estadoProduccion }}</option>
                                    @endforeach

                                </select>
                            </th>

                            <th>
                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('producciones.index') }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($producciones as $produccion)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $produccion->id }}
                                </td>

                                <td>
                                    {{ $produccion->producto->nombre ?? 'Sin producto' }}
                                </td>

                                <td>
                                    {{ $produccion->usuario->name ?? 'Sin usuario' }}
                                </td>

                                <td class="fw-semibold">
                                    {{ $produccion->cantidad }}
                                </td>

                                <td>
                                    {{ optional($produccion->fecha_produccion)->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    {{ $produccion->observacion ?: 'Sin observación' }}
                                </td>

                                <td>

                                    <span class="badge bg-secondary">{{ $produccion->estado_produccion }}</span>

                                    @if ($produccion->estado_produccion === 'LEGADA')
                                        <small class="text-muted d-block">Histórica / no conciliada</small>
                                    @endif

                                </td>

                                <td>

                                    <div class="d-flex gap-2 flex-wrap">

                                        @can('producciones.ver')
                                            <a href="{{ route('producciones.show', $produccion->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @include('producciones._confirmar', ['claseConfirmar' => 'btn-sm'])
                                        @include('producciones._anular', ['claseAnular' => 'btn-sm'])

                                        @if ($produccion->esEditable())
                                            @can('producciones.modificar')
                                                <a href="{{ route('producciones.edit', $produccion->id) }}"
                                                    class="btn btn-outline-warning btn-sm">
                                                    Editar
                                                </a>
                                            @endcan

                                        @endif

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron producciones registradas.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-4">
                {{ $producciones->links() }}
            </div>

        </div>
    </div>
@endsection
