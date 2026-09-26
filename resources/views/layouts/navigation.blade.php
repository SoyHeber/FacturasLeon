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

        {{-- PRINCIPAL --}}
        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
            aria-expanded="{{ request()->routeIs('dashboard') ? 'true' : 'false' }}" aria-controls="menuPrincipal">

            <span>
                🏠 Principal
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ request()->routeIs('dashboard') ? 'show' : '' }}" id="menuPrincipal">

            <a href="{{ route('dashboard') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                Dashboard
            </a>

        </div>


        {{-- CATÁLOGOS --}}
        @php
            $catalogosActivo =
                request()->routeIs('categorias.*') ||
                request()->routeIs('marcas.*') ||
                request()->routeIs('productos.*') ||
                request()->routeIs('paises.*') ||
                request()->routeIs('departamentos.*') ||
                request()->routeIs('municipios.*') ||
                request()->routeIs('metodos_pago.*') ||
                request()->routeIs('tipos_identificacion.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuCatalogos"
            aria-expanded="{{ $catalogosActivo ? 'true' : 'false' }}" aria-controls="menuCatalogos">

            <span>
                📚 Catálogos
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $catalogosActivo ? 'show' : '' }}" id="menuCatalogos">

            <a href="{{ route('categorias.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
                📂 Categorías
            </a>

            <a href="{{ route('marcas.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('marcas.*') ? 'active' : '' }}">
                🏷️ Marcas
            </a>

            <a href="{{ route('productos.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
                💍 Productos
            </a>

            <a href="{{ route('paises.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('paises.*') ? 'active' : '' }}">
                🌎 Países
            </a>

            <a href="{{ route('departamentos.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">
                🗺️ Departamentos
            </a>

            <a href="{{ route('municipios.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('municipios.*') ? 'active' : '' }}">
                📍 Municipios
            </a>

            <a href="{{ route('metodos_pago.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('metodos_pago.*') ? 'active' : '' }}">
                💳 Métodos de Pago
            </a>

            <a href="{{ route('tipos_identificacion.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('tipos_identificacion.*') ? 'active' : '' }}">
                🪪 Tipos de Identificación
            </a>

        </div>


        {{-- OPERACIONES --}}
        @php
            $operacionesActivo =
                request()->routeIs('inventarios.*') ||
                request()->routeIs('movimientos_inventario.*') ||
                request()->routeIs('compras.*') ||
                request()->routeIs('detalles_compra.*') ||
                request()->routeIs('inventarios_compra.*') ||
                request()->routeIs('movimientos_inventario_compra.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuOperaciones"
            aria-expanded="{{ $operacionesActivo ? 'true' : 'false' }}" aria-controls="menuOperaciones">

            <span>
                ⚙️ Operaciones
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $operacionesActivo ? 'show' : '' }}" id="menuOperaciones">

            <a href="{{ route('inventarios.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('inventarios.*') ? 'active' : '' }}">
                📦 Inventarios
            </a>

            <a href="{{ route('movimientos_inventario.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('movimientos_inventario.*') ? 'active' : '' }}">
                🔄 Movimientos Inventario
            </a>

            <a href="{{ route('compras.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('compras.*') ? 'active' : '' }}">
                🛒 Compras
            </a>

            <a href="{{ route('detalles_compra.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('detalles_compra.*') ? 'active' : '' }}">
                📋 Detalles Compra
            </a>

            <a href="{{ route('inventarios_compra.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('inventarios_compra.*') ? 'active' : '' }}">
                🧱 Inventarios Compra
            </a>

            <a href="{{ route('movimientos_inventario_compra.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('movimientos_inventario_compra.*') ? 'active' : '' }}">
                🔁 Movimientos Inventario Compra
            </a>

        </div>


        {{-- PRODUCCIÓN --}}
        @php
            $produccionActivo = request()->routeIs('materiales_producto.*') || request()->routeIs('producciones.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuProduccion"
            aria-expanded="{{ $produccionActivo ? 'true' : 'false' }}" aria-controls="menuProduccion">

            <span>
                🛠️ Producción
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $produccionActivo ? 'show' : '' }}" id="menuProduccion">

            <a href="{{ route('materiales_producto.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('materiales_producto.*') ? 'active' : '' }}">
                🧩 Materiales Producto
            </a>

            <a href="{{ route('producciones.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('producciones.*') ? 'active' : '' }}">
                🏭 Producciones
            </a>

        </div>


        {{-- PERSONAS --}}
        @php
            $personasActivo =
                request()->routeIs('proveedores.*') ||
                request()->routeIs('clientes.*') ||
                request()->routeIs('direcciones.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuPersonas"
            aria-expanded="{{ $personasActivo ? 'true' : 'false' }}" aria-controls="menuPersonas">

            <span>
                👥 Personas
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $personasActivo ? 'show' : '' }}" id="menuPersonas">

            <a href="{{ route('proveedores.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
                🚚 Proveedores
            </a>

            <a href="{{ route('clientes.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                👤 Clientes
            </a>

            <a href="{{ route('direcciones.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('direcciones.*') ? 'active' : '' }}">
                🏘️ Direcciones
            </a>

        </div>


        {{-- SEGURIDAD --}}
        @php
            $seguridadActivo = request()->routeIs('users.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuSeguridad"
            aria-expanded="{{ $seguridadActivo ? 'true' : 'false' }}" aria-controls="menuSeguridad">

            <span>
                🔐 Seguridad
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $seguridadActivo ? 'show' : '' }}" id="menuSeguridad">

            <a href="{{ route('users.index') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                👤 Usuarios
            </a>

        </div>


        {{-- CUENTA --}}
        @php
            $cuentaActivo = request()->routeIs('profile.*');
        @endphp

        <button class="sidebar-module-button" type="button" data-bs-toggle="collapse" data-bs-target="#menuCuenta"
            aria-expanded="{{ $cuentaActivo ? 'true' : 'false' }}" aria-controls="menuCuenta">

            <span>
                ⚙️ Cuenta
            </span>

            <span class="sidebar-arrow">
                ▾
            </span>

        </button>

        <div class="collapse {{ $cuentaActivo ? 'show' : '' }}" id="menuCuenta">

            <a href="{{ route('profile.edit') }}"
                class="sidebar-link sidebar-sub-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                👤 Cuenta
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="sidebar-link sidebar-sub-link w-100 border-0 bg-transparent text-start">

                    🚪 Cerrar sesión
                </button>

            </form>

        </div>

    </nav>

</aside>
