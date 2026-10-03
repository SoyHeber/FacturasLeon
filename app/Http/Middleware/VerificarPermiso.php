<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermiso
{
    /**
     * Método de la ruta => clave de la acción requerida.
     * cambiar-estado equivale a eliminar porque el sistema inactiva en lugar de borrar.
     */
    public const ACCIONES_POR_METODO = [
        'index' => 'ver',
        'show' => 'ver',
        'create' => 'crear',
        'store' => 'crear',
        'edit' => 'modificar',
        'update' => 'modificar',
        'confirmar' => 'modificar',
        'anular' => 'eliminar',
        'permisos' => 'modificar',
        'guardar-permisos' => 'modificar',
        'destroy' => 'eliminar',
        'cambiar-estado' => 'eliminar',
    ];

    /**
     * Traduce el nombre de ruta "prefijo.metodo" a opción + acción
     * y verifica que el usuario tenga ese permiso en alguno de sus roles.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nombreRuta = $request->route()?->getName();

        if (! $nombreRuta || ! str_contains($nombreRuta, '.')) {
            abort(403, 'Ruta sin permiso configurado.');
        }

        $ruta = substr($nombreRuta, 0, strrpos($nombreRuta, '.'));
        $metodo = substr($nombreRuta, strrpos($nombreRuta, '.') + 1);

        $accion = self::ACCIONES_POR_METODO[$metodo] ?? null;

        if (! $accion || ! $request->user()?->tienePermiso($ruta, $accion)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
