<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarMySql();
        $grammar = DB::connection()->getQueryGrammar();
        $tabla = $grammar->wrapTable('producciones');

        // Las filas existentes quedan como históricas sin aplicar inventario.
        DB::statement('ALTER TABLE '.$tabla."
            ADD COLUMN estado_produccion ENUM('LEGADA', 'BORRADOR', 'CONFIRMADA', 'ANULADA') NOT NULL DEFAULT 'LEGADA',
            ADD COLUMN inventario_aplicado BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN fecha_confirmacion DATETIME NULL,
            ADD COLUMN confirmado_por_id BIGINT UNSIGNED NULL,
            ADD COLUMN fecha_anulacion DATETIME NULL,
            ADD COLUMN anulado_por_id BIGINT UNSIGNED NULL,
            ADD INDEX producciones_estado_produccion_index (estado_produccion),
            ADD INDEX producciones_confirmado_por_id_index (confirmado_por_id),
            ADD INDEX producciones_anulado_por_id_index (anulado_por_id),
            ADD CONSTRAINT producciones_confirmado_por_id_foreign FOREIGN KEY (confirmado_por_id)
                REFERENCES ".$grammar->wrapTable('users').' (id) ON DELETE RESTRICT,
            ADD CONSTRAINT producciones_anulado_por_id_foreign FOREIGN KEY (anulado_por_id)
                REFERENCES '.$grammar->wrapTable('users').' (id) ON DELETE RESTRICT');

        // Cambiar el valor por defecto no modifica las producciones históricas.
        DB::statement('ALTER TABLE '.$tabla." ALTER COLUMN estado_produccion SET DEFAULT 'BORRADOR'");
    }

    public function down(): void
    {
        $this->verificarMySql();

        if (DB::table('producciones')->where('estado_produccion', '<>', 'LEGADA')
            ->orWhere('inventario_aplicado', '<>', 0)
            ->orWhereNotNull('fecha_confirmacion')->orWhereNotNull('confirmado_por_id')
            ->orWhereNotNull('fecha_anulacion')->orWhereNotNull('anulado_por_id')->exists()) {
            throw new RuntimeException('No se pueden retirar el estado y la auditoría de producciones: ya contienen información utilizada.');
        }

        $tabla = DB::connection()->getQueryGrammar()->wrapTable('producciones');
        DB::statement('ALTER TABLE '.$tabla.'
            DROP FOREIGN KEY producciones_confirmado_por_id_foreign,
            DROP FOREIGN KEY producciones_anulado_por_id_foreign,
            DROP INDEX producciones_confirmado_por_id_index,
            DROP INDEX producciones_anulado_por_id_index,
            DROP INDEX producciones_estado_produccion_index,
            DROP COLUMN estado_produccion,
            DROP COLUMN inventario_aplicado,
            DROP COLUMN fecha_confirmacion,
            DROP COLUMN confirmado_por_id,
            DROP COLUMN fecha_anulacion,
            DROP COLUMN anulado_por_id');
    }

    private function verificarMySql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Esta migración de preparación requiere MySQL.');
        }
    }
};
