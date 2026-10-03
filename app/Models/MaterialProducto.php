<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialProducto extends Model
{
    use HasFactory;

    protected $table = 'materiales_producto';

    protected $fillable = [
        'producto_id',
        'inventario_compra_id',
        'cantidad_requerida',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad_requerida' => 'decimal:10',
        'estado' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function inventarioCompra()
    {
        return $this->belongsTo(InventarioCompra::class);
    }
}
