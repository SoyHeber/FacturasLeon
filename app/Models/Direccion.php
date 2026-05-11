<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    use HasFactory;

    protected $table = 'direcciones';

    protected $fillable = [
        'municipio_id',
        'direccion',
        'referencia',
        'codigo_postal',
        'estado',
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }
}
