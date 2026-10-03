<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoProduccion extends Model
{
    protected $table = 'consumos_produccion';

    protected $fillable = [
        'produccion_id',
        'inventario_compra_id',
        'material_producto_id',
        'nombre_material',
        'unidad_medida',
        'cantidad_requerida',
        'cantidad_producida',
        'cantidad_consumida',
    ];

    protected $casts = [
        'cantidad_requerida' => 'decimal:10',
        'cantidad_producida' => 'integer',
        'cantidad_consumida' => 'decimal:10',
    ];

    public function produccion()
    {
        return $this->belongsTo(Produccion::class);
    }

    public function inventarioCompra()
    {
        return $this->belongsTo(InventarioCompra::class);
    }

    public function materialProducto()
    {
        return $this->belongsTo(MaterialProducto::class);
    }

    public function movimientosInventarioCompra()
    {
        return $this->hasMany(MovimientoInventarioCompra::class);
    }
}
