<aside id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <div class="brand-logo">
            JL
        </div>

        <div>
            <p class="brand-title">Joyería de León</p>
            <p class="brand-subtitle">Sistema administrativo</p>
        </div>
    </div>

    <nav class="sidebar-menu">

        <div class="sidebar-section-title">Principal</div>

        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">🏠</span>
            Dashboard
        </a>

        <div class="sidebar-section-title">Catálogos</div>

        <a href="{{ route('categorias.index') }}"
           class="sidebar-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
            <span class="sidebar-icon">📂</span>
            Categorías
        </a>

        <a href="{{ route('marcas.index') }}"
           class="sidebar-link {{ request()->routeIs('marcas.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏷️</span>
            Marcas
        </a>

        <a href="{{ route('productos.index') }}"
           class="sidebar-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
            <span class="sidebar-icon">💍</span>
            Productos
        </a>

        <a href="{{ route('paises.index') }}"
           class="sidebar-link {{ request()->routeIs('paises.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🌎</span>
            Países
        </a>

        <a href="{{ route('departamentos.index') }}"
           class="sidebar-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🗺️</span>
            Departamentos
        </a>

        <a href="{{ route('municipios.index') }}"
           class="sidebar-link {{ request()->routeIs('municipios.*') ? 'active' : '' }}">
            <span class="sidebar-icon">📍</span>
            Municipios
        </a>

        <div class="sidebar-section-title">Operaciones</div>

        <a href="{{ route('inventarios.index') }}"
           class="sidebar-link {{ request()->routeIs('inventarios.*') ? 'active' : '' }}">
            <span class="sidebar-icon">📦</span>
            Inventarios
        </a>

        <a href="{{ route('direcciones.index') }}"
           class="sidebar-link {{ request()->routeIs('direcciones.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏘️</span>
            Direcciones
        </a>

        <a href="{{ route('metodos_pago.index') }}"
           class="sidebar-link {{ request()->routeIs('metodos_pago.*') ? 'active' : '' }}">
            <span class="sidebar-icon">💳</span>
            Métodos de Pago
        </a>

        <a href="{{ route('tipos_identificacion.index') }}"
           class="sidebar-link {{ request()->routeIs('tipos_identificacion.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🪪</span>
            Tipos de Identificación
        </a>

        <div class="sidebar-section-title">Personas</div>

        <a href="{{ route('proveedores.index') }}"
           class="sidebar-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🚚</span>
            Proveedores
        </a>

        <a href="{{ route('clientes.index') }}"
           class="sidebar-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
            <span class="sidebar-icon">👥</span>
            Clientes
        </a>

    </nav>
</aside>