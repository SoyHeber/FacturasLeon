@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Nueva categoría</h1>
            <p class="module-subtitle">
                Registra una nueva categoría para clasificar los productos de la joyería.
            </p>
        </div>

        <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
            ← Volver
        </a>
    </div>

    <div class="module-card">
        <div class="module-card-body">
            <form action="{{ route('categorias.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-bold">
                            Nombre de la categoría
                        </label>

                        <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}"
                            class="form-control rounded-3 @error('nombre') is-invalid @enderror"
                            placeholder="Ejemplo: Anillos, Cadenas, Pulseras" required>

                        @error('nombre')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="estado" class="form-label fw-bold">
                            Estado
                        </label>

                        <select name="estado" id="estado"
                            class="form-select rounded-3 @error('estado') is-invalid @enderror" required>
                            <option value="1" {{ old('estado', 1) == 1 ? 'selected' : '' }}>
                                Activa
                            </option>
                            <option value="0" {{ old('estado') == 0 ? 'selected' : '' }}>
                                Inactiva
                            </option>
                        </select>

                        @error('estado')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="descripcion" class="form-label fw-bold">
                            Descripción
                        </label>

                        <textarea name="descripcion" id="descripcion" rows="5"
                            class="form-control rounded-3 @error('descripcion') is-invalid @enderror"
                            placeholder="Escribe una breve descripción de la categoría...">{{ old('descripcion') }}</textarea>

                        @error('descripcion')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary rounded-3 fw-bold">
                        Cancelar
                    </a>

                    <button type="submit" class="btn-gold">
                        Guardar categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
