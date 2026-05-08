@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Editar Municipio</h1>
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

                    <form action="{{ route('municipios.update', $municipio->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="departamento_id" class="form-label">Departamento</label>
                            <select name="departamento_id" id="departamento_id" class="form-select" required>
                                <option value="">Seleccione un departamento</option>
                                @foreach ($departamentos as $departamento)
                                    <option value="{{ $departamento->id }}"
                                        {{ old('departamento_id', $municipio->departamento_id) == $departamento->id ? 'selected' : '' }}>
                                        {{ $departamento->pais->nombre ?? 'Sin país' }} - {{ $departamento->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="{{ old('nombre', $municipio->nombre) }}" maxlength="100" required>
                        </div>

                        <div class="mb-3">
                            <label for="codigo" class="form-label">Código</label>
                            <input type="text" name="codigo" id="codigo" class="form-control"
                                value="{{ old('codigo', $municipio->codigo) }}" maxlength="10">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', $municipio->estado) ? 'checked' : '' }}>
                            <label class="form-check-label" for="estado">
                                Activo
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                            <a href="{{ route('municipios.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
