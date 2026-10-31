<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separa el estado de la receta de la pertenencia de cada componente.
     * Las recetas anteriores sin componentes vigentes permanecen inactivas; las demás
     * conservan su disponibilidad. No altera líneas, consumos ni existencias.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('receta_activa')->default(true);
        });

        DB::table('productos')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('materiales_producto')
                    ->whereColumn('materiales_producto.producto_id', 'productos.id');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('materiales_producto')
                    ->whereColumn('materiales_producto.producto_id', 'productos.id')->where('estado', true);
            })->update(['receta_activa' => false]);
    }

    /** Impide perder el bloqueo de recetas inactivas al volver al esquema anterior. */
    public function down(): void
    {
        if (DB::table('productos')->where('receta_activa', false)->exists()) {
            throw new RuntimeException('Hay recetas inactivas. Revise su disponibilidad antes de retirar el estado general.');
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('receta_activa');
        });
    }
};
