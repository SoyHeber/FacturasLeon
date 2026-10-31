<?php

namespace App\Http\Controllers;

use App\Services\ExportadorCsvService;
use App\Services\ReportesGeneralesService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesGeneralesController extends Controller
{
    /** Consulta paginada y sumas del resultado completo; no ejecuta operaciones de negocio. */
    public function index(Request $request, string $reporte)
    {
        [$definicion, $filtros] = $this->preparar($request, $reporte);
        $resultado = (new ReportesGeneralesService)->consultar($definicion, $filtros, (int) $filtros['page'], (int) $filtros['por_pagina']);
        return view('reportes_generales.index', $resultado + compact('definicion', 'filtros'));
    }

    /**
     * Descarga todas las filas filtradas por lotes, no solo la página visible.
     * Revalida permiso y filtros antes de abrir el flujo. No almacena copias ni
     * carga XML FEL. Un fallo de consulta o escritura interrumpe la descarga.
     */
    public function exportar(Request $request, string $reporte): StreamedResponse
    {
        [$definicion, $filtros] = $this->preparar($request, $reporte);
        $nombre = 'reporte-'.$reporte.'-'.$definicion['vista'].'-'.CarbonImmutable::now('America/Guatemala')->format('Ymd-His').'.csv';
        return new StreamedResponse(function () use ($definicion, $filtros) {
            $destino = fopen('php://output', 'wb');
            try {
                (new ExportadorCsvService)->escribir($destino, $definicion['columnas'], (new ReportesGeneralesService)->filas($definicion, $filtros), $filtros['separador']);
            } finally {
                if (is_resource($destino)) {
                    fclose($destino);
                }
            }
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * Admite solo familias, vistas y columnas definidas en servidor. Exportar exige
     * ver en esa familia y no concede acceso a sus CRUD. El período máximo es de
     * 366 días; catálogos y existencias son actuales. Estado vacío incluye ambos,
     * mientras inicialmente se muestran activos. No escribe datos ni llama a FEL.
     */
    private function preparar(Request $request, string $reporte): array
    {
        if (! isset(ReportesGeneralesService::VISTAS[$reporte])) {
            throw ValidationException::withMessages(['reporte' => 'El reporte no está disponible.']);
        }
        $this->authorize('reportes_'.$reporte.'.ver');
        $vistas = ReportesGeneralesService::VISTAS[$reporte];
        $hoy = CarbonImmutable::now('America/Guatemala');
        $request->mergeIfMissing(['vista' => array_key_first($vistas), 'desde' => $hoy->startOfMonth()->toDateString(),
            'hasta' => $hoy->toDateString(), 'page' => 1, 'por_pagina' => 25, 'separador' => ';']);
        $request->validate(['vista' => ['required', Rule::in(array_keys($vistas))]]);
        $definicion = (new ReportesGeneralesService)->definicion($reporte, $request->input('vista'));
        $request->validate(['filtros' => ['sometimes', 'array']]);
        if (isset($definicion['columnas']['estado']) && ! $request->has('filtros.estado')) {
            $request->merge(['filtros' => array_merge($request->input('filtros', []), ['estado' => '1'])]);
        }
        $reglas = ['vista' => ['required', Rule::in(array_keys($vistas))], 'page' => ['required', 'integer', 'min:1', 'max:1000000'],
            'por_pagina' => ['required', Rule::in([25, 50, 100])], 'separador' => ['required', Rule::in([';', ','])],
            'filtros' => ['sometimes', 'array:'.implode(',', array_keys($definicion['columnas']))]];
        if ($definicion['fechas']) {
            $reglas += ['desde' => ['required', 'date_format:Y-m-d'], 'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde']];
        }
        foreach ($definicion['columnas'] as $campo => $columna) {
            $reglas['filtros.'.$campo] = match ($columna['tipo']) {
                'fecha' => ['nullable', 'date_format:Y-m-d'],
                'estado' => ['nullable', Rule::in(['0', '1'])],
                'entero' => ['nullable', 'regex:/^-?\d{1,20}$/'],
                'decimal', 'dinero' => ['nullable', 'regex:/^-?\d{1,20}(?:\.\d{1,10})?$/'],
                default => ['nullable', 'string', 'max:200'],
            };
        }
        $filtros = $request->validate($reglas);
        if ($definicion['fechas'] && CarbonImmutable::parse($filtros['desde'])->diffInDays(CarbonImmutable::parse($filtros['hasta'])) > 365) {
            throw ValidationException::withMessages(['hasta' => 'Consulta hasta 366 días por descarga. Divide períodos mayores.']);
        }
        return [$definicion, $filtros];
    }
}
