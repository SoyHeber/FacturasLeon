@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Categorías</h1>
            <p class="module-subtitle">
                Administra las categorías utilizadas para clasificar los productos de la joyería.
            </p>
        </div>

        <a href="{{ route('categorias.create') }}" class="btn-gold">
            + Nueva categoría
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="module-card">
        <div class="module-card-body">
            @if ($categorias->count())
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th style="width: 140px;">Estado</th>
                                <th style="width: 180px;">Fecha creación</th>
                                <th style="width: 260px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($categorias as $categoria)
                                <tr>
                                    <td class="fw-bold text-muted">
                                        #{{ $categoria->id }}
                                    </td>

                                    <td class="fw-semibold">
                                        {{ $categoria->nombre }}
                                    </td>

                                    <td class="text-muted">
                                        {{ $categoria->descripcion ?: 'Sin descripción' }}
                                    </td>

                                    <td>
                                        @if ($categoria->estado)
                                            <span class="badge-active">Activa</span>
                                        @else
                                            <span class="badge-inactive">Inactiva</span>
                                        @endif
                                    </td>

                                    <td class="text-muted">
                                        {{ $categoria->created_at?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('categorias.show', $categoria->id) }}"
                                               class="btn btn-sm btn-outline-info rounded-3 fw-bold">
                                                Ver
                                            </a>

                                            <a href="{{ route('categorias.edit', $categoria->id) }}"
                                               class="btn btn-sm btn-outline-warning rounded-3 fw-bold">
                                                Editar
                                            </a>

                                            <form action="{{ route('categorias.cambiar-estado', $categoria->id) }}"
                                                  method="POST">
                                                @csrf
                                                @method('PATCH')

                                                @if ($categoria->estado)
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-secondary rounded-3 fw-bold">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-success rounded-3 fw-bold">
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

                <div class="mt-4">
                    {{ $categorias->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-3" style="font-size: 42px;">
                        📂
                    </div>

                    <h5 class="fw-bold text-dark">
                        No hay categorías registradas
                    </h5>

                    <p class="text-muted mb-4">
                        Crea tu primera categoría para comenzar a clasificar tus productos.
                    </p>

                    <a href="{{ route('categorias.create') }}" class="btn-gold">
                        + Crear categoría
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection