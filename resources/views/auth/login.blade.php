<x-guest-layout>
    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #111827, #1f2937, #374151);
            min-height: 100vh;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .login-card {
            width: 100%;
            max-width: 1050px;
            background: #ffffff;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
        }

        .login-brand {
            background: #0f172a;
            color: #ffffff;
            padding: 50px;
            position: relative;
            overflow: hidden;
            min-height: 620px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .login-brand::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.18);
            top: -80px;
            right: -80px;
            filter: blur(10px);
        }

        .login-brand::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(184, 134, 11, 0.18);
            bottom: -80px;
            left: -80px;
            filter: blur(10px);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: #d4af37;
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.35);
        }

        .brand-title {
            font-size: 40px;
            font-weight: 800;
            line-height: 1.15;
            margin-top: 45px;
            margin-bottom: 20px;
        }

        .brand-description {
            color: #d1d5db;
            font-size: 16px;
            line-height: 1.7;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 18px;
            padding: 18px;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            color: #facc15;
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .stat-label {
            color: #d1d5db;
            font-size: 13px;
            margin: 0;
        }

        .login-form-area {
            padding: 55px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-size: 32px;
            font-weight: 800;
            color: #111827;
        }

        .form-subtitle {
            color: #6b7280;
            font-size: 15px;
        }

        .form-label {
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 16px;
            padding: 13px 16px;
            border: 1px solid #d1d5db;
            font-size: 15px;
        }

        .form-control:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.20);
        }

        .input-group-text {
            border-radius: 16px 0 0 16px;
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .input-group .form-control {
            border-radius: 0 16px 16px 0;
        }

        .btn-gold {
            background: #d4af37;
            color: #111827;
            border: none;
            border-radius: 16px;
            padding: 13px 18px;
            font-weight: 800;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.35);
        }

        .btn-gold:hover {
            background: #b8860b;
            color: #111827;
            transform: translateY(-1px);
        }

        .forgot-link {
            color: #b8860b;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .forgot-link:hover {
            color: #8b6508;
            text-decoration: underline;
        }

        .mobile-brand {
            display: none;
        }

        @media (max-width: 991px) {
            .login-brand {
                display: none;
            }

            .login-form-area {
                padding: 35px 25px;
            }

            .mobile-brand {
                display: flex;
            }

            .login-card {
                max-width: 520px;
            }
        }
    </style>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="row g-0">

                <!-- Lado izquierdo -->
                <div class="col-lg-6">
                    <div class="login-brand">
                        <div class="brand-content">
                            <div class="d-flex align-items-center gap-3">
                                <div class="brand-logo">JL</div>
                                <div>
                                    <h1 class="h4 fw-bold mb-0">Joyería de León</h1>
                                    <p class="text-secondary mb-0">Sistema administrativo</p>
                                </div>
                            </div>

                            <h2 class="brand-title">
                                Gestiona tu joyería con elegancia y control.
                            </h2>

                            <p class="brand-description">
                                Administra facturación, ventas e inventario desde una plataforma segura,
                                moderna y diseñada para optimizar tus procesos internos.
                            </p>
                        </div>

                        <div class="brand-content">
                            <div class="row g-3">
                                <div class="col-4">
                                    <div class="stat-box">
                                        <p class="stat-number">100%</p>
                                        <p class="stat-label">Control</p>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div class="stat-box">
                                        <p class="stat-number">24/7</p>
                                        <p class="stat-label">Acceso</p>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div class="stat-box">
                                        <p class="stat-number">JL</p>
                                        <p class="stat-label">Gestión</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="col-lg-6">
                    <div class="login-form-area">

                        <div class="mobile-brand align-items-center gap-3 mb-4">
                            <div class="brand-logo">JL</div>
                            <div>
                                <h1 class="h5 fw-bold mb-0 text-dark">Joyería de León</h1>
                                <p class="text-muted mb-0">Sistema administrativo</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h2 class="form-title mb-2">Bienvenido de nuevo</h2>
                            <p class="form-subtitle mb-0">
                                Ingresa tus credenciales para continuar administrando el sistema.
                            </p>
                        </div>

                        <x-auth-session-status class="mb-4" :status="session('status')" />

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <!-- Email -->
                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    Correo electrónico
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        @
                                    </span>

                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="correo@ejemplo.com"
                                        class="form-control @error('email') is-invalid @enderror"
                                    >
                                </div>

                                @error('email')
                                    <div class="text-danger small mt-2">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    Contraseña
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        🔒
                                    </span>

                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Ingresa tu contraseña"
                                        class="form-control @error('password') is-invalid @enderror"
                                    >

                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        onclick="togglePassword()"
                                        style="border-radius: 0 16px 16px 0;"
                                    >
                                        👁
                                    </button>
                                </div>

                                @error('password')
                                    <div class="text-danger small mt-2">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <!-- Recordarme -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input
                                        id="remember_me"
                                        type="checkbox"
                                        name="remember"
                                        class="form-check-input"
                                    >

                                    <label class="form-check-label text-muted" for="remember_me">
                                        Recordarme
                                    </label>
                                </div>

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="forgot-link">
                                        ¿Olvidaste tu contraseña?
                                    </a>
                                @endif
                            </div>

                            <button type="submit" class="btn btn-gold w-100">
                                Iniciar sesión
                            </button>
                        </form>

                        <p class="text-center text-muted small mt-5 mb-0">
                            © {{ date('Y') }} Joyería de León. Sistema de gestión interna.
                        </p>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');

            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        }
    </script>
</x-guest-layout>