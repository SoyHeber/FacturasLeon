<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportesGeneralesController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\Opcion;
use App\Models\User;
use App\Services\ExportadorCsvService;
use App\Services\ReportesGeneralesService;
use Carbon\CarbonImmutable;
use Database\Seeders\ReportesGeneralesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\VentasTestCase;

class ReportesGeneralesTest extends VentasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Única conexión, sin bootstrap Laravel ni .env: nunca abre MySQL/Ainnova.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 15:00:00', 'UTC'));
        \Illuminate\Container\Container::getInstance()->instance('translator', \Illuminate\Container\Container::getInstance()->make('validator')->getTranslator());
        Schema::table('users', fn (Blueprint $t) => $t->boolean('estado')->default(true));
        Schema::table('productos', fn (Blueprint $t) => $t->boolean('receta_activa')->default(true));
        Schema::table('movimientos_inventario', function (Blueprint $t) {
            $t->integer('produccion_id')->nullable();
            $t->integer('detalle_venta_id')->nullable();
        });
        Schema::create('proveedores', function (Blueprint $t) {
            $t->id();
            foreach (['nombre', 'numero_identificacion', 'telefono', 'correo'] as $c) $t->string($c)->nullable();
            $t->boolean('estado')->default(true);
        });
        Schema::create('inventarios_compra', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('unidad_medida');
            foreach (['cantidad', 'stock_minimo'] as $c) $t->string($c)->default('0.0000000000');
            $t->string('stock_maximo')->nullable();
            $t->string('ubicacion')->nullable();
            $t->boolean('estado')->default(true);
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proveedor_id')->constrained('proveedores');
            $t->string('serie')->default('A');
            $t->string('numero')->default('0001');
            $t->string('moneda')->default('GTQ');
            $t->dateTime('fecha_emision');
            $t->dateTime('fecha_anulacion')->nullable();
            $t->boolean('estado')->default(true);
            $t->boolean('inventario_aplicado')->default(true);
            $this->importes($t);
        });
        Schema::create('detalles_compra', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compra_id')->constrained('compras');
            $t->foreignId('inventario_compra_id')->constrained('inventarios_compra');
            $t->integer('numero_linea');
            $t->string('cantidad')->default('1.0000000000');
            $t->string('precio_unitario')->default('10.0000000000');
            $t->string('porcentaje_descuento')->default('0.0000');
            $t->boolean('estado')->default(true);
            $this->importes($t);
        });
        Schema::create('producciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('producto_id')->constrained('productos');
            $t->integer('cantidad')->default(2);
            $t->string('estado_produccion')->default('CONFIRMADA');
            $t->boolean('inventario_aplicado')->default(true);
            $t->boolean('producto_terminado_aplicado')->default(true);
            $t->dateTime('fecha_confirmacion')->nullable();
            $t->dateTime('fecha_anulacion')->nullable();
            $t->integer('confirmado_por_id')->nullable();
            $t->integer('anulado_por_id')->nullable();
        });
        Schema::create('consumos_produccion', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produccion_id')->constrained('producciones');
            $t->integer('inventario_compra_id');
            $t->string('nombre_material');
            $t->string('unidad_medida');
            $t->string('cantidad_consumida');
            $t->string('cantidad_requerida');
        });
        Schema::create('materiales_producto', function (Blueprint $t) {
            $t->id();
            $t->integer('producto_id');
            $t->integer('inventario_compra_id');
            $t->string('cantidad_requerida');
            $t->string('observacion')->nullable();
            $t->boolean('estado')->default(true);
        });
        Schema::create('movimientos_inventario_compra', function (Blueprint $t) {
            $t->id();
            $t->integer('inventario_compra_id');
            $t->integer('detalle_compra_id')->nullable();
            $t->integer('produccion_id')->nullable();
            $t->string('tipo_movimiento');
            $t->string('cantidad');
            $t->dateTime('fecha_movimiento');
            $t->string('motivo')->nullable();
            $t->string('observacion')->nullable();
            $t->boolean('estado')->default(true);
        });
        DB::table('proveedores')->insert(['id' => 1, 'nombre' => 'Proveedor de prueba', 'numero_identificacion' => '001234']);
        DB::table('inventarios_compra')->insert([
            ['id' => 1, 'nombre' => 'Alambre de plata 925', 'unidad_medida' => 'GRAMO', 'cantidad' => '7.1234567890'],
            ['id' => 2, 'nombre' => 'Broche', 'unidad_medida' => 'UNIDAD', 'cantidad' => '5.0000000000'],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_todas_las_vistas_consultan_y_renderizan_incluso_sin_operaciones(): void
    {
        $antes = $this->fotoOperativa();
        foreach (ReportesGeneralesService::VISTAS as $reporte => $vistas) {
            foreach (array_keys($vistas) as $vista) {
                $pagina = (new ReportesGeneralesController)->index($this->request(['vista' => $vista], 'GET'), $reporte);
                $html = $pagina->render();
                $this->assertStringContainsString('Descargar CSV', $html, $reporte.' '.$vista);
                $this->assertStringContainsString('filtros-reportes', $html);
                $this->assertLessThan(strpos($html, 'name="filtros['), strpos($html, 'class="table-dark"'));
            }
        }
        $this->assertSame($antes, $this->fotoOperativa());
    }

    public function test_excluye_certificaciones_incompletas_y_no_resta_anulaciones_inciertas(): void
    {
        $sinXml = $this->certificada('2026-10-02 12:00:00');
        DB::table('documentos_fel')->where('venta_id', $sinXml)->update(['xml_certificado' => null]);
        $incierta = $this->certificada('2026-10-02 13:00:00');
        $this->anular($incierta, '2026-10-04');
        DB::table('ventas')->where('id', $incierta)->update(['estado_venta' => 'CONFIRMADA']);
        DB::table('documentos_fel')->where('venta_id', $incierta)->update(['estado_fel' => 'CERTIFICADA']);
        DB::table('anulaciones_venta')->where('venta_id', $incierta)->update(['estado' => 'INCIERTA', 'fecha_confirmacion' => null]);
        $filas = $this->filas('ventas', 'documentos');
        $this->assertCount(1, $filas);
        $this->assertSame('EMISION', $filas[0]['movimiento']);
        $this->assertSame((string) $incierta, $filas[0]['documento_id']);
    }

    public function test_instala_metadatos_sin_dar_acceso_ni_reordenar_dos_veces(): void
    {
        DB::table('opciones')->whereIn('ruta', array_keys(Opcion::REPORTES))->delete();
        DB::table('modulos')->where('nombre', 'Reportes Generales')->delete();
        DB::table('modulos')->where('orden', '>', 4)->decrement('orden');
        $antes = User::findOrFail(7)->permisos();
        $opciones = DB::table('opciones')->orderBy('id')->get()->toJson();
        $seeder = new ReportesGeneralesSeeder;
        $seeder->run();
        $this->assertSame($antes, User::findOrFail(7)->permisos());
        $this->assertSame($opciones, DB::table('opciones')->whereNotIn('ruta', array_keys(Opcion::REPORTES))->orderBy('id')->get()->toJson());
        $this->assertSame(['Operaciones', 'Inventario', 'Producción', 'Reportes Generales', 'Personas', 'Catálogos', 'Seguridad'], DB::table('modulos')->orderBy('orden')->pluck('nombre')->all());
        $modulos = DB::table('modulos')->orderBy('id')->get()->toJson();
        $seeder->run();
        $this->assertSame($modulos, DB::table('modulos')->orderBy('id')->get()->toJson());
        foreach (array_keys(Opcion::REPORTES) as $ruta) {
            $this->assertSame(['ver'], Opcion::where('ruta', $ruta)->firstOrFail()->acciones()->pluck('clave')->all());
        }
    }

    public function test_ventas_certificadas_y_anulaciones_por_fecha_sin_borradores_ni_duplicar_cabeceras(): void
    {
        $id = $this->certificada('2026-10-01 05:59:59');
        $this->anular($id, '2026-10-03');
        $this->crearVenta();
        DB::table('productos')->where('id', 31)->update(['estado' => false]);
        $filas = $this->filas('ventas', 'documentos');
        $this->assertCount(1, $filas);
        $this->assertSame('ANULACION', $filas[0]['movimiento']);
        $this->assertSame('-201.60', $filas[0]['importe_total']);
        $this->assertSame('2026-09-30', $filas[0]['fecha_documento']);
        $detalle = $this->filas('ventas', 'detalle');
        $this->assertSame('-2', $detalle[0]['cantidad']);
        $this->assertSame('112.0000000000', $detalle[0]['precio_unitario']);
        $this->assertSame('10.0000000000', $detalle[0]['porcentaje_descuento']);
        $this->assertSame('-201.60', $detalle[0]['importe_total']);
        $this->assertSame($detalle, $this->filas('ventas', 'detalle', ['filtros' => ['importe_total' => '-201.60']]));
        $this->assertSame([], $this->filas('ventas', 'detalle', ['filtros' => ['importe_total' => '201.60']]));
    }

    public function test_dte_cuenta_la_emision_una_vez_y_anulacion_con_otro_dia_e_importe_negativo(): void
    {
        $id = $this->certificada('2026-10-02 05:59:59');
        $this->anular($id, '2026-10-03');
        $this->crearVenta();
        $dias = $this->filas('dte', 'diario', ['hasta' => '2026-10-03']);
        $this->assertCount(3, $dias);
        $this->assertSame('1', $dias[0]['emitidos']);
        $this->assertSame('0', $dias[1]['emitidos']);
        $this->assertSame('1', $dias[2]['anulados']);
        $this->assertSame('-201.60', $dias[2]['importe_anulado']);
        $this->assertSame([$dias[2]], $this->filas('dte', 'diario', ['hasta' => '2026-10-03', 'filtros' => ['anulados' => '1']]));
    }

    public function test_compras_excluye_legados_y_suma_importes_exactos_de_lineas_sin_repetir_total(): void
    {
        $id = $this->compra(['importe_total' => '999999999999.99', 'estado' => false, 'fecha_anulacion' => '2026-10-04 05:59:59']);
        $this->compra(['inventario_aplicado' => false]);
        DB::table('detalles_compra')->where('compra_id', $id)->update(['importe_total' => '0.10']);
        DB::table('detalles_compra')->insert(['compra_id' => $id, 'inventario_compra_id' => 2, 'numero_linea' => 2, 'importe_total' => '0.20']);
        DB::table('proveedores')->where('id', 1)->update(['estado' => false]);
        $this->assertCount(2, $this->filas('compras', 'documentos'));
        $datos = $this->pagina('compras', ['vista' => 'detalle', 'hasta' => '2026-10-01']);
        $this->assertSame('0.30', $datos['resumen']['GTQ']['importe_total']);
        $this->assertCount(2, $datos['filas']);
        $this->assertSame('2026-10-03', $this->filas('compras', 'documentos')[1]['fecha_evento']);
        $this->assertSame('-999999999999.99', $this->filas('compras', 'documentos')[1]['importe_total']);
    }

    public function test_existencias_separa_unidades_y_filtra_estado_actual(): void
    {
        DB::table('inventarios_compra')->where('id', 2)->update(['estado' => false]);
        $activos = $this->pagina('existencias', ['vista' => 'materias']);
        $this->assertSame(1, $activos['total']);
        $this->assertSame('7.1234567890', $activos['resumen']['GRAMO']['cantidad']);
        $todos = $this->pagina('existencias', ['vista' => 'materias', 'filtros' => ['estado' => '']]);
        $this->assertSame(2, $todos['total']);
        $this->assertArrayHasKey('UNIDAD', $todos['resumen']);
    }

    public function test_produccion_y_consumos_conservan_snapshot_y_reversion_sin_borradores(): void
    {
        $id = DB::table('producciones')->insertGetId(['producto_id' => 31, 'fecha_confirmacion' => '2026-10-02 05:59:59',
            'fecha_anulacion' => '2026-10-04 06:00:00', 'estado_produccion' => 'ANULADA', 'confirmado_por_id' => 7, 'anulado_por_id' => 7]);
        DB::table('producciones')->insert(['producto_id' => 31, 'estado_produccion' => 'BORRADOR', 'inventario_aplicado' => false]);
        DB::table('consumos_produccion')->insert(['produccion_id' => $id, 'inventario_compra_id' => 1, 'nombre_material' => 'Plata histórica',
            'unidad_medida' => 'GRAMO', 'cantidad_consumida' => '0.1234567890', 'cantidad_requerida' => '0.0617283945']);
        $producciones = $this->filas('produccion', 'ordenes');
        $this->assertSame(['2', '-2'], array_column($producciones, 'cantidad'));
        $this->assertSame(['2026-10-01', '2026-10-04'], array_column($producciones, 'fecha_evento'));
        $consumos = $this->filas('consumos', 'materiales');
        $this->assertSame(['0.1234567890', '-0.1234567890'], array_column($consumos, 'cantidad'));
        $this->assertSame('Plata histórica', $consumos[1]['material']);
        $this->assertSame('0.0617283945', $consumos[1]['cantidad_requerida']);
    }

    public function test_movimientos_distingue_fechas_manuales_y_automaticas_y_signos(): void
    {
        $compra = $this->compra();
        $linea = DB::table('detalles_compra')->where('compra_id', $compra)->value('id');
        foreach ([['ENTRADA', '1.5', null, true], ['SALIDA', '0.1', $linea, true], ['AJUSTE', '-0.2', null, true], ['ENTRADA', '100', null, false]] as [$tipo, $cantidad, $detalle, $estado]) {
            DB::table('movimientos_inventario_compra')->insert(['inventario_compra_id' => 1, 'tipo_movimiento' => $tipo,
                'cantidad' => $cantidad, 'detalle_compra_id' => $detalle, 'fecha_movimiento' => '2026-10-02 05:00:00', 'estado' => $estado]);
        }
        $filas = $this->filas('movimientos', 'materias');
        $this->assertCount(3, $filas);
        $this->assertSame('2026-10-01', $filas[0]['fecha_evento']);
        $this->assertSame('-0.1000000000', $filas[0]['cantidad']);
        $this->assertSame((string) $compra, $filas[0]['documento_id']);
        $this->assertSame('2026-10-02', $filas[1]['fecha_evento']);
        $this->assertSame('-0.2000000000', $filas[2]['cantidad']);
    }

    public function test_receta_inactiva_conserva_componentes_y_filtros_independientes(): void
    {
        DB::table('productos')->where('id', 31)->update(['receta_activa' => false]);
        DB::table('materiales_producto')->insert(['producto_id' => 31, 'inventario_compra_id' => 1, 'cantidad_requerida' => '0.5', 'estado' => true]);
        $this->assertSame(0, $this->pagina('catalogos', ['vista' => 'recetas'])['total']);
        $datos = $this->pagina('catalogos', ['vista' => 'recetas', 'filtros' => ['estado' => '0']]);
        $this->assertSame(1, $datos['total']);
        $this->assertSame('Inactivo', $datos['filas'][0]['estado']);
        $this->assertSame('Activo', $datos['filas'][0]['estado_material']);
    }

    public function test_catalogo_clientes_compone_nombres_sin_huecos_y_busca_texto_literal(): void
    {
        $datos = $this->pagina('catalogos', ['vista' => 'clientes', 'filtros' => ['nombre' => 'Ana María López Pérez']]);
        $this->assertSame(1, $datos['total']);
        $this->assertSame('Ana María López Pérez', $datos['filas'][0]['nombre']);
        $this->assertSame(0, $this->pagina('catalogos', ['vista' => 'clientes', 'filtros' => ['nombre' => '%']])['total']);
        $this->assertSame(0, $this->pagina('catalogos', ['vista' => 'clientes', 'filtros' => ['nombre' => '_']])['total']);
    }

    public function test_exporta_todo_el_resultado_con_los_mismos_filtros_y_bom_sin_totales_duplicados(): void
    {
        // Supera tanto la página de 25 como el lote interno de 500 registros.
        for ($i = 0; $i < 503; $i++) $this->compra(['numero' => str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        $this->assertCount(25, $this->pagina('compras')['filas']);
        $respuesta = (new ReportesGeneralesController)->exportar($this->request(['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'page' => 2], 'GET'), 'compras');
        ob_start();
        $respuesta->sendContent();
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(504, explode("\r\n", rtrim(substr($csv, 3), "\r\n")));
        $this->assertStringContainsString(';0000;', $csv);
        $this->assertStringContainsString(';0502;', $csv);
        $this->assertStringContainsString('attachment;', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_csv_escapa_formulas_textuales_y_conserva_negativos_decimales_y_saltos(): void
    {
        $destino = fopen('php://temp', 'w+b');
        $columnas = ['nombre' => ['titulo' => 'Nombre', 'tipo' => 'texto'], 'numero' => ['titulo' => 'Código', 'tipo' => 'identificador'], 'importe' => ['titulo' => 'Total', 'tipo' => 'dinero']];
        (new ExportadorCsvService)->escribir($destino, $columnas, [['nombre' => " =SUM(1;2)\n\"ñ\"", 'numero' => '+123', 'importe' => '-999999999999.99']], ',');
        rewind($destino);
        fread($destino, 3);
        fgetcsv($destino, 0, ',', '"', '');
        $fila = fgetcsv($destino, 0, ',', '"', '');
        fclose($destino);
        $this->assertSame(["' =SUM(1;2)\n\"ñ\"", "'+123", '-999999999999.99'], $fila);
    }

    public function test_valida_periodo_vista_columnas_y_separador_sin_ejecutar_sql_arbitrario(): void
    {
        foreach ([['desde' => '2025-01-01', 'hasta' => '2026-10-31'], ['desde' => '2026-10-31', 'hasta' => '2026-10-01'],
            ['vista' => 'inexistente'], ['filtros' => ['password' => 'x']], ['filtros' => ['importe_total' => '1e4']], ['separador' => '|'], ['filtros' => 'x']] as $datos) {
            $this->rechaza(fn () => $this->pagina('ventas', $datos), ValidationException::class);
        }
    }

    public function test_exportar_esta_protegido_y_ver_un_reporte_no_concede_otros_crud(): void
    {
        $opcion = Opcion::where('ruta', 'reportes_ventas')->value('id');
        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion)->delete();
        $this->rechaza(fn () => (new ReportesGeneralesController)->exportar($this->request([], 'GET'), 'ventas'), AuthorizationException::class);
        $request = $this->request([], 'GET');
        $request->setRouteResolver(fn () => $this->router->getRoutes()->getByName('reportes_compras.exportar'));
        $this->assertSame(200, (new VerificarPermiso)->handle($request, fn () => new Response)->getStatusCode());
        foreach (array_keys(Opcion::REPORTES) as $ruta) {
            $this->assertContains('permiso', $this->router->getRoutes()->getByName($ruta.'.exportar')->gatherMiddleware());
        }
    }

    public function test_acceso_exclusivo_es_repetible_sin_alterar_otros_roles_usuarios_ni_datos(): void
    {
        // Simula actualización desde antes del módulo: dos cuentas comparten Administrador.
        $reportes = Opcion::whereIn('ruta', array_keys(Opcion::REPORTES))->pluck('id');
        DB::table('roles_opciones_acciones')->whereIn('opcion_id', $reportes)->delete();
        DB::table('users')->insert(['id' => 8, 'name' => 'Otra cuenta', 'email' => 'otra@example.test', 'password' => 'sin-uso']);
        $rol = DB::table('roles_usuarios')->where('user_id', 7)->value('rol_id');
        DB::table('roles_usuarios')->insert(['user_id' => 8, 'rol_id' => $rol]);
        $otros = User::findOrFail(8)->permisos();
        $previos = User::findOrFail(7)->permisos();
        $seeder = new ReportesGeneralesSeeder;
        $seeder->run();
        $this->assertSame($previos, User::findOrFail(7)->permisos());
        $seeder->otorgarAccesoAUsuario(7);
        $this->assertSame($otros, User::findOrFail(8)->permisos());
        $nuevos = array_values(array_diff(User::findOrFail(7)->permisos(), $previos));
        $this->assertCount(8, $nuevos);
        $this->assertContains('reportes_ventas.ver', $nuevos);
        $antes = DB::table('roles_opciones_acciones')->orderBy('id')->get()->toJson();
        $seeder->run();
        $seeder->otorgarAccesoAUsuario(7);
        $this->assertSame($antes, DB::table('roles_opciones_acciones')->orderBy('id')->get()->toJson());
        $this->assertSame(2, DB::table('roles_usuarios')->where('user_id', 7)->count());
        $this->assertSame(0, DB::table('compras')->count());
    }

    public function test_rechaza_rol_exclusivo_compartido_sin_conceder_permisos(): void
    {
        DB::table('users')->insert(['id' => 8, 'name' => 'Otra cuenta', 'email' => 'otra@example.test', 'password' => 'sin-uso']);
        $rol = DB::table('roles')->insertGetId(['nombre' => 'Reportes Generales · usuario 7', 'estado' => true]);
        DB::table('roles_usuarios')->insert(['user_id' => 8, 'rol_id' => $rol]);
        $antes = DB::table('roles_opciones_acciones')->orderBy('id')->get()->toJson();
        $this->rechaza(fn () => (new ReportesGeneralesSeeder)->otorgarAccesoAUsuario(7), RuntimeException::class);
        $this->assertSame($antes, DB::table('roles_opciones_acciones')->orderBy('id')->get()->toJson());
    }

    /** Columnas TEXT en SQLite para probar precisión decimal sin afinidad binaria. */
    private function importes(Blueprint $t): void
    {
        foreach (['bruto', 'descuento', 'exento', 'otros', 'neto', 'iva', 'total'] as $c) $t->string('importe_'.$c)->default('0.00');
    }

    private function pagina(string $reporte, array $datos = []): array
    {
        return (new ReportesGeneralesController)->index($this->request($datos, 'GET'), $reporte)->getData();
    }

    private function filas(string $reporte, string $vista, array $filtros = []): array
    {
        $servicio = new ReportesGeneralesService;
        return iterator_to_array($servicio->filas($servicio->definicion($reporte, $vista), $filtros + ['desde' => '2026-10-01', 'hasta' => '2026-10-31']));
    }

    private function compra(array $datos = []): int
    {
        $id = DB::table('compras')->insertGetId($datos + ['proveedor_id' => 1, 'fecha_emision' => '2026-10-01 01:00:00', 'importe_total' => '10.00']);
        DB::table('detalles_compra')->insert(['compra_id' => $id, 'inventario_compra_id' => 1, 'numero_linea' => 1, 'importe_total' => '10.00']);
        return $id;
    }

    private function certificada(string $fecha): int
    {
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'CONFIRMADA']);
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => 'CERTIFICADA', 'fel_uuid' => 'uuid-'.$venta->id,
            'xml_certificado' => '<prueba/>', 'fecha_certificacion' => $fecha, 'fecha_hora_emision' => $fecha, 'fel_serie' => 'A', 'fel_numero' => '0001', 'ambiente' => 'PRUEBAS']);
        return $venta->id;
    }

    private function anular(int $id, string $fecha): void
    {
        DB::table('ventas')->where('id', $id)->update(['estado_venta' => 'ANULADA']);
        DB::table('documentos_fel')->where('venta_id', $id)->update(['estado_fel' => 'ANULADA']);
        DB::table('anulaciones_venta')->insert(['venta_id' => $id, 'documento_fel_id' => DB::table('documentos_fel')->where('venta_id', $id)->value('id'),
            'usuario_id' => 7, 'tipo' => 'FEL', 'estado' => 'APLICADA', 'fecha_anulacion' => $fecha, 'motivo' => 'Prueba', 'solicitud' => '{}', 'fecha_confirmacion' => $fecha.' 18:00:00']);
    }

    /** Foto de tablas operativas para detectar escrituras ocultas al consultar o renderizar. */
    private function fotoOperativa(): array
    {
        $foto = [];
        foreach (['ventas', 'documentos_fel', 'anulaciones_venta', 'compras', 'detalles_compra', 'inventarios', 'inventarios_compra',
            'movimientos_inventario', 'movimientos_inventario_compra', 'producciones', 'consumos_produccion', 'materiales_producto', 'productos', 'clientes', 'proveedores'] as $tabla) {
            $foto[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        return $foto;
    }
}
