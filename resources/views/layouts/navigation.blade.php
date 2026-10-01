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

        {{-- Módulos y opciones según los permisos del usuario (App\Models\User::menu) --}}
        @auth
            @foreach (Auth::user()->menu() as $modulo)
                <div class="sidebar-section-title">
                    {{ $modulo->nombre }}
                </div>

                @foreach ($modulo->opciones as $opcion)
                    @if (Route::has($opcion->ruta . '.index'))
                        <a href="{{ route($opcion->ruta . '.index') }}"
                            class="sidebar-link {{ request()->routeIs($opcion->ruta . '.*') ? 'active' : '' }}">
                            <span class="sidebar-icon">{{ $opcion->icono }}</span>
                            {{ $opcion->nombre }}
                        </a>
                    @endif
                @endforeach
            @endforeach
        @endauth

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
