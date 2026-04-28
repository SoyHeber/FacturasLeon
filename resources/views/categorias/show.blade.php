<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0 text-dark">Detalle de Categoría</h2>
    </x-slot>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="mb-3">
                    <h5 class="mb-1">ID</h5>
                    <p class="mb-0">{{ $categoria->id }}</p>
                </div>

                <div class="mb-3">
                    <h5 class="mb-1">Nombre</h5>
                    <p class="mb-0">{{ $categoria->nombre }}</p>
                </div>

                <div class="mb-3">
                    <h5 class="mb-1">Descripción</h5>
                    <p class="mb-0">{{ $categoria->descripcion ?: 'Sin descripción' }}</p>
                </div>

                <div class="mb-3">
                    <h5 class="mb-1">Estado</h5>
                    @if ($categoria->estado)
                        <span class="badge bg-success">Activa</span>
                    @else
                        <span class="badge bg-secondary">Inactiva</span>
                    @endif
                </div>

                <div class="mb-3">
                    <h5 class="mb-1">Fecha de creación</h5>
                    <p class="mb-0">{{ $categoria->created_at?->format('d/m/Y H:i') }}</p>
                </div>

                <div class="mb-4">
                    <h5 class="mb-1">Última actualización</h5>
                    <p class="mb-0">{{ $categoria->updated_at?->format('d/m/Y H:i') }}</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('categorias.index') }}" class="btn btn-secondary">Volver</a>
                    <a href="{{ route('categorias.edit', $categoria->id) }}"
                        class="btn btn-warning text-dark">Editar</a>

                    {{-- <form action="{{ route('categorias.destroy', $categoria->id) }}" method="POST"
                        onsubmit="return confirm('¿Deseas eliminar esta categoría?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form> --}}

                    <form action="{{ route('categorias.cambiar-estado', $categoria->id) }}" method="POST"
                        onsubmit="return confirm('¿Deseas cambiar el estado de esta categoría?')">
                        @csrf
                        @method('PATCH')

                        @if ($categoria->estado)
                            <button type="submit" class="btn btn-secondary">Inactivar</button>
                        @else
                            <button type="submit" class="btn btn-success">Activar</button>
                        @endif
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
