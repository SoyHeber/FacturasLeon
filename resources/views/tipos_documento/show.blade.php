@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Detalle de tipo de documento</h1>
            <p class="module-subtitle">
                Consulta la información registrada para este tipo de documento.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('tipos_documento.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
                ← Volver
            </a>

            @can('tipos_documento.modificar')
                <a href="{{ route('tipos_documento.edit', $tipoDocumento->id) }}"
                    class="btn btn-warning rounded-3 fw-bold px-4 py-2">
                    Editar
                </a>
            @endcan
        </div>
    </div>

    <div class="module-card card shadow-sm border-0 rounded-4">
        <div class="module-card-body card-body p-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="p-4 rounded-4 h-100" style="background: #f9fafb; border: 1px solid #e5e7eb;">
                        <div class="mb-3" style="font-size: 42px;">
                            📄
                        </div>

                        <p class="text-muted mb-1">
                            Tipo de documento
                        </p>

                        <h3 class="fw-bold text-dark mb-3">
                            {{ $tipoDocumento->nombre }}
                        </h3>

                        @if ($tipoDocumento->estado)
                            <span class="badge-active">Activo</span>
                        @else
                            <span class="badge-inactive">Inactivo</span>
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
                                    #{{ $tipoDocumento->id }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Estado
                                </p>

                                @if ($tipoDocumento->estado)
                                    <span class="badge-active">Activo</span>
                                @else
                                    <span class="badge-inactive">Inactivo</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Código
                                </p>
                                <p class="mb-0 fw-semibold">
                                    {{ $tipoDocumento->codigo }}
                                </p>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Descripción
                                </p>
                                <p class="mb-0">
                                    {{ $tipoDocumento->descripcion ?: 'Sin descripción' }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Fecha de creación
                                </p>
                                <p class="mb-0">
                                    {{ $tipoDocumento->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background: #ffffff; border: 1px solid #e5e7eb;">
                                <p class="text-muted mb-1 small fw-bold">
                                    Última actualización
                                </p>
                                <p class="mb-0">
                                    {{ $tipoDocumento->updated_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                @can('tipos_documento.eliminar')
                    <form action="{{ route('tipos_documento.cambiar-estado', $tipoDocumento->id) }}"
                        method="POST"
                        onsubmit="return confirm('¿Deseas cambiar el estado de este tipo de documento?')">
                        @csrf
                        @method('PATCH')

                        @if ($tipoDocumento->estado)
                            <button type="submit" class="btn btn-outline-secondary rounded-3 fw-bold">
                                Inactivar
                            </button>
                        @else
                            <button type="submit" class="btn btn-outline-success rounded-3 fw-bold">
                                Activar
                            </button>
                        @endif
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
