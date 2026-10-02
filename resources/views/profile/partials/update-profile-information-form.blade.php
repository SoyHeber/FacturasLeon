<section aria-labelledby="profile-information-title">
    <header class="mb-4">
        <h2 id="profile-information-title" class="h5 fw-bold text-dark mb-1">Información del perfil</h2>
        <p class="text-muted mb-0">Actualiza tu nombre y correo electrónico.</p>
    </header>

    @if (session('status') === 'profile-updated')
        <div class="alert alert-success" role="status">
            La información del perfil se actualizó correctamente.
        </div>
    @endif

    <form id="send-verification" method="POST" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <div class="row g-4">
            <div class="col-md-6">
                <label for="name" class="form-label fw-bold">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                    class="form-control rounded-3 @error('name') is-invalid @enderror"
                    maxlength="255" required autofocus autocomplete="name"
                    @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>

                @error('name')
                    <div id="name-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label fw-bold">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                    class="form-control rounded-3 @error('email') is-invalid @enderror"
                    maxlength="255" required autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>

                @error('email')
                    <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        <p class="mb-3">Tu correo electrónico aún no está verificado.</p>
                        <button type="submit" form="send-verification"
                            class="btn btn-outline-secondary rounded-3 fw-bold">
                            Reenviar correo de verificación
                        </button>
                    </div>

                    @if (session('status') === 'verification-link-sent')
                        <div class="alert alert-success mt-3 mb-0" role="status">
                            Se envió un nuevo enlace de verificación a tu correo electrónico.
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn-gold">Guardar cambios</button>
        </div>
    </form>
</section>
