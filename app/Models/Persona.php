<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $table = 'personas';

    protected $fillable = [
        'cliente_id',
        'nombre1',
        'nombre2',
        'nombre3',
        'apellido1',
        'apellido2',
        'apellido_casada',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}