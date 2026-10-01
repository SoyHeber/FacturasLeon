@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Editar Acción</h1>
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

                    <form action="{{ route('acciones.update', $accion->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="{{ old('nombre', $accion->nombre) }}" maxlength="50" required>
                        </div>

                        <div class="mb-3">
                            <label for="clave" class="form-label">Clave</label>
                            <input type="text" name="clave" id="clave" class="form-control"
                                value="{{ old('clave', $accion->clave) }}" maxlength="50" required placeholder="Ej: exportar">
                            <small class="text-muted">
                                Identificador usado en el código (@@can('marcas.&lt;clave&gt;')). Cambiar la clave de
                                ver, crear, modificar o eliminar deja sin efecto esos permisos.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3">{{ old('descripcion', $accion->descripcion) }}</textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', $accion->estado) ? 'checked' : '' }}>
                            <label class="form-check-label" for="estado">
                                Activa
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                            <a href="{{ route('acciones.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
