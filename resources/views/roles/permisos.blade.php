@extends('layouts.app-bootstrap')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Permisos del rol: {{ $rol->nombre }}</h1>
            <small class="text-muted">Marca las acciones que este rol puede realizar en cada opción.</small>
        </div>
        <a href="{{ route('roles.show', $rol->id) }}" class="btn btn-secondary">Volver</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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

    <form action="{{ route('roles.guardar-permisos', $rol->id) }}" method="POST">
        @csrf
        @method('PUT')

        @forelse ($modulos as $modulo)
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">
                        {{ $modulo->icono }} {{ $modulo->nombre }}
                        @unless ($modulo->estado)
                            <span class="badge bg-secondary">Inactivo</span>
                        @endunless
                    </h2>
                </div>
                <div class="card-body">
                    @if ($modulo->opciones->count())
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Opción</th>
                                        <th>Acciones</th>
                                        <th width="120">Todas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modulo->opciones as $opcion)
                                        <tr>
                                            <td>
                                                {{ $opcion->icono }} {{ $opcion->nombre }}
                                                @unless ($opcion->estado)
                                                    <span class="badge bg-secondary">Inactiva</span>
                                                @endunless
                                            </td>
                                            <td>
                                                @forelse ($opcion->acciones as $accion)
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input permiso-opcion-{{ $opcion->id }}"
                                                            type="checkbox" name="permisos[{{ $opcion->id }}][]"
                                                            id="permiso-{{ $opcion->id }}-{{ $accion->id }}"
                                                            value="{{ $accion->id }}"
                                                            {{ in_array($opcion->id . '-' . $accion->id, $permisosActuales, true) ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="permiso-{{ $opcion->id }}-{{ $accion->id }}">
                                                            {{ $accion->nombre }}
                                                        </label>
                                                    </div>
                                                @empty
                                                    <span class="text-muted">La opción no tiene acciones configuradas.</span>
                                                @endforelse
                                            </td>
                                            <td>
                                                @if ($opcion->acciones->count())
                                                    <button type="button" class="btn btn-outline-dark btn-sm"
                                                        onclick="marcarOpcion({{ $opcion->id }})">
                                                        Marcar / quitar
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-secondary mb-0">
                            Este módulo no tiene opciones registradas.
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="alert alert-secondary">
                No hay módulos registrados.
            </div>
        @endforelse

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar permisos</button>
            <a href="{{ route('roles.show', $rol->id) }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

    <script>
        function marcarOpcion(opcionId) {
            const checks = document.querySelectorAll('.permiso-opcion-' + opcionId);
            const marcarTodos = Array.from(checks).some(check => !check.checked);

            checks.forEach(check => check.checked = marcarTodos);
        }
    </script>
@endsection
