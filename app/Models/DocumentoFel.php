<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentoFel extends Model
{
    use HasFactory;

    public const ESTADOS = ['PENDIENTE', 'EN_PROCESO', 'CERTIFICADA', 'ERROR', 'INCIERTA', 'ANULADA'];

    public const TIPOS_AINNOVA = ['FACT' => 1];

    public const LONGITUD_REFERENCIA = 20;

    protected $table = 'documentos_fel';

    protected $attributes = ['estado_fel' => 'PENDIENTE', 'p_tipo_doc' => 1, 'p_tipo_respuesta' => 'D'];

    protected $fillable = [
        'venta_id', 'tipo_documento_id', 'ambiente', 'nit_emisor', 'codigo_establecimiento', 'id_maquina',
        'nombre_emisor', 'nombre_comercial', 'afiliacion_iva', 'direccion_emisor', 'codigo_postal_emisor',
        'municipio_emisor', 'departamento_emisor', 'pais_emisor', 'frases', 'fecha_hora_emision',
    ];

    protected $casts = ['p_tipo_doc' => 'integer', 'frases' => 'array', 'fecha_hora_emision' => 'datetime', 'fecha_certificacion' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $documento) {
            $venta = $documento->venta()->firstOrFail();
            $tipo = $documento->tipoDocumento()->firstOrFail();
            if ($tipo->codigo !== 'FACT' || ! $tipo->estado || (int) $venta->tipo_documento_id !== (int) $tipo->id) {
                throw ValidationException::withMessages(['documento_fel' => 'El documento debe corresponder al FACT activo de la venta.']);
            }
            $documento->referencia = strtoupper(Str::random(self::LONGITUD_REFERENCIA));
            $documento->estado_fel = 'PENDIENTE';
            $documento->p_tipo_doc = self::TIPOS_AINNOVA['FACT'];
            $documento->p_tipo_respuesta = 'D';
        });
        static::updating(function (self $documento) {
            if ($documento->isDirty(['referencia', 'venta_id', 'tipo_documento_id', 'p_tipo_doc', 'p_tipo_respuesta'])) {
                throw ValidationException::withMessages(['documento_fel' => 'La referencia y la identidad del documento FEL son inmutables.']);
            }
            if ($documento->isDirty(['xml_solicitud', 'hash_xml'])) {
                if ($documento->getRawOriginal('xml_solicitud') !== null || $documento->getRawOriginal('hash_xml') !== null
                    || $documento->venta()->value('estado_venta') !== 'BORRADOR') {
                    throw ValidationException::withMessages(['documento_fel' => 'El XML solicitado y su hash son inmutables.']);
                }
                if (! is_string($documento->xml_solicitud) || $documento->xml_solicitud === ''
                    || ! is_string($documento->hash_xml) || ! hash_equals(hash('sha256', $documento->xml_solicitud), $documento->hash_xml)) {
                    throw ValidationException::withMessages(['documento_fel' => 'El hash SHA-256 debe corresponder exactamente al XML solicitado.']);
                }
            }
        });
        static::deleting(function () {
            throw ValidationException::withMessages(['documento_fel' => 'El documento FEL no puede eliminarse ni reemplazarse.']);
        });
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function intentos()
    {
        return $this->hasMany(IntentoFel::class);
    }

    public function ultimoIntento()
    {
        return $this->hasOne(IntentoFel::class)->latestOfMany();
    }

    public function ultimoIntentoConRespuesta()
    {
        return $this->hasOne(IntentoFel::class)->ofMany(['id' => 'max'], fn ($query) => $query->whereNotNull('respuesta_raw')->where('respuesta_raw', '<>', ''));
    }

    public static function minutosIntentoExpirado(): int
    {
        $minutos = filter_var(config('fel.ainnova.intento_expirado_minutos', 5), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1440]]);
        // La recuperación siempre espera más que los tiempos máximos de transporte.
        $transporte = (int) config('fel.ainnova.timeout', 60) + (int) config('fel.ainnova.connect_timeout', 10) + 60;

        return max($minutos === false ? 5 : $minutos, intdiv($transporte + 59, 60));
    }

    public function intentoExpirado(IntentoFel $intento): bool
    {
        return $this->estado_fel === 'EN_PROCESO' && $intento->resultado === 'EN_PROCESO'
            && $intento->fecha_fin === null && $intento->fecha_inicio !== null
            && $intento->fecha_inicio->lt(now()->subMinutes(self::minutosIntentoExpirado()));
    }

    public function puedeRecuperarCertificacion(): bool
    {
        return $this->ultimoIntento !== null && $this->intentoExpirado($this->ultimoIntento);
    }
}
