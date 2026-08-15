<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Joyería de León') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: #f3f4f6;
            overflow-x: hidden;
        }

        .app-wrapper {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 280px;
            background: #0f172a;
            color: #ffffff;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            transition: all 0.3s ease;
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.18);
        }

        .sidebar.hidden-sidebar {
            left: -280px;
        }

        .sidebar-header {
            height: 78px;
            display: flex;
            align-items: center;
            padding: 0 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #d4af37;
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin-right: 12px;
        }

        .brand-title {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        .sidebar-menu {
            padding: 18px 14px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            background: rgba(212, 175, 55, 0.12);
            color: #facc15;
        }

        .sidebar-link.active {
            background: #d4af37;
            color: #111827;
        }

        .sidebar-icon {
            width: 24px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-section-title {
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 14px 14px 8px;
        }

        .main-content {
            width: 100%;
            margin-left: 280px;
            transition: all 0.3s ease;
        }

        .main-content.full-content {
            margin-left: 0;
        }

        .topbar {
            height: 78px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .menu-toggle {
            width: 44px;
            height: 44px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111827;
            transition: all 0.2s ease;
        }

        .menu-toggle:hover {
            background: #f9fafb;
            border-color: #d4af37;
            color: #b8860b;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #0f172a;
            color: #facc15;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .page-content {
            padding: 28px;
        }

        .sidebar-overlay {
            display: none;
        }

        @media (max-width: 991px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.show-sidebar {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.55);
                z-index: 1040;
            }

            .sidebar-overlay.show-overlay {
                display: block;
            }

            .topbar {
                padding: 0 16px;
            }

            .page-content {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">

        <!-- Overlay móvil -->
        <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
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
                   class="sidebar-link {{ request()->routeIs('metodos-pago.*') ? 'active' : '' }}">
                    <span class="sidebar-icon">💳</span>
                    Métodos de Pago
                </a>

                <a href="{{ route('tipos_identificacion.index') }}"
                   class="sidebar-link {{ request()->routeIs('tipos-identificacion.*') ? 'active' : '' }}">
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

        <!-- Contenido -->
        <main id="mainContent" class="main-content">
            <header class="topbar">
                <button type="button" class="menu-toggle" onclick="toggleSidebar()">
                    ☰
                </button>

                <div class="topbar-user">
                    <div class="text-end d-none d-sm-block">
                        <p class="mb-0 fw-bold text-dark">
                            Administrador
                        </p>
                        <small class="text-muted">
                            Sesión activa
                        </small>
                    </div>

                    <div class="user-avatar">
                        AD
                    </div>
                </div>
            </header>

            <section class="page-content">
                {{ $slot }}
            </section>
        </main>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth <= 991) {
                sidebar.classList.toggle('show-sidebar');
                overlay.classList.toggle('show-overlay');
            } else {
                sidebar.classList.toggle('hidden-sidebar');
                mainContent.classList.toggle('full-content');
            }
        }
    </script>
</body>
</html>