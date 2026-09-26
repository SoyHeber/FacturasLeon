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

        {{-- Principal --}}
        <div class="sidebar-section-title">
            Principal
        </div>

        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">

            <span class="sidebar-icon">
                🏠
            </span>

            Dashboard
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

        <div class="sidebar-section-title">
            Cuenta
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="sidebar-link w-100 border-0 bg-transparent text-start">
                <span class="sidebar-icon">
                    🚪
                </span>

                Cerrar sesión
            </button>
        </form>

    </nav>

</aside>
