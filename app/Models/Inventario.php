<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    use HasFactory;

    protected $table = 'inventarios';

    protected $attributes = [
        'cantidad' => '0',
    ];

    protected $fillable = [
        'producto_id',
        'stock_minimo',
        'stock_maximo',
        'ubicacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'string',
        'stock_minimo' => 'integer',
        'stock_maximo' => 'integer',
        'estado' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
