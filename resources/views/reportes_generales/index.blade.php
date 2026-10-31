@extends('layouts.app-bootstrap')

@section('content')
    <style>
        .main-content { min-width: 0; }
        .reporte-tabla { font-variant-numeric: tabular-nums; }
        .reporte-tabla th { white-space: nowrap; }
        .reporte-tabla .form-control, .reporte-tabla .form-select { min-width: 130px; }
        .reporte-tabla td { min-width: 110px; max-width: 340px; overflow-wrap: anywhere; }
        .reporte-controles { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; }
        .reporte-resumen { font-variant-numeric: tabular-nums; }
        .reporte-desplazable:focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
        @media (max-width: 575px) {
            .reporte-controles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .reporte-controles .form-control, .reporte-controles .form-select { min-width: 0; max-width: 100%; }
            .reporte-controles > button, .reporte-controles > p { grid-column: 1 / -1; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">{{ $definicion['titulo'] }}</h1>
            <p class="text-muted mb-0">{{ $definicion['fechas'] ? 'Consulta el historial de operaciones validadas y descarga sus resultados.' : 'Consulta la información actual y descarga sus resultados.' }}</p>
        </div>
        @can('reportes_'.$definicion['reporte'].'.ver')
            <button type="submit" form="filtros-reportes" formaction="{{ route('reportes_'.$definicion['reporte'].'.exportar') }}" class="btn btn-warning fw-bold">Descargar CSV</button>
        @endcan
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revisa los filtros para continuar.</strong>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (count(\App\Services\ReportesGeneralesService::VISTAS[$definicion['reporte']]) > 1)
        <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Vistas del reporte">
            @foreach (\App\Services\ReportesGeneralesService::VISTAS[$definicion['reporte']] as $clave => $nombre)
                <a class="btn btn-sm {{ $clave === $definicion['vista'] ? 'btn-dark' : 'btn-outline-secondary' }}"
                    @if ($clave === $definicion['vista']) aria-current="page" @endif
                    href="{{ route('reportes_'.$definicion['reporte'].'.index', ['vista' => $clave] + ($definicion['fechas'] ? ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']] : [])) }}">{{ $nombre }}</a>
            @endforeach
        </nav>
    @endif

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <form id="filtros-reportes" method="GET" action="{{ route('reportes_'.$definicion['reporte'].'.index') }}">
                <input type="hidden" name="vista" value="{{ $definicion['vista'] }}">
                <div class="reporte-controles mb-3">
                    @if ($definicion['fechas'])
                        <div><label for="desde" class="form-label">Desde</label><input id="desde" type="date" name="desde" value="{{ $filtros['desde'] }}" class="form-control" required></div>
                        <div><label for="hasta" class="form-label">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control" required></div>
                    @else
                        <p class="mb-2 text-muted">Información actual · {{ now('America/Guatemala')->format('d/m/Y H:i') }}</p>
                    @endif
                    <div><label for="por-pagina" class="form-label">Filas por página</label>
                        <select id="por-pagina" name="por_pagina" class="form-select">@foreach ([25, 50, 100] as $cantidad)<option value="{{ $cantidad }}" @selected((int) $filtros['por_pagina'] === $cantidad)>{{ $cantidad }}</option>@endforeach</select>
                    </div>
                    <div><label for="separador" class="form-label">Separador CSV</label>
                        <select id="separador" name="separador" class="form-select"><option value=";" @selected($filtros['separador'] === ';')>Punto y coma (;)</option><option value="," @selected($filtros['separador'] === ',')>Coma (,)</option></select>
                    </div>
                    <button type="submit" class="btn btn-dark">Consultar</button>
                </div>
            </form>
            <p class="text-muted mb-3">{{ $definicion['nota'] }}</p>
            <p class="small mb-2">{{ number_format($total) }} {{ $total === 1 ? 'fila encontrada' : 'filas encontradas' }}. El CSV incluye todo el resultado filtrado.</p>

            <div class="table-responsive reporte-desplazable" tabindex="0" role="region" aria-label="Resultados de {{ $definicion['titulo'] }}. Desplázate horizontalmente para ver todas las columnas.">
                <table class="table table-bordered table-hover align-middle mb-0 reporte-tabla">
                    <caption class="visually-hidden">{{ $definicion['titulo'] }} — {{ \App\Services\ReportesGeneralesService::VISTAS[$definicion['reporte']][$definicion['vista']] }}</caption>
                    <thead>
                        <tr class="table-dark">@foreach ($definicion['columnas'] as $columna)<th scope="col">{{ $columna['titulo'] }}</th>@endforeach<th scope="col">Filtros</th></tr>
                        <tr>
                            @foreach ($definicion['columnas'] as $campo => $columna)
                                <th>
                                    @if ($columna['tipo'] === 'estado')
                                        <select form="filtros-reportes" name="filtros[{{ $campo }}]" class="form-select form-select-sm" aria-label="Filtrar {{ $columna['titulo'] }}">
                                            <option value="">Todos</option><option value="1" @selected(($filtros['filtros'][$campo] ?? '') === '1')>Activo</option><option value="0" @selected(($filtros['filtros'][$campo] ?? '') === '0')>Inactivo</option>
                                        </select>
                                    @else
                                        <input form="filtros-reportes" name="filtros[{{ $campo }}]" value="{{ $filtros['filtros'][$campo] ?? '' }}"
                                            type="{{ $columna['tipo'] === 'fecha' ? 'date' : (in_array($columna['tipo'], ['entero', 'decimal', 'dinero']) ? 'number' : 'text') }}"
                                            @if (in_array($columna['tipo'], ['decimal', 'dinero'])) step="any" @endif
                                            class="form-control form-control-sm" aria-label="Filtrar {{ $columna['titulo'] }}" placeholder="{{ $columna['titulo'] }}">
                                    @endif
                                </th>
                            @endforeach
                            <th><div class="d-flex gap-2"><button type="submit" form="filtros-reportes" class="btn btn-dark btn-sm">Filtrar</button><a href="{{ route('reportes_'.$definicion['reporte'].'.index', ['vista' => $definicion['vista']]) }}" class="btn btn-outline-secondary btn-sm">Limpiar</a></div></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                @foreach ($definicion['columnas'] as $campo => $columna)
                                    @php
                                        $valor = $fila[$campo];
                                        $numerico = in_array($columna['tipo'], ['entero', 'decimal', 'dinero']);
                                        if ($valor !== null && $columna['tipo'] === 'decimal') $valor = rtrim(rtrim($valor, '0'), '.');
                                        if ($valor !== null && $columna['tipo'] === 'fecha') $valor = \Carbon\CarbonImmutable::parse($valor)->format('d/m/Y');
                                    @endphp
                                    <td class="{{ $numerico ? 'text-end text-nowrap' : '' }} {{ $numerico && str_starts_with((string) $valor, '-') ? 'text-danger' : '' }}">
                                        @if ($columna['tipo'] === 'estado')<span class="badge {{ $valor === 'Activo' ? 'bg-success' : 'bg-secondary' }}">{{ $valor }}</span>@else{{ $valor === null || $valor === '' ? '—' : $valor }}@endif
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($definicion['columnas']) + 1 }}" class="text-center text-muted py-4">No hay registros que cumplan estos filtros. Prueba otro período o limpia los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $filas->links() }}</div>

            @if ($resumen)
                <h2 class="h6 fw-bold mt-4">Totales del resultado filtrado</h2>
                <div class="table-responsive reporte-desplazable" tabindex="0" role="region" aria-label="Totales agrupados">
                    <table class="table table-sm align-middle reporte-resumen mb-0">
                        <thead><tr><th scope="col">{{ $definicion['grupo'] === 'moneda' ? 'Moneda' : 'Unidad' }}</th>@foreach ($definicion['sumas'] as $campo)<th scope="col" class="text-end">{{ $definicion['columnas'][$campo]['titulo'] }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($resumen as $grupo => $totales)<tr><th scope="row">{{ $grupo }}</th>@foreach ($definicion['sumas'] as $campo)<td class="text-end">{{ $totales[$campo] }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @endif
            <details class="mt-4 small text-muted"><summary>Observaciones de la descarga</summary>
                <ul class="mb-0 mt-2"><li>El archivo CSV contiene datos y encabezados, sin estilos, gráficos ni filas adicionales de totales.</li><li>Los importes usan punto decimal y la moneda aparece en su propia columna. Al importar en Excel, selecciona el separador elegido y trata códigos e identificaciones como texto para conservar ceros iniciales.</li><li>Los textos que podrían interpretarse como fórmulas se protegen con un apóstrofo. Los importes negativos conservan su signo.</li>@if ($definicion['fechas'])<li>Las fechas corresponden al día local de Guatemala. Consulta hasta 366 días por descarga.</li>@endif</ul>
            </details>
        </div>
    </div>
@endsection
