<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
    use HasFactory;

    public const LEGADA = 'LEGADA';

    public const BORRADOR = 'BORRADOR';

    public const CONFIRMADA = 'CONFIRMADA';

    public const ANULADA = 'ANULADA';

    protected $table = 'producciones';

    protected $attributes = [
        'estado_produccion' => self::BORRADOR,
        'inventario_aplicado' => false,
    ];

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
        'inventario_aplicado' => 'boolean',
        'producto_terminado_aplicado' => 'boolean',
        'fecha_confirmacion' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'confirmado_por_id' => 'integer',
        'anulado_por_id' => 'integer',
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

    public function confirmadoPor()
    {
        return $this->belongsTo(User::class, 'confirmado_por_id');
    }

    public function anuladoPor()
    {
        return $this->belongsTo(User::class, 'anulado_por_id');
    }

    public function consumos()
    {
        return $this->hasMany(ConsumoProduccion::class);
    }

    public function scopeParaAutomatizacion(Builder $query): Builder
    {
        return $query->where('estado_produccion', self::BORRADOR)
            ->where('estado', true)
            ->where('inventario_aplicado', false);
    }

    public function esEditable(): bool
    {
        return $this->estado_produccion === self::BORRADOR && ! $this->inventario_aplicado;
    }

    public function movimientosInventarioCompra()
    {
        return $this->hasMany(MovimientoInventarioCompra::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class, 'produccion_id');
    }
}
