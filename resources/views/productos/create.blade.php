@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">

            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Crear Producto</h1>
                </div>

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

                    <form action="{{ route('productos.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="codigo" class="form-label">
                                Código
                            </label>

                            <input type="text" name="codigo" id="codigo" class="form-control"
                                value="{{ old('codigo') }}" maxlength="50" required>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">
                                Nombre
                            </label>

                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="{{ old('nombre') }}" maxlength="80" required>
                        </div>

                        <div class="mb-3">
                            <label for="categoria_id" class="form-label">
                                Categoría
                            </label>

                            <select name="categoria_id" id="categoria_id" class="form-select" required>
                                <option value="">
                                    Seleccione una categoría
                                </option>

                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}"
                                        {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                        {{ $categoria->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="marca_id" class="form-label">
                                Marca
                            </label>

                            <select name="marca_id" id="marca_id" class="form-select" required>
                                <option value="">
                                    Seleccione una marca
                                </option>

                                @foreach ($marcas as $marca)
                                    <option value="{{ $marca->id }}"
                                        {{ old('marca_id') == $marca->id ? 'selected' : '' }}>
                                        {{ $marca->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">
                                Descripción
                            </label>

                            <textarea name="descripcion" id="descripcion" class="form-control" rows="4" maxlength="255">{{ old('descripcion') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="imagen" class="form-label">
                                Imagen
                            </label>

                            <input type="file" name="imagen" id="imagen" class="form-control"
                                accept=".jpg,.jpeg,.png,.webp">

                            <small class="text-muted">
                                Formatos permitidos: JPG, JPEG, PNG y WEBP.
                                Máximo 2 MB.
                            </small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', true) ? 'checked' : '' }}>

                            <label class="form-check-label" for="estado">
                                Activo
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                Guardar
                            </button>

                            <a href="{{ route('productos.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
