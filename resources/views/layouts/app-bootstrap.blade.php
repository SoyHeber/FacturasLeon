<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'FacturasLeon') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Figtree', Arial, sans-serif;
            background: #f3f4f6;
            overflow-x: hidden;
        }

        .app-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
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
            height: calc(100vh - 78px);
            overflow-y: auto;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.18);
            border-radius: 20px;
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

        /* Main */
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
            font-size: 20px;
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

        /* Estilos reutilizables para módulos */
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }

        .module-title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
            margin: 0;
        }

        .module-subtitle {
            color: #6b7280;
            margin: 4px 0 0;
            font-size: 14px;
        }

        .module-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .module-card-body {
            padding: 22px;
        }

        .btn-gold {
            background: #d4af37;
            color: #111827;
            border: none;
            border-radius: 12px;
            padding: 10px 16px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-gold:hover {
            background: #b8860b;
            color: #111827;
            transform: translateY(-1px);
        }

        .btn-dark-soft {
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-dark-soft:hover {
            background: #1e293b;
            color: #ffffff;
        }

        .table-modern {
            margin-bottom: 0;
        }

        .table-modern thead th {
            background: #0f172a;
            color: #ffffff;
            font-size: 13px;
            padding: 14px;
            border: none;
        }

        .table-modern tbody td {
            padding: 14px;
            vertical-align: middle;
            border-color: #e5e7eb;
            font-size: 14px;
        }

        .table-modern tbody tr:hover {
            background: #f9fafb;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-inactive {
            background: #e5e7eb;
            color: #374151;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
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

            .module-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">

        <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

        @include('layouts.navigation')

        <main id="mainContent" class="main-content">
            <header class="topbar">
                <button type="button" class="menu-toggle" onclick="toggleSidebar()">
                    ☰
                </button>

                @auth
                    <div class="topbar-user">
                        <div class="text-end d-none d-sm-block">
                            <p class="mb-0 fw-bold text-dark">
                                {{ Auth::user()->name }}
                            </p>
                            <small class="text-muted">
                                {{ Auth::user()->email }}
                            </small>
                        </div>

                        <div class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </div>
                @endauth
            </header>

            @isset($header)
                <div class="bg-white border-bottom">
                    <div class="page-content py-3">
                        {{ $header }}
                    </div>
                </div>
            @endisset

            <section class="page-content">
                {{ $slot ?? '' }}
                @yield('content')
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
