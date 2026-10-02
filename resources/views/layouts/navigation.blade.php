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


        {{-- Módulos y opciones según los permisos del usuario --}}
        @auth
            @foreach (Auth::user()->menu() as $modulo)
                @php
                    $opciones = $modulo->opciones->filter(
                        fn ($opcion) => Route::has($opcion->ruta . '.index')
                    );
                    $moduloActivo = $opciones->contains(
                        fn ($opcion) => request()->routeIs($opcion->ruta . '.*')
                    );
                    $menuId = 'menuModulo' . $modulo->id;
                @endphp

                @if ($opciones->isEmpty())
                    @continue
                @endif

                <button class="sidebar-module-button" type="button" data-bs-toggle="collapse"
                    data-bs-target="#{{ $menuId }}" aria-expanded="{{ $moduloActivo ? 'true' : 'false' }}"
                    aria-controls="{{ $menuId }}">

                    <span>
                        {{ $modulo->icono }} {{ $modulo->nombre }}
                    </span>

                    <span class="sidebar-arrow">
                        ▾
                    </span>

                </button>

                <div class="collapse {{ $moduloActivo ? 'show' : '' }}" id="{{ $menuId }}">
                    @foreach ($opciones as $opcion)
                        @can($opcion->ruta . '.ver')
                            <a href="{{ route($opcion->ruta . '.index') }}"
                                class="sidebar-link sidebar-sub-link {{ request()->routeIs($opcion->ruta . '.*') ? 'active' : '' }}">
                                <span class="sidebar-icon">{{ $opcion->icono }}</span>
                                {{ $opcion->nombre }}
                            </a>
                        @endcan
                    @endforeach
                </div>
            @endforeach
        @endauth


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
