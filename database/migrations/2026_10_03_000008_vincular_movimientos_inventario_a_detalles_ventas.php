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
        $detalles = $grammar->wrapTable('detalles_ventas');

        DB::statement('ALTER TABLE '.$tabla.'
            ADD COLUMN detalle_venta_id BIGINT UNSIGNED NULL,
            ADD INDEX movimientos_inventario_detalle_venta_id_index (detalle_venta_id),
            ADD CONSTRAINT movimientos_inventario_detalle_venta_id_foreign FOREIGN KEY (detalle_venta_id)
                REFERENCES '.$detalles.' (id) ON DELETE RESTRICT,
            ADD CONSTRAINT movimientos_inventario_venta_tipo_unique UNIQUE (detalle_venta_id, tipo_movimiento),
            ADD CONSTRAINT movimientos_inventario_venta_check CHECK (
                detalle_venta_id IS NULL OR (produccion_id IS NULL AND tipo_movimiento IN (\'ENTRADA\', \'SALIDA\') AND cantidad > 0 AND estado = 1)
            )');
    }

    public function down(): void
    {
        $mariaDb = $this->verificarServidor();
        if (DB::table('movimientos_inventario')->whereNotNull('detalle_venta_id')->exists()) {
            throw new RuntimeException('No se puede retirar el vínculo con Ventas: existen movimientos automáticos.');
        }
        $tabla = DB::connection()->getQueryGrammar()->wrapTable('movimientos_inventario');
        DB::statement('ALTER TABLE '.$tabla.'
            DROP '.($mariaDb ? 'CONSTRAINT' : 'CHECK').' movimientos_inventario_venta_check,
            DROP FOREIGN KEY movimientos_inventario_detalle_venta_id_foreign,
            DROP INDEX movimientos_inventario_venta_tipo_unique,
            DROP INDEX movimientos_inventario_detalle_venta_id_index,
            DROP COLUMN detalle_venta_id');
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
