<?php

namespace App\Http\Controllers;

use App\Services\ConsultasOperativasService;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ZONA = 'America/Guatemala';

    /**
     * Consulta el resumen operativo sin cambiar documentos, stock ni permisos.
     * Cada sección exige el permiso de consulta de su módulo antes de acceder a sus
     * tablas. Los accesos de creación son independientes de los indicadores.
     */
    public function index(Request $request): View
    {
        $periodo = $this->periodo($request);
        $usuario = $request->user();
        $permisos = [];
        foreach (['ventas', 'compras', 'producciones', 'inventarios', 'inventarios_compra'] as $modulo) {
            $permisos[$modulo] = $usuario->tienePermiso($modulo, 'ver');
        }

        $ventas = $permisos['ventas'] ? $this->ventas($periodo) : null;
        $compras = $permisos['compras'] ? $this->compras($periodo) : null;
        $produccion = $permisos['producciones'] ? $this->produccion($periodo) : null;
        $inventarios = [];
        foreach (['inventarios', 'inventarios_compra'] as $modulo) {
            if ($permisos[$modulo]) {
                $inventarios[$modulo] = $this->inventario($modulo);
            }
        }
        $actividad = collect(array_merge($ventas['eventos'] ?? [], $compras['eventos'] ?? [], $produccion['eventos'] ?? []))
            ->sortByDesc(fn ($evento) => $evento['instante'].'-'.str_pad((string) $evento['id'], 20, '0', STR_PAD_LEFT))
            ->take(8)->values();

        return view('dashboard', compact('periodo', 'permisos', 'ventas', 'compras', 'produccion', 'inventarios', 'actividad'));
    }

    /**
     * Valida un intervalo inclusivo de hasta 366 días civiles de Guatemala.
     * Convierte sus límites a UTC con fin exclusivo para los eventos guardados
     * con now(); conserva límites locales para fechas documentales sin zona.
     */
    private function periodo(Request $request): array
    {
        $hoy = CarbonImmutable::now(self::ZONA)->startOfDay();
        $datos = $request->validate([
            'periodo' => ['nullable', Rule::in(['mes', 'hoy', 'personalizado'])],
            'desde' => ['required_if:periodo,personalizado', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy->toDateString()],
            'hasta' => ['required_if:periodo,personalizado', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:'.$hoy->toDateString()],
        ]);
        $seleccion = $datos['periodo'] ?? 'mes';
        $desde = $seleccion === 'personalizado' ? CarbonImmutable::parse($datos['desde'], self::ZONA) : ($seleccion === 'hoy' ? $hoy : $hoy->startOfMonth());
        $hasta = $seleccion === 'personalizado' ? CarbonImmutable::parse($datos['hasta'], self::ZONA) : $hoy;
        if ($desde->diffInDays($hasta) > 365) {
            throw ValidationException::withMessages(['hasta' => 'Selecciona un período de hasta 366 días.']);
        }

        return ['seleccion' => $seleccion, 'desde' => $desde, 'hasta' => $hasta,
            'inicio_utc' => $desde->utc()->format('Y-m-d H:i:s'), 'fin_utc' => $hasta->addDay()->utc()->format('Y-m-d H:i:s'),
            'inicio_local' => $desde->format('Y-m-d H:i:s'), 'fin_local' => $hasta->addDay()->format('Y-m-d H:i:s')];
    }

    /**
     * Conserva cada emisión que obtuvo certificación, aunque hoy esté anulada.
     * La emisión cuenta un DTE en su fecha fiscal; una anulación FEL CONFIRMADA
     * o APLICADA aporta otro evento negativo en la fecha solicitada y confirmada.
     * Una respuesta incierta o la cancelación local de un borrador no resta ventas.
     * La certeza fiscal y la aplicación del reintegro se consultan por separado.
     */
    private function ventas(array $periodo): array
    {
        $filas = (new ConsultasOperativasService)->ventasCertificadas()
            ->where(function (Builder $query) use ($periodo) {
                $query->where(fn ($q) => $q->where('d.fecha_hora_emision', '>=', $periodo['inicio_utc'])->where('d.fecha_hora_emision', '<', $periodo['fin_utc']))
                    ->orWhere(fn ($q) => $q->where('a.fecha_anulacion', '>=', $periodo['desde']->toDateString())->where('a.fecha_anulacion', '<=', $periodo['hasta']->toDateString()));
            })
            ->get(['v.id', 'v.moneda', 'v.importe_total', 'd.fecha_hora_emision', 'd.ambiente', 'a.fecha_anulacion']);

        $eventos = [];
        foreach ($filas as $fila) {
            $eventos[] = $this->evento('ventas', $fila->id, 'Emisión FEL', $fila->fecha_hora_emision, $fila->importe_total, $fila->moneda);
            if ($fila->fecha_anulacion !== null) {
                $eventos[] = $this->evento('ventas', $fila->id, 'Anulación FEL', $fila->fecha_anulacion, (string) BigDecimal::of($fila->importe_total)->negated(), $fila->moneda, true);
            }
        }
        $eventos = $this->enPeriodo($eventos, $periodo);
        $dias = [];
        for ($dia = $periodo['desde']; $dia->lte($periodo['hasta']); $dia = $dia->addDay()) {
            $dias[$dia->toDateString()] = ['fecha' => $dia->toDateString(), 'emitidos' => 0, 'anulados' => 0, 'importes' => []];
        }
        $resumen = $this->importes($eventos);
        foreach ($eventos as $evento) {
            $clave = $evento['tipo'] === 'Emisión FEL' ? 'emitidos' : 'anulados';
            $dias[$evento['fecha']][$clave]++;
        }
        $porDia = collect($eventos)->groupBy('fecha');
        foreach ($dias as $fecha => &$dia) {
            $dia['importes'] = $this->importes($porDia->get($fecha, collect())->all());
            foreach ($resumen as $moneda => $importes) {
                $dia['importes'][$moneda] ??= ['emitido' => '0.00', 'anulado' => '0.00', 'neto' => '0.00'];
            }
        }
        unset($dia);

        return ['eventos' => $eventos, 'importes' => $resumen, 'dias' => $dias,
            'emitidos' => array_sum(array_column($dias, 'emitidos')), 'anulados' => array_sum(array_column($dias, 'anulados')),
            'sin_ambiente' => $filas->whereNull('ambiente')->count(), 'ambientes' => $filas->pluck('ambiente')->filter()->unique()->values()->all(),
            'alertas' => $this->alertasFel(), 'graficos' => $this->graficos($dias, array_keys($resumen))];
    }

    /**
     * Separa documentos históricos por revisar de compras con inventario aplicado
     * y detalles registrados. La fecha de emisión de compra es documental local;
     * su anulación operativa se guarda en UTC. Los importes nunca mezclan monedas.
     */
    private function compras(array $periodo): array
    {
        $query = DB::table('compras as c')->where(function ($query) use ($periodo) {
            $query->where(fn ($q) => $q->where('c.fecha_emision', '>=', $periodo['inicio_local'])->where('c.fecha_emision', '<', $periodo['fin_local']))
                ->orWhere(fn ($q) => $q->where('c.fecha_anulacion', '>=', $periodo['inicio_utc'])->where('c.fecha_anulacion', '<', $periodo['fin_utc']));
        });
        $historicas = (clone $query)->where('c.inventario_aplicado', false)->count();
        $filas = (new ConsultasOperativasService)->comprasValidadas($query)
            ->get(['c.id', 'c.importe_total', 'c.moneda', 'c.fecha_emision', 'c.fecha_anulacion', 'c.estado']);
        $eventos = [];
        foreach ($filas as $fila) {
            $eventos[] = $this->evento('compras', $fila->id, 'Registro de compra', $fila->fecha_emision, $fila->importe_total, $fila->moneda, true);
            if (! $fila->estado && $fila->fecha_anulacion !== null) {
                $eventos[] = $this->evento('compras', $fila->id, 'Anulación de compra', $fila->fecha_anulacion, (string) BigDecimal::of($fila->importe_total)->negated(), $fila->moneda);
            }
        }
        $eventos = $this->enPeriodo($eventos, $periodo);

        return ['eventos' => $eventos, 'importes' => $this->importes($eventos), 'historicas' => $historicas];
    }

    /**
     * Cuenta confirmaciones y reversiones que afectaron producto terminado.
     * Una producción cancelada desde BORRADOR no representa unidades fabricadas.
     */
    private function produccion(array $periodo): array
    {
        $filas = (new ConsultasOperativasService)->produccionesConfirmadas()
            ->where(function ($query) use ($periodo) {
                $query->where(fn ($q) => $q->where('fecha_confirmacion', '>=', $periodo['inicio_utc'])->where('fecha_confirmacion', '<', $periodo['fin_utc']))
                    ->orWhere(fn ($q) => $q->where('fecha_anulacion', '>=', $periodo['inicio_utc'])->where('fecha_anulacion', '<', $periodo['fin_utc']));
            })->get(['id', 'cantidad', 'fecha_confirmacion', 'fecha_anulacion', 'estado_produccion']);
        $eventos = [];
        foreach ($filas as $fila) {
            $eventos[] = $this->evento('producciones', $fila->id, 'Producción confirmada', $fila->fecha_confirmacion, null, null, false, (int) $fila->cantidad);
            if ($fila->estado_produccion === 'ANULADA' && $fila->fecha_anulacion !== null) {
                $eventos[] = $this->evento('producciones', $fila->id, 'Reversión de producción', $fila->fecha_anulacion, null, null, false, -(int) $fila->cantidad);
            }
        }
        $eventos = $this->enPeriodo($eventos, $periodo);
        $confirmaciones = array_filter($eventos, fn ($evento) => $evento['cantidad'] > 0);
        $reversiones = array_filter($eventos, fn ($evento) => $evento['cantidad'] < 0);

        return ['eventos' => $eventos, 'ordenes' => count($confirmaciones), 'reversiones' => count($reversiones),
            'unidades' => array_sum(array_column($confirmaciones, 'cantidad')), 'revertidas' => -array_sum(array_column($reversiones, 'cantidad')),
            'neto' => array_sum(array_column($eventos, 'cantidad'))];
    }

    /**
     * Obtiene alertas actuales, independientes del período. Incluye stock cero
     * aun con mínimo cero y evita sumar gramos con unidades. Cuenta todas las
     * referencias y muestra hasta cinco por inventario, con agotados primero.
     * CAST mantiene la comparación numérica también en las pruebas SQLite.
     */
    private function inventario(string $modulo): array
    {
        $query = DB::table($modulo.' as i')->where('i.estado', true);
        $columnas = ['i.id', 'i.cantidad', 'i.stock_minimo'];
        if ($modulo === 'inventarios') {
            $query->join('productos as p', 'p.id', '=', 'i.producto_id')->where('p.estado', true);
            $columnas[] = 'p.nombre';
        } else {
            $columnas[] = 'i.nombre';
            $columnas[] = 'i.unidad_medida';
        }
        $query->whereRaw('(CAST(i.cantidad AS DECIMAL(30,10)) = 0 OR CAST(i.cantidad AS DECIMAL(30,10)) < CAST(i.stock_minimo AS DECIMAL(30,10)))');

        return ['total' => (clone $query)->count(),
            'agotados' => (clone $query)->whereRaw('CAST(i.cantidad AS DECIMAL(30,10)) = 0')->count(),
            'items' => $query->orderByRaw('CASE WHEN CAST(i.cantidad AS DECIMAL(30,10)) = 0 THEN 0 ELSE 1 END')
                ->orderBy($modulo === 'inventarios' ? 'p.nombre' : 'i.nombre')->orderBy('i.id')->limit(5)->get($columnas)];
    }

    /** Señala documentos que requieren revisión; solo genera enlaces de consulta. */
    private function alertasFel(): array
    {
        $query = DB::table('ventas as v')->join('documentos_fel as d', 'd.venta_id', '=', 'v.id')
            ->leftJoin('anulaciones_venta as a', 'a.venta_id', '=', 'v.id')->where('v.estado_venta', 'CONFIRMADA')
            ->where(fn ($q) => $q->whereIn('d.estado_fel', ['PENDIENTE', 'EN_PROCESO', 'ERROR', 'INCIERTA'])
                ->orWhereIn('a.estado', ['EN_PROCESO', 'RECHAZADA', 'INCIERTA', 'CONFIRMADA']));

        return ['total' => (clone $query)->count(), 'items' => $query->orderBy('v.id')->limit(5)
            ->get(['v.id', 'd.estado_fel', 'a.estado as estado_anulacion'])];
    }

    /** Normaliza un evento; solo las fechas documentales locales omiten conversión UTC. */
    private function evento(string $modulo, int $id, string $tipo, string $fecha, ?string $importe, ?string $moneda, bool $local = false, ?int $cantidad = null): array
    {
        $instante = CarbonImmutable::parse($fecha, $local ? self::ZONA : 'UTC')->setTimezone(self::ZONA);

        return ['modulo' => $modulo, 'id' => $id, 'tipo' => $tipo, 'fecha' => $instante->toDateString(),
            'instante' => $instante->format('Y-m-d H:i:s'), 'importe' => $importe, 'moneda' => $moneda, 'cantidad' => $cantidad];
    }

    /** Conserva únicamente eventos del intervalo civil inclusivo elegido. */
    private function enPeriodo(array $eventos, array $periodo): array
    {
        return array_values(array_filter($eventos, fn ($evento) => $evento['fecha'] >= $periodo['desde']->toDateString() && $evento['fecha'] <= $periodo['hasta']->toDateString()));
    }

    /** Suma importes exactos a dos decimales por moneda, sin conversiones ni floats. */
    private function importes(array $eventos): array
    {
        $resultado = [];
        foreach ($eventos as $evento) {
            $moneda = $evento['moneda'];
            $resultado[$moneda] ??= ['emitido' => '0.00', 'anulado' => '0.00', 'neto' => '0.00'];
            $importe = BigDecimal::of($evento['importe']);
            $clave = str_contains($evento['tipo'], 'Anulación') ? 'anulado' : 'emitido';
            $resultado[$moneda][$clave] = (string) BigDecimal::of($resultado[$moneda][$clave])->plus($importe->abs())->toScale(2);
            $resultado[$moneda]['neto'] = (string) BigDecimal::of($resultado[$moneda]['neto'])->plus($importe)->toScale(2);
        }
        ksort($resultado);

        return $resultado;
    }

    /**
     * Proyecta las series a coordenadas SVG por moneda. Los floats se utilizan
     * únicamente para dibujar; las cifras financieras proceden de BigDecimal.
     */
    private function graficos(array $dias, array $monedas): array
    {
        $graficos = [];
        foreach ($monedas as $moneda) {
            $valores = array_map(fn ($dia) => $dia['importes'][$moneda] ?? ['emitido' => '0.00', 'anulado' => '0.00', 'neto' => '0.00'], array_values($dias));
            $maximo = max(1, ...array_map(fn ($valor) => (float) $valor['emitido'], $valores));
            $minimo = min(0, ...array_map(fn ($valor) => -(float) $valor['anulado'], $valores));
            $y = fn ($importe) => round(15 + ($maximo - $importe) / ($maximo - $minimo) * 165, 2);
            $puntos = ['emitido' => [], 'anulado' => [], 'neto' => []];
            foreach ($valores as $indice => $valor) {
                $x = count($valores) === 1 ? 400 : 80 + $indice / (count($valores) - 1) * 640;
                foreach ($puntos as $serie => &$coordenadas) {
                    $importe = (float) $valor[$serie] * ($serie === 'anulado' ? -1 : 1);
                    $coordenadas[] = round($x, 2).','.$y($importe);
                }
                unset($coordenadas);
            }
            $graficos[$moneda] = ['maximo' => $maximo, 'minimo' => $minimo, 'cero' => $y(0),
                'puntos' => array_map(fn ($puntos) => implode(' ', $puntos), $puntos)];
        }

        return $graficos;
    }
}
