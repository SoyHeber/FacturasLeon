<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repite la verificación justo antes de cambiar el esquema.
        (require __DIR__ . '/2026_10_02_000004_verificar_tipos_documento_de_compras.php')->up();

        $grammar = DB::connection()->getQueryGrammar();
        $cambios = ['MODIFY ' . $grammar->wrap('tipo_documento_id') . ' BIGINT UNSIGNED NOT NULL'];

        foreach (Schema::getIndexes('compras') as $indice) {
            if (!in_array('tipo_dte', $indice['columns'], true)) {
                continue;
            }

            if ($indice['columns'] !== ['tipo_dte'] || $indice['primary']) {
                throw new RuntimeException(
                    'No se puede eliminar tipo_dte: el índice ' . $indice['name']
                    . ' requiere revisión antes de modificar el esquema.'
                );
            }

            $cambios[] = 'DROP INDEX ' . $grammar->wrap($indice['name']);
        }

        $cambios[] = 'DROP COLUMN ' . $grammar->wrap('tipo_dte');

        // Un solo ALTER conserva la FK y evita dividir el cambio de esquema.
        DB::statement('ALTER TABLE ' . $grammar->wrapTable('compras') . ' ' . implode(', ', $cambios));
    }

    public function down(): void
    {
        DB::table('compras')
            ->leftJoin('tipos_documento', 'tipos_documento.id', '=', 'compras.tipo_documento_id')
            ->select('compras.id', 'tipos_documento.codigo')
            ->chunkById(500, function ($compras) {
                foreach ($compras as $compra) {
                    if (!is_string($compra->codigo) || mb_strlen($compra->codigo) > 10 || $compra->codigo === '') {
                        throw new RuntimeException(
                            'No se puede revertir la transición: la compra #' . $compra->id
                            . ' no tiene un código válido para restaurar tipo_dte VARCHAR(10).'
                        );
                    }
                }
            }, 'compras.id', 'id');

        $grammar = DB::connection()->getQueryGrammar();
        $tabla = $grammar->wrapTable('compras');

        // Permite reintentar el rollback si se interrumpe después de agregar la columna.
        if (!Schema::hasColumn('compras', 'tipo_dte')) {
            DB::statement('ALTER TABLE ' . $tabla . ' ADD COLUMN ' . $grammar->wrap('tipo_dte') . ' VARCHAR(10) NULL');
        }

        DB::transaction(function () use ($grammar) {
            DB::table('compras')->update([
                'tipo_dte' => DB::raw(
                    '(SELECT ' . $grammar->wrap('codigo') . ' FROM ' . $grammar->wrapTable('tipos_documento')
                    . ' WHERE ' . $grammar->wrap('tipos_documento.id') . ' = ' . $grammar->wrap('compras.tipo_documento_id') . ')'
                ),
            ]);
        });

        $cambios = [
            'MODIFY ' . $grammar->wrap('tipo_dte') . ' VARCHAR(10) NOT NULL',
            'MODIFY ' . $grammar->wrap('tipo_documento_id') . ' BIGINT UNSIGNED NULL',
        ];

        $tieneIndice = collect(Schema::getIndexes('compras'))
            ->contains(fn ($indice) => $indice['columns'] === ['tipo_dte']);

        if (!$tieneIndice) {
            $cambios[] = 'ADD INDEX ' . $grammar->wrap('compras_tipo_dte_index') . ' (' . $grammar->wrap('tipo_dte') . ')';
        }

        DB::statement('ALTER TABLE ' . $tabla . ' ' . implode(', ', $cambios));
    }
};
