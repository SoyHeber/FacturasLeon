<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tipoDocumento = DB::table('tipos_documento')
                ->where('codigo', 'FACT')
                ->first();

            if (!$tipoDocumento) {
                $ahora = now();

                DB::table('tipos_documento')->insertOrIgnore([
                    'codigo' => 'FACT',
                    'nombre' => 'Factura',
                    'descripcion' => null,
                    'estado' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                $tipoDocumento = DB::table('tipos_documento')
                    ->where('codigo', 'FACT')
                    ->first();
            }

            if (!$tipoDocumento || $tipoDocumento->codigo !== 'FACT') {
                throw new RuntimeException(
                    'No se pudo obtener el tipo de documento FACT para migrar las compras.'
                );
            }

            DB::table('compras')
                ->select('id', 'tipo_dte')
                ->chunkById(500, function ($compras) {
                    foreach ($compras as $compra) {
                        if ($compra->tipo_dte !== 'FACT') {
                            throw new RuntimeException(
                                'No se pueden migrar las compras: la compra #' . $compra->id
                                . ' tiene tipo_dte diferente de FACT: ' . var_export($compra->tipo_dte, true)
                                . '. No se actualizaron las compras.'
                            );
                        }
                    }
                });

            DB::table('compras')
                ->where('tipo_dte', 'FACT')
                ->update(['tipo_documento_id' => $tipoDocumento->id]);
        });
    }

    public function down(): void
    {
        // Las referencias se conservan hasta revertir la migración del esquema.
    }
};
