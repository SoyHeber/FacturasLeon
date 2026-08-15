<x-app-layout>
    <div class="mb-4">
        <h1 class="fw-bold text-dark mb-1">Dashboard</h1>
        <p class="text-muted mb-0">
            Resumen general del sistema de Joyería de León.
        </p>
    </div>

    <div class="alert border-0 rounded-4 shadow-sm mb-4" style="background: #dcfce7; color: #166534;">
        <strong>Bienvenido de nuevo.</strong>
        Has iniciado sesión correctamente en el sistema.
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Categorías</p>
                            <h2 class="fw-bold mb-0">{{ $totalCategorias }}</h2>
                        </div>
                        <div style="width: 52px; height: 52px; border-radius: 16px; background: #dbeafe; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                            📂
                        </div>
                    </div>

                    <a href="{{ route('categorias.index') }}" class="btn btn-sm mt-4 w-100" style="background:#0f172a; color:white; border-radius:12px;">
                        Ver categorías
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Marcas</p>
                            <h2 class="fw-bold mb-0">{{ $totalMarcas }}</h2>
                        </div>
                        <div style="width: 52px; height: 52px; border-radius: 16px; background: #f3e8ff; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                            🏷️
                        </div>
                    </div>

                    <a href="{{ route('marcas.index') }}" class="btn btn-sm mt-4 w-100" style="background:#0f172a; color:white; border-radius:12px;">
                        Ver marcas
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Productos</p>
                            <h2 class="fw-bold mb-0">{{ $totalProductos }}</h2>
                        </div>
                        <div style="width: 52px; height: 52px; border-radius: 16px; background: #fef3c7; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                            💍
                        </div>
                    </div>

                    <a href="{{ route('productos.index') }}" class="btn btn-sm mt-4 w-100" style="background:#d4af37; color:#111827; border-radius:12px; font-weight:700;">
                        Ver productos
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>