<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntentoFel extends Model
{
    use HasFactory;

    protected $table = 'intentos_fel';

    protected $fillable = [
        'documento_fel_id', 'resultado', 'fecha_inicio', 'fecha_fin', 'respuesta_raw',
        'codigo_error', 'mensaje_error', 'error_tecnico', 'usuario_id',
    ];

    protected $casts = ['usuario_id' => 'integer', 'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];

    public function documentoFel()
    {
        return $this->belongsTo(DocumentoFel::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
