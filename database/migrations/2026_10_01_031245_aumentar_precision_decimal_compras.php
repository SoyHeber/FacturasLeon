<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DETALLES DE COMPRA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY cantidad DECIMAL(20,10) NOT NULL
        ");

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY precio_unitario DECIMAL(20,10) NOT NULL
        ");

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY porcentaje_descuento DECIMAL(20,10) NOT NULL DEFAULT 0
        ");


        /*
        |--------------------------------------------------------------------------
        | INVENTARIO DE MATERIA PRIMA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY cantidad DECIMAL(20,10) NOT NULL DEFAULT 0
        ");

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY stock_minimo DECIMAL(20,10) NOT NULL
        ");

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY stock_maximo DECIMAL(20,10) NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | RECETA / MATERIALES DEL PRODUCTO
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE materiales_producto
            MODIFY cantidad_requerida DECIMAL(20,10) NOT NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | MOVIMIENTOS DE INVENTARIO DE COMPRA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE movimientos_inventario_compra
            MODIFY cantidad DECIMAL(20,10) NOT NULL
        ");
    }


    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | RESTAURAR DETALLES DE COMPRA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY cantidad DECIMAL(14,4) NOT NULL
        ");

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY precio_unitario DECIMAL(14,6) NOT NULL
        ");

        DB::statement("
            ALTER TABLE detalles_compra
            MODIFY porcentaje_descuento DECIMAL(7,4) NOT NULL DEFAULT 0
        ");


        /*
        |--------------------------------------------------------------------------
        | RESTAURAR INVENTARIO DE COMPRA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY cantidad DECIMAL(14,4) NOT NULL DEFAULT 0
        ");

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY stock_minimo DECIMAL(14,4) NOT NULL
        ");

        DB::statement("
            ALTER TABLE inventarios_compra
            MODIFY stock_maximo DECIMAL(14,4) NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | RESTAURAR MATERIALES DEL PRODUCTO
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE materiales_producto
            MODIFY cantidad_requerida DECIMAL(14,4) NOT NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | RESTAURAR MOVIMIENTOS
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE movimientos_inventario_compra
            MODIFY cantidad DECIMAL(14,4) NOT NULL
        ");
    }
};