<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventarioCompra extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario_compra';

    protected $fillable = [
        'inventario_compra_id',
        'detalle_compra_id',
        'produccion_id',
        'consumo_produccion_id',
        'tipo_movimiento',
        'cantidad',
        'fecha_movimiento',
        'motivo',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:10',
        'fecha_movimiento' => 'datetime',
        'estado' => 'boolean',
    ];

    public function inventarioCompra()
    {
        return $this->belongsTo(InventarioCompra::class);
    }

    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class);
    }

    public function produccion()
    {
        return $this->belongsTo(Produccion::class);
    }

    public function consumoProduccion()
    {
        return $this->belongsTo(ConsumoProduccion::class);
    }
}
