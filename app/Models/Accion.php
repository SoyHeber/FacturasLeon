<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accion extends Model
{
    use HasFactory;

    protected $table = 'acciones';

    protected $fillable = [
        'nombre',
        'clave',
        'descripcion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function opciones()
    {
        return $this->belongsToMany(
            Opcion::class,
            'opciones_acciones',
            'accion_id',
            'opcion_id'
        )->withTimestamps();
    }
}
