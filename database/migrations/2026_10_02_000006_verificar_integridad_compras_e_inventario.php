<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $estadoInvalido = DB::table('compras')
            ->whereNull('estado')->orWhereNotIn('estado', [0, 1])->value('id');

        if ($estadoInvalido !== null) {
            throw new RuntimeException('La compra #' . $estadoInvalido . ' tiene un estado inválido. No se modificó el esquema.');
        }

        foreach ([['proveedor_id', 'serie', 'numero'], ['numero_autorizacion']] as $columnas) {
            $duplicado = DB::table('compras')->where('estado', 1)
                ->select($columnas)->groupBy($columnas)->havingRaw('COUNT(*) > 1')->first();

            if ($duplicado) {
                throw new RuntimeException(
                    'Existen compras activas duplicadas por ' . implode(', ', $columnas)
                    . ': ' . json_encode($duplicado, JSON_UNESCAPED_UNICODE) . '. No se modificó el esquema.'
                );
            }
        }

        $duplicado = DB::table('movimientos_inventario_compra')->whereNotNull('detalle_compra_id')
            ->select('detalle_compra_id', 'tipo_movimiento')
            ->groupBy('detalle_compra_id', 'tipo_movimiento')->havingRaw('COUNT(*) > 1')->first();

        if ($duplicado) {
            throw new RuntimeException(
                'El detalle de compra #' . $duplicado->detalle_compra_id . ' tiene movimientos repetidos de tipo '
                . $duplicado->tipo_movimiento . ', incluidos los inactivos. Revise el historial; no se eliminaron movimientos.'
            );
        }

        $inventarioNegativo = DB::table('inventarios_compra')->where('cantidad', '<', 0)->value('id');

        if ($inventarioNegativo !== null) {
            throw new RuntimeException('El inventario de compra #' . $inventarioNegativo . ' tiene stock negativo. No se modificó su cantidad.');
        }
    }

    public function down(): void
    {
        // La verificación no modifica datos ni esquema.
    }
};
