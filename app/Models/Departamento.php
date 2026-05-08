<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';

    protected $fillable = [
        'pais_id',
        'nombre',
        'codigo',
        'estado',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class);
    }

    /*public function municipios()
    {
        return $this->hasMany(Municipio::class);
    }*/
}