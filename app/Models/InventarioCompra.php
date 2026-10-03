<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventarioCompra extends Model
{
    use HasFactory;

    protected $table = 'inventarios_compra';

    protected $fillable = [
        'nombre',
        'descripcion',
        'unidad_medida',
        'stock_minimo',
        'stock_maximo',
        'ubicacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:10',
        'stock_minimo' => 'decimal:10',
        'stock_maximo' => 'decimal:10',
        'estado' => 'boolean',
    ];

    public function detallesCompra()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function materialesProducto()
    {
        return $this->hasMany(MaterialProducto::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventarioCompra::class);
    }
}
