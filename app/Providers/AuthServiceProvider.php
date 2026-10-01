<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Permite @can('marcas.crear') en Blade y Gate::allows('marcas.crear') en código.
        // Si la habilidad no tiene formato "ruta.accion" se deja pasar a Gates/Policies normales.
        Gate::before(function ($user, string $ability) {
            if (!str_contains($ability, '.')) {
                return null;
            }

            $ruta = substr($ability, 0, strrpos($ability, '.'));
            $accion = substr($ability, strrpos($ability, '.') + 1);

            return $user->tienePermiso($ruta, $accion) ?: null;
        });
    }
}
