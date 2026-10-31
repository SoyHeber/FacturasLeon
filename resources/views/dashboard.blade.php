@extends('layouts.app-bootstrap')

@section('content')
    @php
        // Conserva las cadenas decimales al presentar los importes monetarios.
        $numero = static function ($valor) {
            $partes = explode('.', (string) $valor);
            return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $partes[0]).'.'.str_pad($partes[1] ?? '', 2, '0');
        };
        $dinero = static fn ($valor, $moneda) => ($moneda === 'GTQ' ? 'Q ' : $moneda.' ').$numero($valor);
        $cantidad = static fn ($valor) => rtrim(rtrim(str_contains((string) $valor, '.') ? (string) $valor : $valor.'.0', '0'), '.');
        $nombresInventario = ['inventarios' => 'Productos terminados', 'inventarios_compra' => 'Materias primas'];
        $nombresModulo = ['ventas' => 'Venta', 'compras' => 'Compra', 'producciones' => 'Producción'];
        $reposicion = array_sum(array_column($inventarios, 'total'));
    @endphp
    <style>
        /* Permite que el contenido se ajuste al layout flex en pantallas pequeñas. */
        .main-content { min-width: 0; }
        .dashboard-numero { font-variant-numeric: tabular-nums; }
        .dashboard-indicador { font-size: 1.8rem; overflow-wrap: anywhere; }
        .dashboard-tabla-diaria { max-height: 24rem; overflow: auto; }
        .dashboard-tabla-diaria thead { position: sticky; top: 0; z-index: 1; }
        .dashboard-grafico { display: block; width: 100%; height: auto; }
        .dashboard-grafico text { font-family: inherit; font-size: 16px; fill: #374151; }
        .dashboard-leyenda { display: inline-block; width: 1.1rem; height: .2rem; vertical-align: middle; margin-right: .3rem; }
        @media (max-width: 575px) {
            .dashboard-grafico text { display: none; }
        }
    </style>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="fw-bold text-dark mb-1">Resumen operativo</h1>
            <p class="text-muted mb-0">Hola, {{ request()->user()->name }}. Consulta la actividad de la joyería y los asuntos por atender.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('ventas.crear')<a href="{{ route('ventas.create') }}" class="btn btn-warning fw-bold">+ Nueva venta</a>@endcan
            @can('compras.crear')<a href="{{ route('compras.create') }}" class="btn btn-outline-dark">Nueva compra</a>@endcan
            @can('producciones.crear')<a href="{{ route('producciones.create') }}" class="btn btn-outline-dark">Nueva producción</a>@endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-bold mb-1">Revisa el período seleccionado.</p>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (in_array(true, $permisos, true))
        <div class="card border-0 rounded-4 shadow-sm mb-4"><div class="card-body p-4">
            <form method="GET" action="{{ route('dashboard') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="periodo" class="form-label">Período</label>
                    <select id="periodo" name="periodo" class="form-select">
                        @foreach (['mes' => 'Este mes', 'hoy' => 'Hoy', 'personalizado' => 'Rango de fechas'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('periodo', $periodo['seleccion']) === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="desde" class="form-label">Desde</label>
                    <input id="desde" name="desde" type="date" class="form-control" value="{{ old('desde', $periodo['desde']->toDateString()) }}" aria-describedby="ayuda-periodo">
                </div>
                <div class="col-6 col-md-3">
                    <label for="hasta" class="form-label">Hasta</label>
                    <input id="hasta" name="hasta" type="date" class="form-control" value="{{ old('hasta', $periodo['hasta']->toDateString()) }}" aria-describedby="ayuda-periodo">
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-dark" type="submit">Consultar</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Restablecer</a>
                </div>
                <div class="col-12">
                    <p id="ayuda-periodo" class="small text-muted mb-0">{{ $periodo['desde']->format('d/m/Y') }} al {{ $periodo['hasta']->format('d/m/Y') }} · Horario de Guatemala. Para elegir fechas, selecciona “Rango de fechas” (máximo 366 días). Las alertas muestran la situación actual.</p>
                </div>
            </form>
        </div></div>

        <div class="row g-4 mb-4">
            @if ($ventas !== null)
                <div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 rounded-4 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h6 text-muted mb-3">Facturación neta</h2>
                    @forelse ($ventas['importes'] as $moneda => $importes)
                        <p class="dashboard-numero dashboard-indicador fw-bold mb-1">{{ $dinero($importes['neto'], $moneda) }}</p>
                        <p class="small mb-1">Emitido: {{ $dinero($importes['emitido'], $moneda) }}</p>
                        <p class="small mb-3">Anulado: −{{ $dinero($importes['anulado'], $moneda) }}</p>
                    @empty
                        <p class="dashboard-indicador fw-bold mb-1">Q 0.00</p><p class="small text-muted">Sin eventos certificados en el período.</p>
                    @endforelse
                    <p class="small text-muted mb-2">Importes con IVA. Emisiones menos anulaciones confirmadas.</p>
                    @can('ventas.ver')<a href="{{ route('ventas.index') }}" class="small">Ver ventas</a>@endcan
                </div></div></div>
            @endif
            @if ($compras !== null)
                <div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 rounded-4 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h6 text-muted mb-3">Compras netas</h2>
                    @forelse ($compras['importes'] as $moneda => $importes)
                        <p class="dashboard-numero dashboard-indicador fw-bold mb-1">{{ $dinero($importes['neto'], $moneda) }}</p>
                        <p class="small mb-1">Registrado: {{ $dinero($importes['emitido'], $moneda) }}</p>
                        <p class="small mb-3">Anulado: −{{ $dinero($importes['anulado'], $moneda) }}</p>
                    @empty
                        <p class="dashboard-indicador fw-bold mb-1">Q 0.00</p><p class="small text-muted">Sin eventos de compras validadas en el período.</p>
                    @endforelse
                    <p class="small text-muted mb-2">Importes con IVA. Monedas separadas; históricas por revisar excluidas.</p>
                    @can('compras.ver')<a href="{{ route('compras.index') }}" class="small">Ver compras</a>@endcan
                </div></div></div>
            @endif
            @if ($produccion !== null)
                <div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 rounded-4 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h6 text-muted mb-3">Producción del período</h2>
                    <p class="dashboard-numero dashboard-indicador fw-bold mb-1">{{ $produccion['neto'] }} <span class="fs-6 fw-normal">unidades netas</span></p>
                    <p class="small mb-1">{{ $produccion['ordenes'] }} confirmaciones · {{ $produccion['unidades'] }} unidades</p>
                    <p class="small mb-3">{{ $produccion['reversiones'] }} reversiones · −{{ $produccion['revertidas'] }} unidades</p>
                    <p class="small text-muted mb-2">Solo operaciones que ingresaron producto terminado.</p>
                    @can('producciones.ver')<a href="{{ route('producciones.index') }}" class="small">Ver producciones</a>@endcan
                </div></div></div>
            @endif
            @if ($inventarios)
                <div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 rounded-4 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h6 text-muted mb-3">Referencias por reponer</h2>
                    <p class="dashboard-numero dashboard-indicador fw-bold mb-1">{{ $reposicion }}</p>
                    @foreach ($inventarios as $modulo => $inventario)<p class="small mb-1">{{ $nombresInventario[$modulo] }}: {{ $inventario['total'] }}</p>@endforeach
                    <p class="small text-muted mt-3 mb-0">Situación actual · Sin existencias o debajo del mínimo. Se cuentan referencias, no se suman sus cantidades.</p>
                </div></div></div>
            @endif
        </div>

        @if ($compras !== null && $compras['historicas'] > 0)
            <div class="alert alert-warning" role="status"><strong>{{ $compras['historicas'] }} compras históricas por revisar.</strong> Están fuera del total validado porque no tienen aplicación de inventario registrada. Puedes consultarlas en Compras.</div>
        @endif
        @if ($ventas !== null && ($ventas['sin_ambiente'] > 0 || count($ventas['ambientes']) > 1 || in_array('PRUEBAS', $ventas['ambientes'], true)))
            <div class="alert alert-warning" role="status">
                <strong>Origen de los DTE del período:</strong> {{ implode(', ', $ventas['ambientes']) ?: 'sin ambiente registrado' }}.
                @if ($ventas['sin_ambiente'] > 0){{ $ventas['sin_ambiente'] }} documentos sin ambiente registrado.@endif
                El resumen incluye estos documentos; los no clasificados no se asumen de producción.
            </div>
        @endif

        <div class="row g-4 mb-4">
            @if ($inventarios || $ventas !== null)
                <div class="col-12 {{ $ventas !== null ? 'col-xl-5' : '' }}"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-1">Atención prioritaria</h2>
                    <p class="small text-muted mb-3">Situación actual, independiente del período consultado.</p>
                    @if ($ventas !== null)
                        <h3 class="h6 mt-3">Documentos FEL por revisar <span class="badge text-bg-secondary">{{ $ventas['alertas']['total'] }}</span></h3>
                        @forelse ($ventas['alertas']['items'] as $alerta)
                            <div class="py-2 border-bottom">
                                @can('ventas.ver')<a href="{{ route('ventas.show', $alerta->id) }}" class="fw-semibold">Venta #{{ $alerta->id }}</a>@endcan
                                <span class="small d-block">@if ($alerta->estado_anulacion === 'CONFIRMADA')Anulación confirmada; reintegro pendiente.
                                    @elseif (in_array($alerta->estado_anulacion, ['EN_PROCESO', 'RECHAZADA', 'INCIERTA'], true))Anulación: {{ $alerta->estado_anulacion }}.
                                    @else Certificación: {{ $alerta->estado_fel }}.@endif</span>
                            </div>
                        @empty
                            <p class="small text-muted">Sin documentos FEL pendientes de revisión.</p>
                        @endforelse
                        @if ($ventas['alertas']['total'] > 5)<p class="small text-muted mt-2">Se muestran 5 de {{ $ventas['alertas']['total'] }} documentos.</p>@endif
                    @endif
                    @foreach ($inventarios as $modulo => $inventario)
                        <h3 class="h6 mt-4">{{ $nombresInventario[$modulo] }} <span class="badge text-bg-secondary">{{ $inventario['total'] }}</span></h3>
                        @forelse ($inventario['items'] as $item)
                            <div class="py-2 border-bottom">
                                @can($modulo.'.ver')<a href="{{ route($modulo.'.show', $item->id) }}" class="fw-semibold text-break">{{ $item->nombre }}</a>@endcan
                                <span class="small d-block">{{ $cantidad($item->cantidad) }} {{ $modulo === 'inventarios' ? 'unidades' : $item->unidad_medida }} · Mínimo: {{ $cantidad($item->stock_minimo) }} ·
                                    <span class="{{ \Brick\Math\BigDecimal::of($item->cantidad)->isZero() ? 'text-danger' : 'text-dark' }}">{{ \Brick\Math\BigDecimal::of($item->cantidad)->isZero() ? 'Sin existencias' : 'Stock bajo' }}</span>
                                </span>
                            </div>
                        @empty
                            <p class="small text-muted">Sin referencias que requieran reposición.</p>
                        @endforelse
                        @if ($inventario['total'] > 5)<p class="small text-muted mt-2">Se muestran 5 de {{ $inventario['total'] }} referencias; las agotadas aparecen primero.</p>@endif
                        @can($modulo.'.ver')<a href="{{ route($modulo.'.index') }}" class="small d-inline-block mt-2">Ver {{ mb_strtolower($nombresInventario[$modulo]) }}</a>@endcan
                    @endforeach
                </div></div></div>
            @endif
            @if ($ventas !== null)
                <div class="col-12 col-xl-7">
                    <div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-2">Facturación diaria</h2>
                        <div class="d-flex flex-wrap gap-3 small mb-3">
                            <span><span class="dashboard-leyenda" style="background:#166534"></span>Emitido</span>
                            <span><span class="dashboard-leyenda" style="background:#b91c1c"></span>Anulado (negativo)</span>
                            <span><span class="dashboard-leyenda" style="background:#0f172a"></span>Neto</span>
                        </div>
                        @forelse ($ventas['graficos'] as $moneda => $grafico)
                            <h3 class="h6">{{ $moneda }}</h3>
                            <svg class="dashboard-grafico" viewBox="0 0 760 220" role="img" aria-labelledby="grafico-titulo-{{ $moneda }} grafico-descripcion-{{ $moneda }}">
                                <title id="grafico-titulo-{{ $moneda }}">Facturación diaria en {{ $moneda }}</title>
                                <desc id="grafico-descripcion-{{ $moneda }}">Emisiones positivas, anulaciones negativas y neto. Los valores por fecha están en la tabla DTE emitidos por día.</desc>
                                <line x1="80" x2="720" y1="{{ $grafico['cero'] }}" y2="{{ $grafico['cero'] }}" stroke="#9ca3af" />
                                <text x="2" y="20">{{ number_format($grafico['maximo'], 2) }}</text><text x="2" y="180">{{ number_format($grafico['minimo'], 2) }}</text>
                                @foreach (['emitido' => '#166534', 'anulado' => '#b91c1c', 'neto' => '#0f172a'] as $serie => $color)
                                    @if (count($ventas['dias']) === 1)
                                        @php([$x, $y] = explode(',', $grafico['puntos'][$serie]))
                                        <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="{{ $color }}" />
                                    @else
                                        <polyline points="{{ $grafico['puntos'][$serie] }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" />
                                    @endif
                                @endforeach
                                <text x="80" y="207">{{ $periodo['desde']->format('d/m/Y') }}</text><text x="720" y="207" text-anchor="end">{{ $periodo['hasta']->format('d/m/Y') }}</text>
                            </svg>
                            <div class="d-sm-none small text-muted mb-3">
                                <div class="d-flex justify-content-between gap-2"><span>{{ $periodo['desde']->format('d/m/Y') }}</span><span>{{ $periodo['hasta']->format('d/m/Y') }}</span></div>
                                <p class="dashboard-numero mb-0">Escala: {{ number_format($grafico['minimo'], 2) }} a {{ number_format($grafico['maximo'], 2) }} {{ $moneda }}.</p>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sin emisiones ni anulaciones certificadas en el período. El detalle diario muestra cero DTE.</p>
                        @endforelse
                    </div></div>
                </div>
            @endif
        </div>

        @if ($ventas !== null)
            <div class="card border-0 rounded-4 shadow-sm mb-4"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">DTE emitidos por día</h2>
                <p class="small mb-1"><strong>{{ $ventas['emitidos'] }} emitidos</strong> · {{ $ventas['anulados'] }} anulados en el período.</p>
                <p class="small text-muted mb-3">Cada DTE cuenta una vez en su emisión aunque después se anule. Los borradores no se incluyen.</p>
                <div class="dashboard-tabla-diaria table-responsive" role="region" aria-label="Detalle diario de DTE" tabindex="0">
                    <table class="table table-bordered table-hover table-sm align-middle mb-0 dashboard-numero">
                        <caption class="visually-hidden">DTE emitidos, anulaciones y facturación por día, en horario de Guatemala.</caption>
                        <thead class="table-dark"><tr><th scope="col">Fecha</th><th scope="col" class="text-end">Emitidos</th><th scope="col" class="text-end">Anulados</th><th scope="col" class="text-end">Neto</th></tr></thead>
                        <tbody>
                            @foreach ($ventas['dias'] as $dia)
                                <tr><th scope="row" class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($dia['fecha'])->format('d/m/Y') }}</th>
                                    <td class="text-end">{{ $dia['emitidos'] }}@foreach ($dia['importes'] as $moneda => $importes)<span class="d-block text-nowrap small text-muted">{{ $dinero($importes['emitido'], $moneda) }}</span>@endforeach</td>
                                    <td class="text-end">{{ $dia['anulados'] }}@foreach ($dia['importes'] as $moneda => $importes)<span class="d-block text-nowrap small text-muted">−{{ $dinero($importes['anulado'], $moneda) }}</span>@endforeach</td>
                                    <td class="text-end">@forelse ($dia['importes'] as $moneda => $importes)<span class="d-block text-nowrap">{{ $dinero($importes['neto'], $moneda) }}</span>@empty Q 0.00 @endforelse</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div></div>
        @endif

        @if ($ventas !== null || $compras !== null || $produccion !== null)
            <div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-1">Actividad reciente</h2>
                <p class="small text-muted mb-3">Últimos 8 eventos del período en los módulos que puedes consultar.</p>
                <div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark"><tr><th scope="col">Fecha</th><th scope="col">Documento</th><th scope="col">Evento</th><th scope="col" class="text-end">Importe o unidades</th><th scope="col">Acción</th></tr></thead>
                    <tbody>
                        @forelse ($actividad as $evento)
                            <tr><td class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($evento['fecha'])->format('d/m/Y') }}</td>
                                <td>{{ $nombresModulo[$evento['modulo']] }} #{{ $evento['id'] }}</td><td>{{ $evento['tipo'] }}</td>
                                <td class="text-end dashboard-numero {{ ($evento['importe'] !== null && str_starts_with($evento['importe'], '-')) || ($evento['cantidad'] !== null && $evento['cantidad'] < 0) ? 'text-danger' : '' }}">{{ $evento['importe'] !== null ? $dinero($evento['importe'], $evento['moneda']) : $evento['cantidad'].' unidades' }}</td>
                                <td>@can($evento['modulo'].'.ver')<a class="btn btn-sm btn-outline-info" href="{{ route($evento['modulo'].'.show', $evento['id']) }}">Ver detalle</a>@endcan</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin operaciones validadas en el período seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div></div>
        @endif
    @else
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
            <h2 class="h5 fw-bold">Sin secciones de consulta disponibles</h2>
            <p class="text-muted mb-0">Tu usuario no tiene permisos para consultar los indicadores del Dashboard. Puedes utilizar los accesos que tu rol tenga habilitados.</p>
        </div></div>
    @endif
@endsection
