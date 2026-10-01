<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(
            User::class,
            'roles_usuarios',
            'rol_id',
            'user_id'
        )->withTimestamps();
    }

    // Permisos del rol: una fila por cada opción + acción
    public function opciones()
    {
        return $this->belongsToMany(
            Opcion::class,
            'roles_opciones_acciones',
            'rol_id',
            'opcion_id'
        )->withPivot('accion_id')->withTimestamps();
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
