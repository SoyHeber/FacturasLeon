<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'boolean',
    ];

    // Cache por petición para no repetir consultas en middleware, Gate y menú
    protected ?array $permisosCache = null;

    protected ?Collection $menuCache = null;

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'roles_usuarios',
            'user_id',
            'rol_id'
        )->withTimestamps();
    }

    /**
     * Permisos efectivos del usuario como "ruta.clave" (ej: marcas.crear).
     * Suma de todos sus roles activos; ignora módulos, opciones y acciones inactivos.
     */
    public function permisos(): array
    {
        if ($this->permisosCache !== null) {
            return $this->permisosCache;
        }

        $this->permisosCache = DB::table('roles_opciones_acciones as roa')
            ->join('roles_usuarios as ru', 'ru.rol_id', '=', 'roa.rol_id')
            ->join('roles as r', 'r.id', '=', 'roa.rol_id')
            ->join('opciones as o', 'o.id', '=', 'roa.opcion_id')
            ->join('modulos as m', 'm.id', '=', 'o.modulo_id')
            ->join('acciones as a', 'a.id', '=', 'roa.accion_id')
            ->where('ru.user_id', $this->id)
            ->where('r.estado', true)
            ->where('o.estado', true)
            ->where('m.estado', true)
            ->where('a.estado', true)
            ->select('o.ruta', 'a.clave')
            ->distinct()
            ->get()
            ->map(fn ($permiso) => $permiso->ruta . '.' . $permiso->clave)
            ->all();

        return $this->permisosCache;
    }

    public function tienePermiso(string $ruta, string $accion): bool
    {
        return in_array($ruta . '.' . $accion, $this->permisos(), true);
    }

    /**
     * Módulos con las opciones que el usuario puede ver, para el menú lateral.
     */
    public function menu(): Collection
    {
        if ($this->menuCache !== null) {
            return $this->menuCache;
        }

        $this->menuCache = Modulo::with(['opciones' => function ($query) {
            $query->where('estado', true)
                ->orderBy('orden')
                ->orderBy('nombre');
        }])
            ->where('estado', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->each(function ($modulo) {
                $modulo->setRelation(
                    'opciones',
                    $modulo->opciones->filter(
                        fn ($opcion) => $this->tienePermiso($opcion->ruta, 'ver')
                    )->values()
                );
            })
            ->filter(fn ($modulo) => $modulo->opciones->isNotEmpty())
            ->values();

        return $this->menuCache;
    }
}
