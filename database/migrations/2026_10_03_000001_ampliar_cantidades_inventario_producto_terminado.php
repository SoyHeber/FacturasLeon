<?php

use Brick\Math\BigInteger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarMySql();
        $this->validarCantidades('inventarios', '0', '18446744073709551615');
        $this->validarCantidades('movimientos_inventario', '-9223372036854775808', '9223372036854775807');
        $grammar = DB::connection()->getQueryGrammar();

        DB::statement('ALTER TABLE '.$grammar->wrapTable('inventarios').' MODIFY COLUMN cantidad BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE '.$grammar->wrapTable('movimientos_inventario').' MODIFY COLUMN cantidad BIGINT NOT NULL');
    }

    public function down(): void
    {
        $this->verificarMySql();
        $this->validarCantidades('inventarios', '0', '4294967295');
        $this->validarCantidades('movimientos_inventario', '-2147483648', '2147483647');
        $grammar = DB::connection()->getQueryGrammar();

        DB::statement('ALTER TABLE '.$grammar->wrapTable('inventarios').' MODIFY COLUMN cantidad INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE '.$grammar->wrapTable('movimientos_inventario').' MODIFY COLUMN cantidad INT NOT NULL');
    }

    private function validarCantidades(string $tabla, string $minimo, string $maximo): void
    {
        DB::table($tabla)->select(['id', 'cantidad'])->orderBy('id')->chunkById(200, function ($filas) use ($tabla, $minimo, $maximo) {
            foreach ($filas as $fila) {
                $valor = $fila->cantidad;
                if ((! is_string($valor) && ! is_int($valor)) || strlen((string) $valor) > 80
                    || ! preg_match('/^[+-]?[0-9]+$/D', (string) $valor)) {
                    throw new RuntimeException('Cantidad inválida en '.$tabla.' #'.$fila->id.'. No se modificó el esquema.');
                }
                $entero = BigInteger::of($valor);
                if ($entero->isLessThan($minimo) || $entero->isGreaterThan($maximo)) {
                    throw new RuntimeException('Cantidad fuera de rango en '.$tabla.' #'.$fila->id.'. No se modificó el esquema.');
                }
            }
        });
    }

    private function verificarMySql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('La ampliación de cantidades requiere MySQL.');
        }
    }
};
