@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">

        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">
                        Editar Usuario
                    </h1>
                </div>

                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">

                            <strong>
                                Corrige los siguientes errores:
                            </strong>

                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>

                        </div>
                    @endif

                    <form action="{{ route('users.update', $user->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Nombre
                            </label>

                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name', $user->name) }}" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Correo electrónico
                            </label>

                            <input type="email" name="email" id="email" class="form-control"
                                value="{{ old('email', $user->email) }}" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Nueva contraseña
                            </label>

                            <input type="password" name="password" id="password" class="form-control" minlength="8">

                            <small class="text-muted">
                                Déjalo vacío si no deseas cambiar la contraseña.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">
                                Confirmar nueva contraseña
                            </label>

                            <input type="password" name="password_confirmation" id="password_confirmation"
                                class="form-control" minlength="8">
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">
                                Roles
                            </label>

                            @forelse ($roles as $rol)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="roles[]"
                                        id="rol-{{ $rol->id }}" value="{{ $rol->id }}"
                                        {{ in_array($rol->id, old('roles', $user->roles->pluck('id')->all())) ? 'checked' : '' }}>

                                    <label class="form-check-label" for="rol-{{ $rol->id }}">
                                        {{ $rol->nombre }}
                                        @unless ($rol->estado)
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endunless
                                    </label>
                                </div>
                            @empty
                                <p class="mb-0 text-muted">
                                    No hay roles registrados.
                                </p>
                            @endforelse
                        </div>

                        <div class="form-check mb-3">

                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', $user->estado) ? 'checked' : '' }}>

                            <label class="form-check-label" for="estado">
                                Activo
                            </label>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary">
                                Actualizar
                            </button>

                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
