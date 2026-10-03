<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Venta extends Model
{
    use HasFactory;

    public const ESTADOS = ['BORRADOR', 'CONFIRMADA', 'ANULADA'];

    protected $table = 'ventas';

    protected $attributes = ['estado_venta' => 'BORRADOR', 'moneda' => 'GTQ'];

    protected $fillable = [
        'cliente_id', 'tipo_documento_id', 'metodo_pago_id', 'fecha', 'moneda', 'observacion',
        'receptor_identificacion', 'receptor_tipo_identificacion_codigo', 'receptor_tipo_identificacion_nombre',
        'receptor_nombre', 'receptor_direccion', 'receptor_codigo_postal',
        'receptor_municipio', 'receptor_municipio_codigo', 'receptor_departamento', 'receptor_departamento_codigo',
        'receptor_pais', 'receptor_pais_codigo', 'receptor_correo',
        'importe_bruto', 'importe_descuento', 'importe_exento', 'importe_neto', 'importe_iva', 'importe_total',
        'usuario_creador_id', 'usuario_modificador_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe_bruto' => 'decimal:2', 'importe_descuento' => 'decimal:2', 'importe_exento' => 'decimal:2',
        'importe_neto' => 'decimal:2', 'importe_iva' => 'decimal:2', 'importe_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $venta) {
            $venta->estado_venta = 'BORRADOR';
        });
        static::updating(function (self $venta) {
            $venta->exigirBorrador();
            if ($venta->isDirty(['estado_venta', 'usuario_creador_id'])) {
                throw ValidationException::withMessages(['venta' => 'El estado y el creador no pueden modificarse en este flujo.']);
            }
        });
        static::deleting(function () {
            throw ValidationException::withMessages(['venta' => 'Las ventas no pueden eliminarse.']);
        });
    }

    public function exigirBorrador(): void
    {
        if ($this->getRawOriginal('estado_venta') !== 'BORRADOR') {
            throw ValidationException::withMessages(['venta' => 'Solo se pueden editar ventas BORRADOR.']);
        }
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class)->orderBy('numero_linea');
    }

    public function documentoFel()
    {
        return $this->hasOne(DocumentoFel::class);
    }

    public function usuarioCreador()
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function usuarioModificador()
    {
        return $this->belongsTo(User::class, 'usuario_modificador_id');
    }
}
