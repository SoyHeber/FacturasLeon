<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
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

    public function persona()
    {
        return $this->hasOne(Persona::class);
    }

    public function sociedad()
    {
        return $this->hasOne(Sociedad::class);
    }

    /*     public function facturas()
    {
        return $this->hasMany(Factura::class);
    } */
}
