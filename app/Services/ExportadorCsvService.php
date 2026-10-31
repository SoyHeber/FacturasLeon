<?php

namespace App\Services;

use RuntimeException;

class ExportadorCsvService
{
    /**
     * Escribe encabezados y filas en el recurso recibido, con BOM UTF-8 y CRLF.
     * Las columnas están ordenadas y tipadas por el reporte: los textos con inicios
     * que una hoja podría interpretar como fórmula se prefijan con apóstrofo.
     * Los números ya validados conservan sus signos y precisión, sin esa protección
     * textual, para que las anulaciones puedan sumarse. fputcsv escapa comillas,
     * separadores y saltos de línea. Un fallo de escritura interrumpe la descarga.
     * No cierra el recurso, no guarda copias y no modifica los registros exportados.
     */
    public function escribir($recurso, array $columnas, iterable $filas, string $separador = ';'): void
    {
        if (! in_array($separador, [';', ','], true) || ! is_resource($recurso)) {
            throw new RuntimeException('El destino o separador del CSV no es válido.');
        }
        if (fwrite($recurso, "\xEF\xBB\xBF") === false) {
            throw new RuntimeException('No se pudo escribir el CSV.');
        }
        $this->linea($recurso, array_column($columnas, 'titulo'), $separador);
        foreach ($filas as $fila) {
            $valores = [];
            foreach ($columnas as $campo => $columna) {
                $valor = (string) ($fila[$campo] ?? '');
                if (in_array($columna['tipo'], ['texto', 'identificador'], true) && preg_match('/^[\s\x00-\x20]*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $valor)) {
                    $valor = "'".$valor;
                }
                $valores[] = $valor;
            }
            $this->linea($recurso, $valores, $separador);
        }
    }

    /** Propaga errores de E/S en lugar de entregar una fila truncada como correcta. */
    private function linea($recurso, array $valores, string $separador): void
    {
        if (fputcsv($recurso, $valores, $separador, '"', '', "\r\n") === false) {
            throw new RuntimeException('No se pudo completar una fila del CSV.');
        }
    }
}
