<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'user_id',
        'tipo_documento_id',
        'serie',
        'numero',
        'numero_autorizacion',
        'fecha_emision',
        'fecha_certificacion',
        'moneda',
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
        'numero' => 'integer',

        'fecha_emision' => 'datetime',
        'fecha_certificacion' => 'datetime',

        'importe_bruto' => 'decimal:2',
        'importe_descuento' => 'decimal:2',
        'importe_exento' => 'decimal:2',
        'importe_otros' => 'decimal:2',
        'importe_neto' => 'decimal:2',
        'importe_iva' => 'decimal:2',
        'importe_total' => 'decimal:2',

        'estado' => 'boolean',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
