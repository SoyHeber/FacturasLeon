<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opcion extends Model
{
    use HasFactory;

    public const REPORTES = [
        'reportes_ventas' => ['Ventas', '🧾'],
        'reportes_compras' => ['Compras', '🛒'],
        'reportes_dte' => ['DTE por día', '📋'],
        'reportes_existencias' => ['Existencias', '📦'],
        'reportes_movimientos' => ['Movimientos de inventario', '🔄'],
        'reportes_produccion' => ['Producción', '🛠️'],
        'reportes_consumos' => ['Consumo de materiales', '🧱'],
        'reportes_catalogos' => ['Catálogos y recetas', '📚'],
    ];

    public const ACCIONES_POR_RUTA = [
        'compras' => ['ver', 'crear', 'eliminar'],
        'detalles_compra' => ['ver'],
        'reportes_ventas' => ['ver'],
        'reportes_compras' => ['ver'],
        'reportes_dte' => ['ver'],
        'reportes_existencias' => ['ver'],
        'reportes_movimientos' => ['ver'],
        'reportes_produccion' => ['ver'],
        'reportes_consumos' => ['ver'],
        'reportes_catalogos' => ['ver'],
    ];

    protected $table = 'opciones';

    protected $fillable = [
        'modulo_id',
        'nombre',
        'descripcion',
        'ruta',
        'icono',
        'orden',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class);
    }

    // Acciones que admite la opción
    public function acciones()
    {
        return $this->belongsToMany(
            Accion::class,
            'opciones_acciones',
            'opcion_id',
            'accion_id'
        )->withTimestamps();
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
