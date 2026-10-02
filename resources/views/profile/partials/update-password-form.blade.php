<section aria-labelledby="password-title">
    <header class="mb-4">
        <h2 id="password-title" class="h5 fw-bold text-dark mb-1">Cambiar contraseña</h2>
        <p class="text-muted mb-0">Utiliza una contraseña segura para proteger el acceso a tu cuenta.</p>
    </header>

    @if (session('status') === 'password-updated')
        <div class="alert alert-success" role="status">
            La contraseña se actualizó correctamente.
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-md-6 col-xl-4">
                <label for="update_password_current_password" class="form-label fw-bold">Contraseña actual</label>
                <input id="update_password_current_password" name="current_password" type="password"
                    class="form-control rounded-3 @error('current_password', 'updatePassword') is-invalid @enderror"
                    required autocomplete="current-password"
                    @error('current_password', 'updatePassword') aria-invalid="true" aria-describedby="current-password-error" @enderror>

                @error('current_password', 'updatePassword')
                    <div id="current-password-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 col-xl-4">
                <label for="update_password_password" class="form-label fw-bold">Nueva contraseña</label>
                <input id="update_password_password" name="password" type="password"
                    class="form-control rounded-3 @error('password', 'updatePassword') is-invalid @enderror"
                    required autocomplete="new-password"
                    @error('password', 'updatePassword') aria-invalid="true" aria-describedby="new-password-error" @enderror>

                @error('password', 'updatePassword')
                    <div id="new-password-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 col-xl-4">
                <label for="update_password_password_confirmation" class="form-label fw-bold">Confirmar nueva contraseña</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                    class="form-control rounded-3 @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                    required autocomplete="new-password"
                    @error('password_confirmation', 'updatePassword') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>

                @error('password_confirmation', 'updatePassword')
                    <div id="password-confirmation-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn-gold">Actualizar contraseña</button>
        </div>
    </form>
</section>
