<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class IntentoAnulacionVenta extends Model
{
    protected $table = 'intentos_anulacion_venta';

    protected $fillable = ['anulacion_venta_id', 'usuario_id', 'solicitud', 'resultado',
        'fecha_inicio', 'fecha_fin', 'respuesta_raw', 'mensaje'];

    protected $casts = ['solicitud' => 'array', 'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw ValidationException::withMessages(['anulacion' => 'Los intentos de anulación no pueden eliminarse.']));
    }

    public function anulacionVenta()
    {
        return $this->belongsTo(AnulacionVenta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
