@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>

        <a href="{{ route('users.create') }}" class="btn btn-primary">
            Nuevo usuario
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($users->count())
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Correo verificado</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th width="260">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>

                                    <td>{{ $user->name }}</td>

                                    <td>{{ $user->email }}</td>

                                    <td>
                                        @if ($user->email_verified_at)
                                            <span class="badge bg-success">
                                                Verificado
                                            </span>
                                        @else
                                            <span class="badge bg-warning text-dark">
                                                No verificado
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($user->estado)
                                            <span class="badge bg-success">
                                                Activo
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $user->created_at?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">

                                            <a href="{{ route('users.show', $user->id) }}"
                                                class="btn btn-info btn-sm text-white">
                                                Ver
                                            </a>

                                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm">
                                                Editar
                                            </a>

                                            <form action="{{ route('users.cambiar-estado', $user->id) }}" method="POST"
                                                onsubmit="return confirm('¿Deseas cambiar el estado de este usuario?')">
                                                @csrf
                                                @method('PATCH')

                                                @if ($user->estado)
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        Inactivar
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        Activar
                                                    </button>
                                                @endif
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                {{ $users->links() }}
            @else
                <div class="alert alert-secondary mb-0">
                    No hay usuarios registrados.
                </div>
            @endif

        </div>
    </div>
@endsection
