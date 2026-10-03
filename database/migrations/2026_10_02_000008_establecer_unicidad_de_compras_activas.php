<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $anteriores = [
        'compras_proveedor_id_serie_numero_unique' => ['proveedor_id', 'serie', 'numero'],
        'compras_numero_autorizacion_unique' => ['numero_autorizacion'],
    ];

    private array $activos = [
        'compras_documento_activo_unique' => ['proveedor_id', 'serie', 'numero', 'marca_activa'],
        'compras_autorizacion_activa_unique' => ['numero_autorizacion', 'marca_activa'],
    ];

    public function up(): void
    {
        $this->verificarMySql();
        (require __DIR__ . '/2026_10_02_000006_verificar_integridad_compras_e_inventario.php')->up();

        // Comprueba las protecciones originales o los reemplazos de un intento interrumpido.
        $reemplazos = array_keys($this->activos);
        foreach (array_keys($this->anteriores) as $posicion => $nombre) {
            $columnas = $this->anteriores[$nombre];
            $reemplazo = $reemplazos[$posicion];
            if (!$this->tieneIndice($nombre, $columnas) && !$this->tieneIndice($reemplazo, $this->activos[$reemplazo])) {
                throw new RuntimeException('Falta la protección UNIQUE ' . $nombre . '. Revise el esquema antes de continuar.');
            }
        }

        if (!Schema::hasColumn('compras', 'marca_activa')) {
            $this->alterar(['ADD COLUMN marca_activa TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN estado = 1 THEN 1 ELSE NULL END) STORED']);
        }

        $this->verificarMarcaActiva();
        $this->agregarIndices($this->activos);

        // No retira ninguno de los UNIQUE antiguos hasta confirmar ambos reemplazos.
        foreach ($this->activos as $nombre => $columnas) {
            if (!$this->tieneIndice($nombre, $columnas)) {
                throw new RuntimeException('No se confirmó el UNIQUE ' . $nombre . '. Se conservan las protecciones anteriores.');
            }
        }

        $this->retirarIndices($this->anteriores);
    }

    public function down(): void
    {
        $this->verificarMySql();

        // La unicidad anterior también se aplica a las compras inactivas.
        foreach ($this->anteriores as $columnas) {
            $duplicado = DB::table('compras')->select($columnas)->groupBy($columnas)->havingRaw('COUNT(*) > 1')->first();

            if ($duplicado) {
                throw new RuntimeException(
                    'No se puede restaurar la unicidad global: existen documentos reutilizados por '
                    . implode(', ', $columnas) . ': ' . json_encode($duplicado, JSON_UNESCAPED_UNICODE)
                    . '. Se conservan los UNIQUE de compras activas y todos los datos.'
                );
            }
        }

        $this->agregarIndices($this->anteriores);

        foreach ($this->anteriores as $nombre => $columnas) {
            if (!$this->tieneIndice($nombre, $columnas)) {
                throw new RuntimeException('No se confirmó la restauración de ' . $nombre . '. Se conservan los UNIQUE activos.');
            }
        }

        $this->retirarIndices($this->activos);

        if (Schema::hasColumn('compras', 'marca_activa')) {
            $this->alterar(['DROP COLUMN marca_activa']);
        }
    }

    private function tieneIndice(string $nombre, array $columnas): bool
    {
        foreach (Schema::getIndexes('compras') as $indice) {
            if ($indice['name'] !== $nombre) {
                continue;
            }

            if (!$indice['unique'] || $indice['primary'] || $indice['columns'] !== $columnas) {
                throw new RuntimeException('El índice ' . $nombre . ' tiene una definición inesperada. No se retirará ninguna protección.');
            }

            return true;
        }

        return false;
    }

    private function agregarIndices(array $indices): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $cambios = [];

        foreach ($indices as $nombre => $columnas) {
            if (!$this->tieneIndice($nombre, $columnas)) {
                $cambios[] = 'ADD UNIQUE INDEX ' . $grammar->wrap($nombre)
                    . ' (' . implode(', ', array_map(fn ($columna) => $grammar->wrap($columna), $columnas)) . ')';
            }
        }

        $this->alterar($cambios);
    }

    private function retirarIndices(array $indices): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $cambios = [];

        foreach ($indices as $nombre => $columnas) {
            if ($this->tieneIndice($nombre, $columnas)) {
                $cambios[] = 'DROP INDEX ' . $grammar->wrap($nombre);
            }
        }

        $this->alterar($cambios);
    }

    private function verificarMarcaActiva(): void
    {
        $conexion = DB::connection();
        $columna = $conexion->selectOne(
            'SELECT DATA_TYPE AS tipo, EXTRA AS extra, GENERATION_EXPRESSION AS expresion, IS_NULLABLE AS nullable
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$conexion->getDatabaseName(), $conexion->getTablePrefix() . 'compras', 'marca_activa']
        );
        $expresion = $columna ? preg_replace('/[\s`()]+/', '', strtolower($columna->expresion)) : '';

        if (!$columna || $columna->tipo !== 'tinyint' || $columna->nullable !== 'YES'
            || !str_contains(strtoupper($columna->extra), 'STORED GENERATED')
            || $expresion !== 'casewhenestado=1then1elsenullend') {
            throw new RuntimeException('marca_activa no tiene la expresión STORED esperada. No se retirarán los UNIQUE anteriores.');
        }
    }

    private function alterar(array $cambios): void
    {
        if ($cambios) {
            DB::statement('ALTER TABLE ' . DB::connection()->getQueryGrammar()->wrapTable('compras') . ' ' . implode(', ', $cambios));
        }
    }

    private function verificarMySql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('La unicidad mediante marca_activa STORED requiere MySQL.');
        }
    }
};
