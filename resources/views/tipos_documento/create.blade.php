@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Nuevo tipo de documento</h1>
            <p class="module-subtitle">
                Registra un nuevo tipo de documento FEL para el sistema.
            </p>
        </div>

        <a href="{{ route('tipos_documento.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
            ← Volver
        </a>
    </div>

    <div class="module-card card shadow-sm border-0 rounded-4">
        <div class="module-card-body card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Corrige los siguientes errores:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('tipos_documento.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-bold">
                            Nombre
                        </label>

                        <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}"
                            class="form-control rounded-3 @error('nombre') is-invalid @enderror" maxlength="100" required>
                    </div>

                    <div class="col-md-4">
                        <label for="codigo" class="form-label fw-bold">
                            Código
                        </label>

                        <input type="text" name="codigo" id="codigo" value="{{ old('codigo') }}"
                            class="form-control rounded-3 @error('codigo') is-invalid @enderror" maxlength="10" required>
                    </div>

                    <div class="col-md-8">
                        <label for="descripcion" class="form-label fw-bold">
                            Descripción
                        </label>

                        <textarea name="descripcion" id="descripcion" rows="5" maxlength="255"
                            class="form-control rounded-3 @error('descripcion') is-invalid @enderror">{{ old('descripcion') }}</textarea>
                    </div>

                    <div class="col-md-4">
                        <p class="form-label fw-bold">
                            Estado
                        </p>

                        <div class="form-check">
                            <input type="hidden" name="estado" value="0">
                            <input class="form-check-input @error('estado') is-invalid @enderror" type="checkbox"
                                name="estado" id="estado" value="1" {{ old('estado', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="estado">
                                Activo
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('tipos_documento.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
                        Cancelar
                    </a>

                    @can('tipos_documento.crear')
                        <button type="submit" class="btn btn-warning rounded-3 fw-bold px-4 py-2">
                            Guardar
                        </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
@endsection
