@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Detalle de categoría</h1>
            <p class="module-subtitle">
                Consulta la información registrada para esta categoría.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
                ← Volver
            </a>

            @can('categorias.modificar')
                <a href="{{ route('categorias.edit', $categoria->id) }}" class="btn-gold">
                    Editar
                </a>
            @endcan
        </div>
    </div>

    <div class="module-card">
        <div class="module-card-body">
            <div class="row g-4">

                <div class="col-md-4">
                    <div class="p-4 rounded-4 h-100" style="background: #f9fafb; border: 1px solid #e5e7eb;">
                        <div class="mb-3" style="font-size: 42px;">
                            📂
                        </div>

                        <p class="text-muted mb-1">
                            Categoría
                        </p>

                        <h3 class="fw-bold text-dark mb-3">
                            {{ $categoria->nombre }}
                        </h3>

                        @if ($categoria->estado)
                            <span class="badge-active">Activa</span>
                        @else
                            <span class="badge-inactive">Inactiva</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    ID
                                </p>
                                <p class="mb-0 fw-semibold">
                                    #{{ $categoria->id }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Estado
                                </p>

                                @if ($categoria->estado)
                                    <span class="badge-active">Activa</span>
                                @else
                                    <span class="badge-inactive">Inactiva</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Descripción
                                </p>

                                <p class="mb-0">
                                    {{ $categoria->descripcion ?: 'Sin descripción registrada.' }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Fecha de creación
                                </p>

                                <p class="mb-0">
                                    {{ $categoria->created_at?->format('d/m/Y H:i') ?? 'No disponible' }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Última actualización
                                </p>

                                <p class="mb-0">
                                    {{ $categoria->updated_at?->format('d/m/Y H:i') ?? 'No disponible' }}
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                @can('categorias.eliminar')
                    <form action="{{ route('categorias.cambiar-estado', $categoria->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        @if ($categoria->estado)
                            <button type="submit" class="btn btn-outline-secondary rounded-3 fw-bold">
                                Inactivar categoría
                            </button>
                        @else
                            <button type="submit" class="btn btn-outline-success rounded-3 fw-bold">
                                Activar categoría
                            </button>
                        @endif
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
