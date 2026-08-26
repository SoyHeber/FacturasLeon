<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'inventario_id',
        'tipo_movimiento',
        'cantidad',
        'fecha_movimiento',
        'motivo',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'fecha_movimiento' => 'datetime',
        'estado' => 'boolean',
    ];

    public function inventario()
    {
        return $this->belongsTo(Inventario::class);
    }
}
