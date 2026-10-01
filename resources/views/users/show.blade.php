@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">

        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">
                        Detalle de Usuario
                    </h1>
                </div>

                <div class="card-body">

                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">
                            {{ $user->id }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">
                            {{ $user->name }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Correo electrónico</h5>
                        <p class="mb-0">
                            {{ $user->email }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">
                            Verificación del correo
                        </h5>

                        @if ($user->email_verified_at)
                            <span class="badge bg-success">
                                Verificado
                            </span>

                            <p class="mt-2 mb-0">
                                {{ $user->email_verified_at->format('d/m/Y H:i') }}
                            </p>
                        @else
                            <span class="badge bg-warning text-dark">
                                No verificado
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Roles</h5>

                        @forelse ($user->roles as $rol)
                            <span class="badge {{ $rol->estado ? 'bg-primary' : 'bg-secondary' }}">
                                {{ $rol->nombre }}
                            </span>
                        @empty
                            <p class="mb-0 text-muted">
                                Sin roles asignados.
                            </p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>

                        @if ($user->estado)
                            <span class="badge bg-success">
                                Activo
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                Inactivo
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">
                            Fecha de creación
                        </h5>

                        <p class="mb-0">
                            {{ $user->created_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">
                            Última actualización
                        </h5>

                        <p class="mb-0">
                            {{ $user->updated_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="d-flex gap-2">

                        <a href="{{ route('users.index') }}" class="btn btn-secondary">
                            Volver
                        </a>

                        @can('users.modificar')
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning">
                                Editar
                            </a>
                        @endcan

                        @can('users.eliminar')
                            <form action="{{ route('users.cambiar-estado', $user->id) }}" method="POST"
                                onsubmit="return confirm('¿Deseas cambiar el estado de este usuario?')">
                                @csrf
                                @method('PATCH')

                                @if ($user->estado)
                                    <button type="submit" class="btn btn-secondary">
                                        Inactivar
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-success">
                                        Activar
                                    </button>
                                @endif

                            </form>
                        @endcan

                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection
