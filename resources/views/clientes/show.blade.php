@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Detalle de Cliente</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">ID</h5>
                        <p class="mb-0">{{ $cliente->id }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Nombre</h5>
                        <p class="mb-0">{{ $cliente->nombre }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Tipo de Identificación</h5>
                        <p class="mb-0">{{ $cliente->tipoIdentificacion->nombre ?? 'Sin tipo' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Número de Identificación</h5>
                        <p class="mb-0">{{ $cliente->numero_identificacion }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Dirección</h5>
                        @if ($cliente->direccion)
                            <p class="mb-0">{{ $cliente->direccion->direccion }}</p>
                            <small class="text-muted">
                                {{ $cliente->direccion->municipio->nombre ?? 'Sin municipio' }} -
                                {{ $cliente->direccion->municipio->departamento->nombre ?? 'Sin departamento' }} -
                                {{ $cliente->direccion->municipio->departamento->pais->nombre ?? 'Sin país' }}
                            </small>
                        @else
                            <p class="mb-0">Sin dirección</p>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Teléfono</h5>
                        <p class="mb-0">{{ $cliente->telefono ?: 'Sin teléfono' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Correo</h5>
                        <p class="mb-0">{{ $cliente->correo ?: 'Sin correo' }}</p>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Estado</h5>
                        @if ($cliente->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-1">Fecha de creación</h5>
                        <p class="mb-0">{{ $cliente->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-1">Última actualización</h5>
                        <p class="mb-0">{{ $cliente->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('clientes.edit', $cliente->id) }}" class="btn btn-warning">Editar</a>

                        <form action="{{ route('clientes.cambiar-estado', $cliente->id) }}" method="POST"
                            onsubmit="return confirm('¿Deseas cambiar el estado de este cliente?')">
                            @csrf
                            @method('PATCH')

                            @if ($cliente->estado)
                                <button type="submit" class="btn btn-secondary">Inactivar</button>
                            @else
                                <button type="submit" class="btn btn-success">Activar</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
