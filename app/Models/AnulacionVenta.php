<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AnulacionVenta extends Model
{
    protected $table = 'anulaciones_venta';

    protected $fillable = [
        'venta_id', 'documento_fel_id', 'usuario_id', 'tipo', 'estado', 'fecha_anulacion',
        'motivo', 'solicitud', 'confirmacion_raw', 'confirmacion_hash', 'origen_confirmacion',
        'confirmado_por_id', 'referencia_soporte', 'fecha_confirmacion', 'fecha_aplicacion',
        'aplicado_por_id', 'ultimo_error',
    ];

    protected $casts = ['solicitud' => 'array', 'fecha_anulacion' => 'date',
        'fecha_confirmacion' => 'datetime', 'fecha_aplicacion' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw ValidationException::withMessages(['anulacion' => 'El historial de anulación no puede eliminarse.']));
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function documentoFel()
    {
        return $this->belongsTo(DocumentoFel::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function confirmadoPor()
    {
        return $this->belongsTo(User::class, 'confirmado_por_id');
    }

    public function intentos()
    {
        return $this->hasMany(IntentoAnulacionVenta::class);
    }

    public function ultimoIntento()
    {
        return $this->hasOne(IntentoAnulacionVenta::class)->latestOfMany();
    }

    /**
     * Un envío abierto solo admite evidencia manual cuando excedió el plazo de transporte.
     * La expiración habilita revisión; no acredita éxito ni provoca otro envío a Ainnova.
     */
    public function puedeConciliar(): bool
    {
        return $this->tipo === 'FEL' && ($this->estado === 'INCIERTA'
            || ($this->estado === 'EN_PROCESO' && $this->ultimoIntento?->fecha_inicio?->lt(now()->subMinutes(DocumentoFel::minutosIntentoExpirado()))));
    }
}
