<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'inventario_id',
        'produccion_id',
        'detalle_venta_id',
        'tipo_movimiento',
        'cantidad',
        'fecha_movimiento',
        'motivo',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'string',
        'produccion_id' => 'integer',
        'detalle_venta_id' => 'integer',
        'fecha_movimiento' => 'datetime',
        'estado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $movimiento) {
            if ($movimiento->getRawOriginal('produccion_id') !== null || $movimiento->getRawOriginal('detalle_venta_id') !== null
                || $movimiento->isDirty(['produccion_id', 'detalle_venta_id'])) {
                throw ValidationException::withMessages(['movimiento' => 'Los movimientos automáticos no pueden modificarse ni cambiar sus vínculos.']);
            }
        });

        static::deleting(function (self $movimiento) {
            if ($movimiento->getRawOriginal('produccion_id') !== null || $movimiento->getRawOriginal('detalle_venta_id') !== null) {
                throw ValidationException::withMessages(['movimiento' => 'Los movimientos automáticos no pueden eliminarse.']);
            }
        });
    }

    public function inventario()
    {
        return $this->belongsTo(Inventario::class);
    }

    public function produccion()
    {
        return $this->belongsTo(Produccion::class);
    }

    public function detalleVenta()
    {
        return $this->belongsTo(DetalleVenta::class);
    }

    public function esAutomatico(): bool
    {
        return $this->produccion_id !== null || $this->detalle_venta_id !== null;
    }
}
