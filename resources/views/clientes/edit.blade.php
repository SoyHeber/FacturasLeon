@extends('layouts.app-bootstrap')

@section('content')
    @php
        $tipoActual = old('tipo_cliente', $cliente->persona ? 'PERSONA' : ($cliente->sociedad ? 'SOCIEDAD' : ''));
    @endphp

    <div class="row justify-content-center">

        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">Editar Cliente</h1>
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

                    <form action="{{ route('clientes.update', $cliente->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="tipo_cliente" class="form-label">
                                Tipo de Cliente
                            </label>

                            <select name="tipo_cliente" id="tipo_cliente" class="form-select" required>
                                <option value="">
                                    Seleccione una opción
                                </option>

                                <option value="PERSONA" {{ $tipoActual === 'PERSONA' ? 'selected' : '' }}>
                                    Persona Individual
                                </option>

                                <option value="SOCIEDAD" {{ $tipoActual === 'SOCIEDAD' ? 'selected' : '' }}>
                                    Sociedad Anónima / Empresa
                                </option>
                            </select>
                        </div>

                        <div id="campos-persona" style="display: none;">

                            <div class="row">

                                <div class="col-md-4 mb-3">
                                    <label for="nombre1" class="form-label">
                                        Primer Nombre
                                    </label>

                                    <input type="text" name="nombre1" id="nombre1" class="form-control"
                                        value="{{ old('nombre1', $cliente->persona->nombre1 ?? '') }}" maxlength="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="nombre2" class="form-label">
                                        Segundo Nombre
                                    </label>

                                    <input type="text" name="nombre2" id="nombre2" class="form-control"
                                        value="{{ old('nombre2', $cliente->persona->nombre2 ?? '') }}" maxlength="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="nombre3" class="form-label">
                                        Tercer Nombre
                                    </label>

                                    <input type="text" name="nombre3" id="nombre3" class="form-control"
                                        value="{{ old('nombre3', $cliente->persona->nombre3 ?? '') }}" maxlength="100">
                                </div>

                            </div>

                            <div class="row">

                                <div class="col-md-4 mb-3">
                                    <label for="apellido1" class="form-label">
                                        Primer Apellido
                                    </label>

                                    <input type="text" name="apellido1" id="apellido1" class="form-control"
                                        value="{{ old('apellido1', $cliente->persona->apellido1 ?? '') }}" maxlength="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="apellido2" class="form-label">
                                        Segundo Apellido
                                    </label>

                                    <input type="text" name="apellido2" id="apellido2" class="form-control"
                                        value="{{ old('apellido2', $cliente->persona->apellido2 ?? '') }}" maxlength="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="apellido_casada" class="form-label">
                                        Apellido de Casada
                                    </label>

                                    <input type="text" name="apellido_casada" id="apellido_casada" class="form-control"
                                        value="{{ old('apellido_casada', $cliente->persona->apellido_casada ?? '') }}"
                                        maxlength="100">
                                </div>

                            </div>

                        </div>

                        <div id="campos-sociedad" style="display: none;">

                            <div class="mb-3">
                                <label for="nombre_sociedad" class="form-label">
                                    Nombre / Razón Social
                                </label>

                                <input type="text" name="nombre_sociedad" id="nombre_sociedad" class="form-control"
                                    value="{{ old('nombre_sociedad', $cliente->sociedad->nombre ?? '') }}" maxlength="200">
                            </div>

                        </div>

                        <hr>

                        <div class="mb-3">
                            <label for="tipo_identificacion_id" class="form-label">
                                Tipo de Identificación
                            </label>

                            <select name="tipo_identificacion_id" id="tipo_identificacion_id" class="form-select" required>
                                <option value="">
                                    Seleccione un tipo
                                </option>

                                @foreach ($tiposIdentificacion as $tipoIdentificacion)
                                    <option value="{{ $tipoIdentificacion->id }}"
                                        {{ old('tipo_identificacion_id', $cliente->tipo_identificacion_id) == $tipoIdentificacion->id ? 'selected' : '' }}>
                                        {{ $tipoIdentificacion->nombre }}
                                        ({{ $tipoIdentificacion->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="numero_identificacion" class="form-label">
                                Número de Identificación
                            </label>

                            <input type="text" name="numero_identificacion" id="numero_identificacion"
                                class="form-control"
                                value="{{ old('numero_identificacion', $cliente->numero_identificacion) }}"
                                maxlength="30" required>
                        </div>

                        <div class="mb-3">
                            <label for="direccion_id" class="form-label">
                                Dirección
                            </label>

                            <select name="direccion_id" id="direccion_id" class="form-select" required>
                                <option value="">
                                    Seleccione una dirección
                                </option>

                                @foreach ($direcciones as $direccion)
                                    <option value="{{ $direccion->id }}"
                                        {{ old('direccion_id', $cliente->direccion_id) == $direccion->id ? 'selected' : '' }}>
                                        {{ $direccion->direccion }}
                                        -
                                        {{ $direccion->municipio->nombre ?? 'Sin municipio' }}
                                        -
                                        {{ $direccion->municipio->departamento->nombre ?? 'Sin departamento' }}
                                        -
                                        {{ $direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="telefono" class="form-label">
                                Teléfono
                            </label>

                            <input type="text" name="telefono" id="telefono" class="form-control"
                                value="{{ old('telefono', $cliente->telefono) }}" maxlength="20">
                        </div>

                        <div class="mb-3">
                            <label for="correo" class="form-label">
                                Correo
                            </label>

                            <input type="email" name="correo" id="correo" class="form-control"
                                value="{{ old('correo', $cliente->correo) }}" maxlength="150">
                        </div>

                        <div class="form-check mb-3">

                            <input class="form-check-input" type="checkbox" name="estado" id="estado"
                                value="1" {{ old('estado', $cliente->estado) ? 'checked' : '' }}>

                            <label class="form-check-label" for="estado">
                                Activo
                            </label>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary">
                                Actualizar
                            </button>

                            <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const tipoCliente = document.getElementById('tipo_cliente');
            const camposPersona = document.getElementById('campos-persona');
            const camposSociedad = document.getElementById('campos-sociedad');

            function actualizarFormulario() {

                const valor = tipoCliente.value;

                camposPersona.style.display =
                    valor === 'PERSONA' ? 'block' : 'none';

                camposSociedad.style.display =
                    valor === 'SOCIEDAD' ? 'block' : 'none';
            }

            tipoCliente.addEventListener('change', actualizarFormulario);

            actualizarFormulario();
        });
    </script>
@endsection
