<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarMySql();
        (require __DIR__ . '/2026_10_02_000006_verificar_integridad_compras_e_inventario.php')->up();

        foreach (['inventario_aplicado', 'fecha_anulacion', 'anulado_por_id'] as $columna) {
            if (Schema::hasColumn('compras', $columna)) {
                throw new RuntimeException('Ya existe compras.' . $columna . '. Revise el esquema y el registro de migraciones antes de continuar.');
            }
        }

        $grammar = DB::connection()->getQueryGrammar();
        DB::statement('ALTER TABLE ' . $grammar->wrapTable('compras') . '
            ADD COLUMN inventario_aplicado BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN fecha_anulacion DATETIME NULL,
            ADD COLUMN anulado_por_id BIGINT UNSIGNED NULL,
            ADD INDEX compras_anulado_por_id_index (anulado_por_id),
            ADD CONSTRAINT compras_anulado_por_id_foreign FOREIGN KEY (anulado_por_id)
                REFERENCES ' . $grammar->wrapTable('users') . ' (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        $this->verificarMySql();

        if (DB::table('compras')->where('inventario_aplicado', '<>', 0)
            ->orWhereNotNull('fecha_anulacion')->orWhereNotNull('anulado_por_id')->exists()) {
            throw new RuntimeException(
                'No se pueden retirar los campos de inventario/anulación: ya contienen información utilizada. No se eliminaron datos.'
            );
        }

        $tabla = DB::connection()->getQueryGrammar()->wrapTable('compras');
        DB::statement('ALTER TABLE ' . $tabla . '
            DROP FOREIGN KEY compras_anulado_por_id_foreign,
            DROP INDEX compras_anulado_por_id_index,
            DROP COLUMN anulado_por_id,
            DROP COLUMN fecha_anulacion,
            DROP COLUMN inventario_aplicado');
    }

    private function verificarMySql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Esta migración de preparación requiere MySQL.');
        }
    }
};
