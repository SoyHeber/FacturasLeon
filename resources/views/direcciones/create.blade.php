@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Crear Dirección</h1>
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

                    <form action="{{ route('direcciones.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="municipio_id" class="form-label">Municipio</label>
                            <select name="municipio_id" id="municipio_id" class="form-select" required>
                                <option value="">Seleccione un municipio</option>
                                @foreach ($municipios as $municipio)
                                    <option value="{{ $municipio->id }}"
                                        {{ old('municipio_id') == $municipio->id ? 'selected' : '' }}>
                                        {{ $municipio->nombre }} -
                                        {{ $municipio->departamento->nombre ?? 'Sin departamento' }} -
                                        {{ $municipio->departamento->pais->nombre ?? 'Sin país' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea name="direccion" id="direccion" class="form-control" rows="3" required>{{ old('direccion') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="referencia" class="form-label">Referencia</label>
                            <textarea name="referencia" id="referencia" class="form-control" rows="3">{{ old('referencia') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="codigo_postal" class="form-label">Código Postal</label>
                            <input type="text" name="codigo_postal" id="codigo_postal" class="form-control"
                                value="{{ old('codigo_postal') }}" maxlength="15">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="estado">
                                Activo
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="{{ route('direcciones.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
