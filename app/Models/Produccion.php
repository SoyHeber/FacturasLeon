<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
    use HasFactory;

    protected $table = 'producciones';

    protected $fillable = [
        'producto_id',
        'user_id',
        'cantidad',
        'fecha_produccion',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'fecha_produccion' => 'datetime',
        'estado' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientosInventarioCompra()
    {
        return $this->hasMany(MovimientoInventarioCompra::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
