@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Crear Cliente</h1>
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

                    <form action="{{ route('clientes.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="{{ old('nombre') }}" maxlength="150" required>
                        </div>

                        <div class="mb-3">
                            <label for="tipo_identificacion_id" class="form-label">Tipo de Identificación</label>
                            <select name="tipo_identificacion_id" id="tipo_identificacion_id" class="form-select" required>
                                <option value="">Seleccione un tipo</option>
                                @foreach ($tiposIdentificacion as $tipoIdentificacion)
                                    <option value="{{ $tipoIdentificacion->id }}"
                                        {{ old('tipo_identificacion_id') == $tipoIdentificacion->id ? 'selected' : '' }}>
                                        {{ $tipoIdentificacion->nombre }} ({{ $tipoIdentificacion->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="numero_identificacion" class="form-label">Número de Identificación</label>
                            <input type="text" name="numero_identificacion" id="numero_identificacion"
                                class="form-control" value="{{ old('numero_identificacion') }}" maxlength="30" required>
                        </div>

                        <div class="mb-3">
                            <label for="direccion_id" class="form-label">Dirección</label>
                            <select name="direccion_id" id="direccion_id" class="form-select" required>
                                <option value="">Seleccione una dirección</option>
                                @foreach ($direcciones as $direccion)
                                    <option value="{{ $direccion->id }}"
                                        {{ old('direccion_id') == $direccion->id ? 'selected' : '' }}>
                                        {{ $direccion->direccion }} -
                                        {{ $direccion->municipio->nombre ?? 'Sin municipio' }} -
                                        {{ $direccion->municipio->departamento->nombre ?? 'Sin departamento' }} -
                                        {{ $direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="form-control"
                                value="{{ old('telefono') }}" maxlength="20">
                        </div>

                        <div class="mb-3">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" name="correo" id="correo" class="form-control"
                                value="{{ old('correo') }}" maxlength="150">
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
                            <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
