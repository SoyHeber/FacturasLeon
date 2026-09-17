<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    use HasFactory;

    protected $table = 'detalles_compra';

    protected $fillable = [
        'compra_id',
        'inventario_compra_id',
        'cantidad',
        'precio_unitario',
        'porcentaje_descuento',
        'importe_bruto',
        'importe_descuento',
        'importe_exento',
        'importe_otros',
        'importe_neto',
        'importe_iva',
        'importe_total',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:6',
        'porcentaje_descuento' => 'decimal:4',

        'importe_bruto' => 'decimal:2',
        'importe_descuento' => 'decimal:2',
        'importe_exento' => 'decimal:2',
        'importe_otros' => 'decimal:2',
        'importe_neto' => 'decimal:2',
        'importe_iva' => 'decimal:2',
        'importe_total' => 'decimal:2',

        'estado' => 'boolean',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function inventarioCompra()
    {
        return $this->belongsTo(InventarioCompra::class);
    }

    public function movimientosInventarioCompra()
    {
        return $this->hasMany(MovimientoInventarioCompra::class);
    }
}
