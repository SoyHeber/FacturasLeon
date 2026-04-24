<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0 text-dark">Crear Categoría</h2>
    </x-slot>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
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

                <form action="{{ route('categorias.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input
                            type="text"
                            name="nombre"
                            id="nombre"
                            class="form-control"
                            value="{{ old('nombre') }}"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea
                            name="descripcion"
                            id="descripcion"
                            class="form-control"
                            rows="4"
                        >{{ old('descripcion') }}</textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="estado"
                            id="estado"
                            value="1"
                            {{ old('estado', true) ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="estado">
                            Activa
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <a href="{{ route('categorias.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>