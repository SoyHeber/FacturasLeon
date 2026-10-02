@extends('layouts.app-bootstrap')

@section('content')
    <div class="module-header">
        <div>
            <h1 class="module-title">Cuenta</h1>
            <p class="module-subtitle">
                Actualiza tu información personal y la contraseña de acceso al sistema.
            </p>
        </div>
    </div>

    <div class="module-card mb-4">
        <div class="module-card-body">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="module-card">
        <div class="module-card-body">
            @include('profile.partials.update-password-form')
        </div>
    </div>
@endsection
