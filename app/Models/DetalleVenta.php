<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    use HasFactory;

    protected $table = 'detalles_ventas';

    protected $fillable = [
        'venta_id', 'producto_id', 'numero_linea', 'producto_codigo', 'producto_nombre', 'descripcion',
        'unidad_medida', 'bien_servicio', 'cantidad', 'precio_unitario', 'porcentaje_descuento',
        'tratamiento_tributario', 'tasa_iva', 'importe_bruto', 'importe_descuento', 'importe_exento',
        'importe_neto', 'importe_iva', 'importe_total',
    ];

    protected $casts = [
        'cantidad' => 'string', 'precio_unitario' => 'decimal:10', 'porcentaje_descuento' => 'decimal:4',
        'tasa_iva' => 'decimal:4', 'importe_bruto' => 'decimal:2', 'importe_descuento' => 'decimal:2',
        'importe_exento' => 'decimal:2', 'importe_neto' => 'decimal:2', 'importe_iva' => 'decimal:2', 'importe_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $detalle) {
            $detalle->venta()->firstOrFail()->exigirBorrador();
            if ($detalle->exists && $detalle->isDirty('venta_id')) {
                Venta::findOrFail($detalle->getRawOriginal('venta_id'))->exigirBorrador();
            }
        });
        static::deleting(fn (self $detalle) => $detalle->venta()->firstOrFail()->exigirBorrador());
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class, 'detalle_venta_id');
    }
}
