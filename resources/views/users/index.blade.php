@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Usuarios
            </h1>

            <p class="text-muted mb-0">
                Administra las cuentas de acceso al sistema y los roles asignados a cada usuario.
            </p>
        </div>

        @can('users.crear')
            <a href="{{ route('users.create') }}" class="btn btn-warning fw-bold px-4 py-2">
                + Nuevo usuario
            </a>
        @endcan

    </div>


    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert">
            </button>
        </div>
    @endif


    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            <form id="filtros" method="GET" action="{{ route('users.index') }}"></form>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        {{-- Títulos --}}
                        <tr class="table-dark">

                            <th>ID</th>

                            <th>Nombre</th>

                            <th>Correo</th>

                            <th>Roles</th>

                            <th>Correo verificado</th>

                            <th>Estado</th>

                            <th>Fecha creación</th>

                            <th>Acciones</th>

                        </tr>


                        {{-- Filtros --}}
                        <tr>

                            <th>
                                <input type="number" name="id" value="{{ request('id') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="ID">
                            </th>

                            <th>
                                <input type="text" name="name" value="{{ request('name') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar nombre">
                            </th>

                            <th>
                                <input type="text" name="email" value="{{ request('email') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar correo">
                            </th>

                            <th>
                                <input type="text" name="rol" value="{{ request('rol') }}" form="filtros"
                                    class="form-control form-control-sm" placeholder="Filtrar rol">
                            </th>

                            <th>
                                <select name="verificado" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('verificado') === '1' ? 'selected' : '' }}>
                                        Verificado
                                    </option>

                                    <option value="0" {{ request('verificado') === '0' ? 'selected' : '' }}>
                                        No verificado
                                    </option>

                                </select>
                            </th>

                            <th>
                                <select name="estado" form="filtros" class="form-select form-select-sm">

                                    <option value="">
                                        Todos
                                    </option>

                                    <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>
                                        Activo
                                    </option>

                                    <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>
                                        Inactivo
                                    </option>

                                </select>
                            </th>

                            <th>
                                <input type="date" name="fecha" value="{{ request('fecha') }}" form="filtros"
                                    class="form-control form-control-sm">
                            </th>

                            {{-- Acciones no tiene filtro --}}
                            <th>

                                <div class="d-flex gap-2">

                                    <button type="submit" form="filtros" class="btn btn-dark btn-sm">
                                        Filtrar
                                    </button>

                                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Limpiar
                                    </a>

                                </div>

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($users as $user)
                            <tr>

                                <td class="fw-semibold">
                                    #{{ $user->id }}
                                </td>

                                <td class="fw-bold">
                                    {{ $user->name }}
                                </td>

                                <td>
                                    {{ $user->email }}
                                </td>

                                <td>
                                    {{ $user->roles->pluck('nombre')->implode(', ') ?: 'Sin roles' }}
                                </td>

                                <td>

                                    @if ($user->email_verified_at)
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Verificado
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">
                                            No verificado
                                        </span>
                                    @endif

                                </td>

                                <td>

                                    @if ($user->estado)
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                            Inactivo
                                        </span>
                                    @endif

                                </td>

                                <td>
                                    {{ $user->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        @can('users.ver')
                                            <a href="{{ route('users.show', $user->id) }}"
                                                class="btn btn-outline-info btn-sm">
                                                Ver
                                            </a>
                                        @endcan

                                        @can('users.modificar')
                                            <a href="{{ route('users.edit', $user->id) }}"
                                                class="btn btn-outline-warning btn-sm">
                                                Editar
                                            </a>
                                        @endcan


                                        @can('users.eliminar')
                                            <form method="POST"
                                                action="{{ route('users.cambiar-estado', $user->id) }}"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este usuario?')">

                                                @csrf
                                                @method('PATCH')

                                                @if ($user->estado)
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif

                                            </form>
                                        @endcan

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="8" class="text-center text-muted py-4">

                                    No se encontraron usuarios.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $users->links() }}

            </div>

        </div>

    </div>
@endsection
