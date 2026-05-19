<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'tipo_identificacion_id',
        'numero_identificacion',
        'direccion_id',
        'telefono',
        'correo',
        'estado',
    ];

    public function tipoIdentificacion()
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function direccion()
    {
        return $this->belongsTo(Direccion::class);
    }

/*     public function compras()
    {
        return $this->hasMany(Compra::class);
    } */
}
