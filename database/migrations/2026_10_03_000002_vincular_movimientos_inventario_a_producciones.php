<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarServidor();
        $grammar = DB::connection()->getQueryGrammar();
        $tabla = $grammar->wrapTable('movimientos_inventario');
        $producciones = $grammar->wrapTable('producciones');

        DB::statement('ALTER TABLE '.$tabla.'
            ADD COLUMN produccion_id BIGINT UNSIGNED NULL,
            ADD INDEX movimientos_inventario_produccion_id_index (produccion_id),
            ADD CONSTRAINT movimientos_inventario_produccion_id_foreign FOREIGN KEY (produccion_id)
                REFERENCES '.$producciones.' (id) ON DELETE RESTRICT,
            ADD CONSTRAINT movimientos_inventario_produccion_tipo_unique UNIQUE (produccion_id, tipo_movimiento),
            ADD CONSTRAINT movimientos_inventario_produccion_check CHECK (
                produccion_id IS NULL OR (tipo_movimiento IN (\'ENTRADA\', \'SALIDA\') AND cantidad > 0 AND estado = 1)
            )');
    }

    public function down(): void
    {
        $mariaDb = $this->verificarServidor();
        if (DB::table('movimientos_inventario')->whereNotNull('produccion_id')->exists()) {
            throw new RuntimeException('No se puede retirar el vínculo con Producción: existen movimientos automáticos.');
        }
        $tabla = DB::connection()->getQueryGrammar()->wrapTable('movimientos_inventario');

        DB::statement('ALTER TABLE '.$tabla.'
            DROP '.($mariaDb ? 'CONSTRAINT' : 'CHECK').' movimientos_inventario_produccion_check,
            DROP FOREIGN KEY movimientos_inventario_produccion_id_foreign,
            DROP INDEX movimientos_inventario_produccion_tipo_unique,
            DROP INDEX movimientos_inventario_produccion_id_index,
            DROP COLUMN produccion_id');
    }

    private function verificarServidor(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Los vínculos y restricciones de inventario requieren MySQL.');
        }
        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
        $mariaDb = stripos($version, 'MariaDB') !== false;
        preg_match('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+)/', $version, $coincidencia);
        if (! isset($coincidencia[1]) || version_compare($coincidencia[1], $mariaDb ? '10.2.1' : '8.0.16', '<')) {
            throw new RuntimeException('Se requiere MySQL 8.0.16+ o MariaDB 10.2.1+ para garantizar el CHECK de movimientos automáticos.');
        }

        return $mariaDb;
    }
};
