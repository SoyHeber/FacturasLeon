<?php

namespace App\Services;

use App\Models\Opcion;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportesGeneralesService
{
    public const VISTAS = [
        'ventas' => ['documentos' => 'Por documento', 'detalle' => 'Por producto'],
        'compras' => ['documentos' => 'Por documento', 'detalle' => 'Por materia prima'],
        'dte' => ['diario' => 'Resumen diario'],
        'existencias' => ['productos' => 'Productos terminados', 'materias' => 'Materias primas'],
        'movimientos' => ['productos' => 'Productos terminados', 'materias' => 'Materias primas'],
        'produccion' => ['ordenes' => 'Órdenes y reversiones'],
        'consumos' => ['materiales' => 'Consumo y reintegro'],
        'catalogos' => ['productos' => 'Productos', 'clientes' => 'Clientes', 'proveedores' => 'Proveedores', 'recetas' => 'Recetas por producto'],
    ];

    private const IMPORTES_VENTA = ['importe_bruto', 'importe_descuento', 'importe_exento', 'importe_neto', 'importe_iva', 'importe_total'];
    private const IMPORTES_COMPRA = ['importe_bruto', 'importe_descuento', 'importe_exento', 'importe_otros', 'importe_neto', 'importe_iva', 'importe_total'];

    /**
     * Describe el grano de cada fila, sus columnas, tipos y sumas permitidas.
     * Una lista cerrada gobierna tablas y campos; ningún identificador SQL procede
     * de la solicitud. Firmados son importes/cantidades reversibles, nunca precios
     * o porcentajes. Las sumas se agrupan por moneda o unidad para no mezclarlas.
     */
    public function definicion(string $reporte, string $vista): array
    {
        if (! isset(self::VISTAS[$reporte][$vista])) {
            throw new InvalidArgumentException('El reporte o su vista no están disponibles.');
        }
        $columnas = [];
        $sumas = [];
        $firmados = [];
        $grupo = null;
        $nota = '';
        $fechas = ! in_array($reporte, ['existencias', 'catalogos'], true);
        if (in_array($reporte, ['ventas', 'compras'], true)) {
            $columnas = ['fecha_evento' => ['Fecha del movimiento', 'fecha'], 'movimiento' => ['Movimiento', 'texto'],
                'documento_id' => ['ID documento', 'identificador'], 'serie' => ['Serie', 'texto'], 'numero' => ['Número', 'identificador'],
                'tercero' => [$reporte === 'ventas' ? 'Cliente' : 'Proveedor', 'texto'], 'identificacion' => ['Identificación', 'texto']];
            if ($reporte === 'ventas') {
                $columnas += ['uuid' => ['UUID FEL', 'texto'], 'ambiente' => ['Ambiente FEL', 'texto']];
            }
            $columnas += ['fecha_documento' => ['Fecha original', 'fecha'], 'moneda' => ['Moneda', 'texto']];
            if ($vista === 'detalle') {
                $columnas += ['numero_linea' => ['Línea', 'entero'], 'articulo_id' => ['ID artículo', 'identificador'],
                    'articulo' => [$reporte === 'ventas' ? 'Producto' : 'Materia prima', 'texto'], 'unidad' => ['Unidad', 'texto'],
                    'cantidad' => ['Cantidad', $reporte === 'ventas' ? 'entero' : 'decimal'],
                    'precio_unitario' => ['Precio unitario', 'decimal'], 'porcentaje_descuento' => ['Descuento %', 'decimal']];
                $firmados[] = 'cantidad';
            }
            foreach ($reporte === 'ventas' ? self::IMPORTES_VENTA : self::IMPORTES_COMPRA as $campo) {
                $columnas[$campo] = [$this->tituloImporte($campo), 'dinero'];
                $firmados[] = $campo;
                $sumas[] = $campo;
            }
            $grupo = 'moneda';
            $nota = $vista === 'detalle' ? 'Una fila por línea y evento. Los importes corresponden a esa línea; los precios y porcentajes se conservan en las anulaciones.' : 'Una fila por documento y evento. Las anulaciones confirmadas restan en su propia fecha.';
            $nota .= $reporte === 'ventas' ? ' Solo facturas que llegaron a certificarse; los borradores y respuestas inciertas quedan excluidos. El ambiente registrado se muestra sin asumir producción.' : ' Solo compras con detalles activos e inventario aplicado. Las históricas pendientes de validación quedan excluidas.';
        } elseif ($reporte === 'dte') {
            $columnas = ['fecha_evento' => ['Fecha', 'fecha'], 'moneda' => ['Moneda', 'texto'], 'emitidos' => ['DTE emitidos', 'entero'],
                'anulados' => ['DTE anulados', 'entero'], 'importe_emitido' => ['Importe emitido', 'dinero'],
                'importe_anulado' => ['Importe anulado', 'dinero'], 'importe_total' => ['Facturación neta', 'dinero']];
            $sumas = ['emitidos', 'anulados', 'importe_emitido', 'importe_anulado', 'importe_total'];
            $grupo = 'moneda';
            $nota = 'Una fila por día y moneda. Cada DTE cuenta una vez al emitirse aunque se anule después. La anulación cuenta en otra columna y su importe es negativo.';
        } elseif ($reporte === 'existencias') {
            $columnas = ['inventario_id' => ['ID inventario', 'identificador'], 'articulo_id' => ['ID artículo', 'identificador'],
                'articulo' => [$vista === 'productos' ? 'Producto' : 'Materia prima', 'texto'], 'unidad' => ['Unidad', 'texto'],
                'cantidad' => ['Existencia', $vista === 'productos' ? 'entero' : 'decimal'], 'stock_minimo' => ['Mínimo', 'decimal'],
                'stock_maximo' => ['Máximo', 'decimal'], 'ubicacion' => ['Ubicación', 'texto'], 'nivel_stock' => ['Nivel de stock', 'texto'], 'estado' => ['Estado', 'estado']];
            $sumas = ['cantidad'];
            $grupo = 'unidad';
            $nota = 'Existencias actuales al consultar o descargar. Las cantidades se resumen por unidad; no se reconstruyen saldos históricos.';
        } elseif ($reporte === 'movimientos') {
            $columnas = ['fecha_evento' => ['Fecha', 'fecha'], 'movimiento_id' => ['ID movimiento', 'identificador'],
                'inventario_id' => ['ID inventario', 'identificador'], 'articulo' => [$vista === 'productos' ? 'Producto' : 'Materia prima', 'texto'],
                'movimiento' => ['Movimiento', 'texto'], 'cantidad' => ['Variación', $vista === 'productos' ? 'entero' : 'decimal'],
                'unidad' => ['Unidad', 'texto'], 'origen' => ['Origen', 'texto'], 'documento_id' => ['ID origen', 'identificador'],
                'motivo' => ['Motivo', 'texto'], 'observacion' => ['Observación', 'texto']];
            $firmados = ['cantidad'];
            $sumas = ['cantidad'];
            $grupo = 'unidad';
            $nota = 'Movimientos activos: entradas positivas, salidas negativas y ajustes con su signo original. Se conservan las reversiones. Este historial no acredita por sí solo un saldo inicial conciliado.';
        } elseif ($reporte === 'produccion') {
            $columnas = ['fecha_evento' => ['Fecha', 'fecha'], 'documento_id' => ['ID producción', 'identificador'], 'movimiento' => ['Evento', 'texto'],
                'articulo' => ['Producto', 'texto'], 'cantidad' => ['Unidades', 'entero'], 'unidad' => ['Unidad', 'texto'], 'usuario' => ['Responsable', 'texto']];
            $firmados = ['cantidad'];
            $sumas = ['cantidad'];
            $grupo = 'unidad';
            $nota = 'Solo producciones confirmadas que ingresaron producto terminado y sus reversiones. Se excluyen borradores y registros legados sin aplicación acreditada.';
        } elseif ($reporte === 'consumos') {
            $columnas = ['fecha_evento' => ['Fecha', 'fecha'], 'documento_id' => ['ID producción', 'identificador'], 'movimiento' => ['Evento', 'texto'],
                'articulo' => ['Producto fabricado', 'texto'], 'material' => ['Materia prima histórica', 'texto'], 'material_id' => ['ID materia prima', 'identificador'],
                'cantidad' => ['Consumo neto', 'decimal'], 'unidad' => ['Unidad', 'texto'], 'cantidad_requerida' => ['Requerida por unidad', 'decimal']];
            $firmados = ['cantidad'];
            $sumas = ['cantidad'];
            $grupo = 'unidad';
            $nota = 'Consumo registrado al confirmar la producción y reintegros al anularla, con signo negativo para compensar el consumo. Las entradas físicas pueden consultarse en Movimientos de inventario.';
        } else {
            $columnas = $this->columnasCatalogo($vista);
            $nota = 'Catálogo actual con filtro de estado. Los documentos operativos históricos conservan sus referencias aunque el maestro se inactive.';
        }
        foreach ($columnas as $campo => [$titulo, $tipo]) {
            $columnas[$campo] = compact('titulo', 'tipo');
        }

        return ['titulo' => Opcion::REPORTES['reportes_'.$reporte][0], 'reporte' => $reporte, 'vista' => $vista,
            'columnas' => $columnas, 'sumas' => $sumas, 'firmados' => $firmados, 'grupo' => $grupo, 'fechas' => $fechas, 'nota' => $nota];
    }

    /**
     * Recorre por lotes el resultado filtrado y calcula sumas exactas con BigDecimal.
     * Retiene solo la página pedida; pantalla y CSV consumen el mismo generador.
     * Los filtros ya deben estar validados por el controlador. No escribe datos.
     */
    public function consultar(array $definicion, array $filtros, int $pagina, int $porPagina): array
    {
        $total = 0;
        $filas = [];
        $resumen = [];
        foreach ($this->filas($definicion, $filtros) as $fila) {
            if ($total >= ($pagina - 1) * $porPagina && count($filas) < $porPagina) {
                $filas[] = $fila;
            }
            $total++;
            $grupo = $definicion['grupo'] === null ? '' : (string) $fila[$definicion['grupo']];
            foreach ($definicion['sumas'] as $campo) {
                $resumen[$grupo][$campo] = (string) BigDecimal::of($resumen[$grupo][$campo] ?? '0')->plus($fila[$campo]);
            }
        }
        $paginacion = new LengthAwarePaginator($filas, $total, $porPagina, $pagina, ['path' => request()->url(), 'query' => request()->query()]);

        return ['filas' => $paginacion, 'resumen' => $resumen, 'total' => $total];
    }

    /**
     * Fuente única de filas para consultar y descargar. Los movimientos financieros
     * conservan valores originales en SQL; sus signos se aplican en PHP para evitar
     * pérdida binaria de centavos. Las excepciones se propagan, sin inventar ceros.
     */
    public function filas(array $definicion, array $filtros): Generator
    {
        if ($definicion['reporte'] === 'dte') {
            yield from $this->dteDiario($definicion, $filtros);
            return;
        }
        $query = $this->consulta($definicion);
        $query = $this->filtrar($query, $definicion, $filtros);
        foreach ($query->orderBy($definicion['fechas'] ? 'fecha_evento' : array_key_first($definicion['columnas']))
            ->orderBy('_orden')->orderBy('signo', 'desc')->lazy(500) as $registro) {
            yield $this->normalizar((array) $registro, $definicion);
        }
    }

    /** Devuelve únicamente consultas construidas con identificadores internos fijos. */
    private function consulta(array $definicion): Builder
    {
        $reporte = $definicion['reporte'];
        $vista = $definicion['vista'];
        $consulta = match ($reporte) {
            'ventas', 'compras' => $this->financiero($reporte, $vista),
            'existencias' => $this->existencias($vista),
            'movimientos' => $this->movimientos($vista),
            'produccion', 'consumos' => $this->produccion($reporte),
            'catalogos' => $this->catalogo($vista),
            default => throw new InvalidArgumentException('Reporte no disponible.'),
        };

        return DB::query()->fromSub($consulta, 'reporte')->select('reporte.*');
    }

    /**
     * Dos ramas por documento: emisión positiva y anulación negativa. En detalle
     * se seleccionan importes de la línea, nunca se repite el total de cabecera.
     * No filtra por el estado actual del cliente/proveedor/producto histórico.
     */
    private function financiero(string $reporte, string $vista): Builder
    {
        $venta = $reporte === 'ventas';
        $operativas = new ConsultasOperativasService;
        $base = $venta ? $operativas->ventasCertificadas() : $operativas->comprasValidadas()->join('proveedores as pv', 'pv.id', '=', 'c.proveedor_id');
        $maestro = $venta ? 'v' : 'c';
        $importe = $maestro;
        $seleccion = [$maestro.'.id as documento_id', $maestro.'.moneda'];
        if ($venta) {
            $seleccion = array_merge($seleccion, ['d.fel_serie as serie', 'd.fel_numero as numero', 'v.receptor_nombre as tercero',
                'v.receptor_identificacion as identificacion', 'd.fel_uuid as uuid', 'd.ambiente']);
        } else {
            $seleccion = array_merge($seleccion, ['c.serie', 'c.numero', 'pv.nombre as tercero', 'pv.numero_identificacion as identificacion']);
        }
        if ($vista === 'detalle') {
            if ($venta) {
                $base->join('detalles_ventas as ln', 'ln.venta_id', '=', 'v.id');
                $seleccion = array_merge($seleccion, ['ln.numero_linea', 'ln.producto_id as articulo_id', 'ln.producto_nombre as articulo', 'ln.unidad_medida as unidad']);
            } else {
                $base->join('detalles_compra as ln', 'ln.compra_id', '=', 'c.id')->where('ln.estado', true)
                    ->join('inventarios_compra as ic', 'ic.id', '=', 'ln.inventario_compra_id');
                $seleccion = array_merge($seleccion, ['ln.numero_linea', 'ln.inventario_compra_id as articulo_id', 'ic.nombre as articulo', 'ic.unidad_medida as unidad']);
            }
            $seleccion = array_merge($seleccion, ['ln.cantidad', 'ln.precio_unitario', 'ln.porcentaje_descuento']);
            $importe = 'ln';
        }
        foreach ($venta ? self::IMPORTES_VENTA : self::IMPORTES_COMPRA as $campo) {
            $seleccion[] = $importe.'.'.$campo;
        }
        $seleccion[] = ($vista === 'detalle' ? 'ln' : $maestro).'.id as _orden';
        $emision = $venta ? 'd.fecha_hora_emision' : 'c.fecha_emision';
        $anulacion = $venta ? 'a.fecha_anulacion' : 'c.fecha_anulacion';
        $base->select($seleccion)->selectRaw($this->fechaSql($emision, $venta).' AS fecha_documento');
        $positiva = (clone $base)->selectRaw($this->fechaSql($emision, $venta)." AS fecha_evento, 'EMISION' AS movimiento, 1 AS signo");
        $negativa = (clone $base)->whereNotNull($anulacion)
            ->selectRaw($this->fechaSql($anulacion, ! $venta)." AS fecha_evento, 'ANULACION' AS movimiento, -1 AS signo");
        if (! $venta) {
            $negativa->where('c.estado', false);
        }

        return $positiva->unionAll($negativa);
    }

    /** Existencias actuales, sin reconstruir saldos y sin sumar unidades incompatibles. */
    private function existencias(string $vista): Builder
    {
        $productos = $vista === 'productos';
        $q = DB::table(($productos ? 'inventarios' : 'inventarios_compra').' as i');
        if ($productos) {
            $q->join('productos as p', 'p.id', '=', 'i.producto_id');
        }
        return $q->select(['i.id as inventario_id', 'i.cantidad', 'i.stock_minimo', 'i.stock_maximo', 'i.ubicacion', 'i.id as _orden'])
            ->selectRaw($productos ? "p.id AS articulo_id, p.nombre AS articulo, 'UNIDAD' AS unidad, CASE WHEN i.estado = 1 AND p.estado = 1 THEN 1 ELSE 0 END AS estado" : 'i.id AS articulo_id, i.nombre AS articulo, i.unidad_medida AS unidad, i.estado')
            ->selectRaw("CASE WHEN i.cantidad <= i.stock_minimo THEN 'BAJO' WHEN i.stock_maximo IS NOT NULL AND i.cantidad > i.stock_maximo THEN 'ALTO' ELSE 'NORMAL' END AS nivel_stock, 1 AS signo");
    }

    /**
     * Lee movimientos que afectan existencias, con origen identificable y signo físico.
     * Los movimientos automáticos usan timestamps UTC; las fechas capturadas en los
     * formularios manuales ya son locales. Los ajustes conservan su delta firmado.
     */
    private function movimientos(string $vista): Builder
    {
        $productos = $vista === 'productos';
        $q = DB::table(($productos ? 'movimientos_inventario' : 'movimientos_inventario_compra').' as m')
            ->where('m.estado', true)->select(['m.id as movimiento_id', 'm.id as _orden', 'm.tipo_movimiento as movimiento', 'm.cantidad', 'm.motivo', 'm.observacion']);
        if ($productos) {
            $q->join('inventarios as i', 'i.id', '=', 'm.inventario_id')->join('productos as p', 'p.id', '=', 'i.producto_id')
                ->leftJoin('detalles_ventas as dv', 'dv.id', '=', 'm.detalle_venta_id')
                ->addSelect(['i.id as inventario_id', 'p.nombre as articulo'])
                ->selectRaw("'UNIDAD' AS unidad, CASE WHEN m.produccion_id IS NOT NULL THEN 'PRODUCCION' WHEN m.detalle_venta_id IS NOT NULL THEN 'VENTA' ELSE 'MANUAL' END AS origen, COALESCE(m.produccion_id, dv.venta_id) AS documento_id");
            $automatico = 'm.produccion_id IS NOT NULL OR m.detalle_venta_id IS NOT NULL';
        } else {
            $q->join('inventarios_compra as i', 'i.id', '=', 'm.inventario_compra_id')
                ->leftJoin('detalles_compra as dc', 'dc.id', '=', 'm.detalle_compra_id')
                ->addSelect(['i.id as inventario_id', 'i.nombre as articulo', 'i.unidad_medida as unidad'])
                ->selectRaw("CASE WHEN m.produccion_id IS NOT NULL THEN 'PRODUCCION' WHEN m.detalle_compra_id IS NOT NULL THEN 'COMPRA' ELSE 'MANUAL' END AS origen, COALESCE(m.produccion_id, dc.compra_id) AS documento_id");
            $automatico = 'm.produccion_id IS NOT NULL OR m.detalle_compra_id IS NOT NULL';
        }
        return $q->selectRaw('CASE WHEN '.$automatico.' THEN '.$this->fechaSql('m.fecha_movimiento').' ELSE '.$this->fechaSql('m.fecha_movimiento', false).' END AS fecha_evento')
            ->selectRaw("CASE WHEN m.tipo_movimiento = 'SALIDA' THEN -1 ELSE 1 END AS signo");
    }

    /** Producción aplicada y reversión efectiva; consumos toman las instantáneas históricas. */
    private function produccion(string $reporte): Builder
    {
        $q = (new ConsultasOperativasService)->produccionesConfirmadas()
            ->join('productos as p', 'p.id', '=', 'pr.producto_id')
            ->select(['pr.id as documento_id', 'p.nombre as articulo']);
        if ($reporte === 'consumos') {
            $q->join('consumos_produccion as cp', 'cp.produccion_id', '=', 'pr.id')
                ->addSelect(['cp.nombre_material as material', 'cp.inventario_compra_id as material_id', 'cp.cantidad_consumida as cantidad',
                    'cp.unidad_medida as unidad', 'cp.cantidad_requerida', 'cp.id as _orden']);
        } else {
            $q->leftJoin('users as uc', 'uc.id', '=', 'pr.confirmado_por_id')->leftJoin('users as ua', 'ua.id', '=', 'pr.anulado_por_id')
                ->addSelect(['pr.cantidad', 'pr.id as _orden'])->selectRaw("'UNIDAD' AS unidad");
        }
        $positiva = (clone $q)->selectRaw($this->fechaSql('pr.fecha_confirmacion')." AS fecha_evento, 'CONFIRMACION' AS movimiento, 1 AS signo");
        $negativa = (clone $q)->where('pr.estado_produccion', 'ANULADA')->whereNotNull('pr.fecha_anulacion')
            ->selectRaw($this->fechaSql('pr.fecha_anulacion')." AS fecha_evento, 'ANULACION' AS movimiento, -1 AS signo");
        if ($reporte === 'produccion') {
            $positiva->addSelect('uc.name as usuario');
            $negativa->addSelect('ua.name as usuario');
        }
        return $positiva->unionAll($negativa);
    }

    /** Columnas actuales de los catálogos; cada componente identifica su receta completa. */
    private function columnasCatalogo(string $vista): array
    {
        return match ($vista) {
            'productos' => ['id' => ['ID producto', 'identificador'], 'codigo' => ['Código', 'texto'], 'nombre' => ['Producto', 'texto'],
                'categoria' => ['Categoría', 'texto'], 'marca' => ['Marca', 'texto'], 'descripcion' => ['Descripción', 'texto'], 'estado' => ['Estado', 'estado']],
            'clientes', 'proveedores' => ['id' => ['ID', 'identificador'], 'nombre' => ['Nombre', 'texto'], 'identificacion' => ['Identificación', 'texto'],
                'telefono' => ['Teléfono', 'texto'], 'correo' => ['Correo', 'texto'], 'estado' => ['Estado', 'estado']],
            'recetas' => ['material_producto_id' => ['ID material en receta', 'identificador'], 'producto_id' => ['ID producto', 'identificador'],
                'producto' => ['Producto', 'texto'], 'material_id' => ['ID materia prima', 'identificador'], 'material' => ['Materia prima', 'texto'],
                'cantidad_requerida' => ['Requerida por unidad', 'decimal'], 'unidad' => ['Unidad', 'texto'], 'estado' => ['Estado receta', 'estado'],
                'estado_material' => ['Estado componente', 'texto'], 'observacion' => ['Observación', 'texto']],
        };
    }

    /** Consulta catálogos sin tocar datos; no aplica su estado a documentos históricos. */
    private function catalogo(string $vista): Builder
    {
        if ($vista === 'productos') {
            return DB::table('productos as p')->leftJoin('categorias as ca', 'ca.id', '=', 'p.categoria_id')->leftJoin('marcas as ma', 'ma.id', '=', 'p.marca_id')
                ->select(['p.id', 'p.codigo', 'p.nombre', 'ca.nombre as categoria', 'ma.nombre as marca', 'p.descripcion', 'p.estado', 'p.id as _orden'])->selectRaw('1 AS signo');
        }
        if ($vista === 'recetas') {
            return DB::table('materiales_producto as mp')->join('productos as p', 'p.id', '=', 'mp.producto_id')
                ->join('inventarios_compra as ic', 'ic.id', '=', 'mp.inventario_compra_id')
                ->select(['mp.id as material_producto_id', 'p.id as producto_id', 'p.nombre as producto', 'ic.id as material_id', 'ic.nombre as material',
                    'mp.cantidad_requerida', 'ic.unidad_medida as unidad', 'p.receta_activa as estado', 'mp.observacion', 'mp.id as _orden'])
                ->selectRaw("CASE WHEN mp.estado = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado_material, 1 AS signo");
        }
        $q = DB::table(($vista === 'clientes' ? 'clientes' : 'proveedores').' as c')
            ->select(['c.id', 'c.numero_identificacion as identificacion', 'c.telefono', 'c.correo', 'c.estado', 'c.id as _orden'])->selectRaw('1 AS signo');
        if ($vista === 'proveedores') {
            return $q->addSelect('c.nombre');
        }
        $q->leftJoin('personas as pe', 'pe.cliente_id', '=', 'c.id')->leftJoin('sociedades as so', 'so.cliente_id', '=', 'c.id');
        $partes = array_map(fn ($c) => "NULLIF(TRIM(pe.$c), '')", ['nombre1', 'nombre2', 'nombre3', 'apellido1', 'apellido2', 'apellido_casada']);
        // Omite nombres opcionales vacíos, como hace el catálogo de clientes.
        $nombre = DB::connection()->getDriverName() === 'sqlite'
            ? implode(' || ', array_map(fn ($p) => "COALESCE($p || ' ', '')", $partes))
            : "CONCAT_WS(' ', ".implode(', ', $partes).')';
        return $q->selectRaw("COALESCE(so.nombre, TRIM($nombre)) AS nombre");
    }

    /** Filtros tipados sobre aliases controlados; cantidades firmadas coinciden con lo visible. */
    private function filtrar(Builder $q, array $definicion, array $filtros): Builder
    {
        if ($definicion['fechas']) {
            $q->whereBetween('fecha_evento', [$filtros['desde'], $filtros['hasta']]);
        }
        foreach ($filtros['filtros'] ?? [] as $campo => $valor) {
            if ($valor === '' || $valor === null) {
                continue;
            }
            $tipo = $definicion['columnas'][$campo]['tipo'];
            if (in_array($tipo, ['entero', 'decimal', 'dinero'], true)) {
                $signo = in_array($campo, $definicion['firmados'], true) ? ' * signo' : '';
                $q->whereRaw("CAST($campo AS DECIMAL(30,10))$signo = CAST(? AS DECIMAL(30,10))", [$valor]);
            } elseif ($tipo === 'texto') {
                // Busca texto literal: % y _ escritos por el usuario no son comodines SQL.
                $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor);
                $q->whereRaw("$campo LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
            } else {
                $q->where($campo, $valor);
            }
        }
        return $q;
    }

    /** Conserva decimales exactos; una cifra inválida interrumpe el reporte en vez de ocultarse. */
    private function normalizar(array $registro, array $definicion): array
    {
        $fila = [];
        foreach ($definicion['columnas'] as $campo => $columna) {
            $valor = $registro[$campo];
            $tipo = $columna['tipo'];
            if ($valor !== null && in_array($tipo, ['entero', 'decimal', 'dinero'], true)) {
                $numero = BigDecimal::of((string) $valor);
                if (in_array($campo, $definicion['firmados'], true) && (int) $registro['signo'] === -1) {
                    $numero = $numero->negated();
                }
                $valor = (string) $numero->toScale(match ($tipo) {'dinero' => 2, 'decimal' => 10, default => 0});
            } elseif ($tipo === 'estado') {
                $valor = (int) $valor === 1 ? 'Activo' : 'Inactivo';
            } elseif ($valor !== null) {
                $valor = (string) $valor;
            }
            $fila[$campo] = $valor;
        }
        return $fila;
    }

    /**
     * Agrupa eventos certificados por día civil y moneda con sumas decimales exactas.
     * Los días sin actividad se incluyen como cero. Primero agrega, después filtra
     * contadores/importes diarios; nunca filtra facturas por un total agregado.
     */
    private function dteDiario(array $definicion, array $filtros): Generator
    {
        $dias = [];
        $monedas = [];
        $financieros = ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']];
        foreach ($this->filas($this->definicion('ventas', 'documentos'), $financieros) as $fila) {
            $fecha = $fila['fecha_evento'];
            $moneda = $fila['moneda'];
            $monedas[$moneda] = true;
            $dias[$fecha][$moneda] ??= ['emitidos' => 0, 'anulados' => 0, 'importe_emitido' => '0.00', 'importe_anulado' => '0.00'];
            $tipo = $fila['movimiento'] === 'EMISION' ? 'emitido' : 'anulado';
            $contador = $tipo === 'emitido' ? 'emitidos' : 'anulados';
            $dias[$fecha][$moneda][$contador]++;
            $campo = 'importe_'.$tipo;
            $dias[$fecha][$moneda][$campo] = (string) BigDecimal::of($dias[$fecha][$moneda][$campo])->plus($fila['importe_total']);
        }
        $monedas = array_keys($monedas ?: ['GTQ' => true]);
        sort($monedas);
        for ($dia = CarbonImmutable::parse($filtros['desde']); $dia->toDateString() <= $filtros['hasta']; $dia = $dia->addDay()) {
            foreach ($monedas as $moneda) {
                $fila = ['fecha_evento' => $dia->toDateString(), 'moneda' => $moneda] + ($dias[$dia->toDateString()][$moneda] ?? ['emitidos' => 0, 'anulados' => 0, 'importe_emitido' => '0.00', 'importe_anulado' => '0.00']);
                $fila['importe_total'] = (string) BigDecimal::of($fila['importe_emitido'])->plus($fila['importe_anulado']);
                $fila = $this->normalizar($fila + ['signo' => 1], $definicion);
                $incluir = true;
                foreach ($filtros['filtros'] ?? [] as $campo => $valor) {
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    $tipo = $definicion['columnas'][$campo]['tipo'];
                    $coincide = in_array($tipo, ['entero', 'dinero'], true) ? BigDecimal::of($fila[$campo])->isEqualTo($valor)
                        : ($tipo === 'texto' ? mb_stripos($fila[$campo], $valor) !== false : $fila[$campo] === $valor);
                    $incluir = $incluir && $coincide;
                }
                if ($incluir) {
                    yield $fila;
                }
            }
        }
    }

    /** Fecha civil: timestamps de servidor en UTC; fechas documentales locales sin conversión. */
    private function fechaSql(string $campo, bool $utc = true): string
    {
        if (! $utc) {
            return "SUBSTR($campo, 1, 10)";
        }

        // Guatemala mantiene UTC-06:00 durante los períodos operativos del sistema.
        return DB::connection()->getDriverName() === 'sqlite'
            ? "DATE($campo, '-6 hours')" : "DATE(DATE_SUB($campo, INTERVAL 6 HOUR))";
    }

    /** Etiquetas de importes compartidas por encabezados, filtros y CSV. */
    private function tituloImporte(string $campo): string
    {
        return ['importe_bruto' => 'Bruto', 'importe_descuento' => 'Descuento', 'importe_exento' => 'Exento',
            'importe_otros' => 'Otros', 'importe_neto' => 'Base neta', 'importe_iva' => 'IVA', 'importe_total' => 'Total'][$campo];
    }
}
