@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Productos
            </h1>

            <p class="text-muted mb-0">
                Administra el catálogo de joyas y artículos que se venden en la joyería.
            </p>
        </div>

        @can('productos.crear')
            <a href="{{ route('productos.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo producto
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
            <form id="filtros" method="GET" action="{{ route('productos.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Imagen</th>

                            <th>Código</th>

                            <th>Nombre</th>

                            <th>Categoría</th>

                            <th>Marca</th>

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

                            <th></th>

                            <th>
                                <input type="text" name="codigo" value="{{ request('codigo') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar código">
                            </th>

                            <th>
                                <input type="text" name="nombre" value="{{ request('nombre') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="categoria" value="{{ request('categoria') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar categoría">
                            </th>

                            <th>
                                <input type="text" name="marca" value="{{ request('marca') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar marca">
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

                                    <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($productos as $producto)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $producto->id }}
                                </td>

                                <td>

                                    @if ($producto->img_path)
                                        <img src="{{ asset('storage/' . $producto->img_path) }}"
                                            alt="{{ $producto->nombre }}" width="70" height="70"
                                            class="rounded border" style="object-fit: cover;">
                                    @else
                                        <span class="text-muted">Sin imagen</span>
                                    @endif

                                </td>

                                <td>
                                    {{ $producto->codigo }}
                                </td>

                                <td class="fw-bold">
                                    {{ $producto->nombre }}
                                </td>

                                <td>
                                    {{ $producto->categoria->nombre ?? 'Sin categoría' }}
                                </td>

                                <td>
                                    {{ $producto->marca->nombre ?? 'Sin marca' }}
                                </td>

                                <td>
                                    {{ $producto->descripcion ?: 'Sin descripción' }}
                                </td>

                                <td>

                                    @if ($producto->estado)
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
                                    {{ $producto->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('productos.ver')
                                            <a href="{{ route('productos.show', $producto->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('productos.modificar')
                                            <a href="{{ route('productos.edit', $producto->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('productos.eliminar')
                                            <form method="POST"
                                                action="{{ route('productos.cambiar-estado', $producto->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este producto?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($producto->estado)
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

                                    No se encontraron productos.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $productos->links() }}

            </div>

        </div>

    </div>
@endsection
