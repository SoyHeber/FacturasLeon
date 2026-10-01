<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'icono',
        'orden',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function opciones()
    {
        return $this->hasMany(Opcion::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
