<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'FacturasLeon') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">
                {{ config('app.name', 'FacturasLeon') }}
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                            href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}"
                            href="{{ route('categorias.index') }}">
                            Categorías
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('marcas.*') ? 'active' : '' }}"
                            href="{{ route('marcas.index') }}">
                            Marcas
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('paises.*') ? 'active' : '' }}"
                            href="{{ route('paises.index') }}">
                            Países
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}"
                            href="{{ route('departamentos.index') }}">
                            Departamentos
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('municipios.*') ? 'active' : '' }}"
                            href="{{ route('municipios.index') }}">
                            Municipios
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('direcciones.*') ? 'active' : '' }}"
                            href="{{ route('direcciones.index') }}">
                            Direcciones
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('metodos_pago.*') ? 'active' : '' }}"
                            href="{{ route('metodos_pago.index') }}">
                            Métodos de Pago
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tipos_identificacion.*') ? 'active' : '' }}"
                            href="{{ route('tipos_identificacion.index') }}">
                            Tipos de Identificación
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}"
                            href="{{ route('proveedores.index') }}">
                            Proveedores
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}"
                            href="{{ route('clientes.index') }}">
                            Clientes
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    @isset($header)
        <header class="bg-white border-bottom shadow-sm">
            <div class="container py-3">
                {{ $header }}
            </div>
        </header>
    @endisset

    <main class="py-4">
        <div class="container">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

</body>

</html>
