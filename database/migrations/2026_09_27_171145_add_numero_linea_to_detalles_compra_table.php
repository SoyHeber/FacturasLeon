<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Agregar numero_linea temporalmente como nullable
        |--------------------------------------------------------------------------
        |
        | Esto permite que la migración funcione aunque ya existan
        | detalles de compra registrados.
        |
        */
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->unsignedInteger('numero_linea')
                ->nullable()
                ->after('compra_id');
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Asignar número de línea a registros existentes
        |--------------------------------------------------------------------------
        */
        $compras = DB::table('detalles_compra')
            ->select('compra_id')
            ->distinct()
            ->pluck('compra_id');

        foreach ($compras as $compraId) {

            $detalles = DB::table('detalles_compra')
                ->where('compra_id', $compraId)
                ->orderBy('id')
                ->get();

            $numeroLinea = 1;

            foreach ($detalles as $detalle) {

                DB::table('detalles_compra')
                    ->where('id', $detalle->id)
                    ->update([
                        'numero_linea' => $numeroLinea,
                    ]);

                $numeroLinea++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Convertir numero_linea en obligatorio
        |--------------------------------------------------------------------------
        */
        DB::statement(
            'ALTER TABLE detalles_compra
             MODIFY numero_linea INT UNSIGNED NOT NULL'
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Evitar líneas duplicadas dentro de una misma compra
        |--------------------------------------------------------------------------
        */
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->unique(
                ['compra_id', 'numero_linea'],
                'detalles_compra_compra_linea_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('detalles_compra', function (Blueprint $table) {

            $table->dropUnique(
                'detalles_compra_compra_linea_unique'
            );

            $table->dropColumn('numero_linea');
        });
    }
};
