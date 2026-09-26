@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Crear Opción</h1>
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

                    <form action="{{ route('opciones.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="modulo_id" class="form-label">Módulo</label>
                            <select name="modulo_id" id="modulo_id" class="form-select" required>
                                <option value="">Seleccione un módulo</option>
                                @foreach ($modulos as $modulo)
                                    <option value="{{ $modulo->id }}"
                                        {{ (string) old('modulo_id') === (string) $modulo->id ? 'selected' : '' }}>
                                        {{ $modulo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="{{ old('nombre') }}" maxlength="100" required>
                        </div>

                        <div class="mb-3">
                            <label for="ruta" class="form-label">Ruta</label>
                            <input type="text" name="ruta" id="ruta" class="form-control"
                                value="{{ old('ruta') }}" maxlength="100" required placeholder="Ej: metodos_pago">
                            <small class="text-muted">
                                Prefijo del nombre de la ruta en routes/web.php (metodos_pago.index → metodos_pago).
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3">{{ old('descripcion') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="icono" class="form-label">Icono</label>
                                <input type="text" name="icono" id="icono" class="form-control"
                                    value="{{ old('icono') }}" maxlength="20" placeholder="Ej: 🏷️">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="orden" class="form-label">Orden en el menú</label>
                                <input type="number" name="orden" id="orden" class="form-control"
                                    value="{{ old('orden', 0) }}" min="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Acciones que admite</label>
                            @foreach ($acciones as $accion)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="acciones[]"
                                        id="accion-{{ $accion->id }}" value="{{ $accion->id }}"
                                        {{ in_array($accion->id, old('acciones', $acciones->pluck('id')->all())) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="accion-{{ $accion->id }}">
                                        {{ $accion->nombre }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="estado">
                                Activa
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="{{ route('opciones.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
