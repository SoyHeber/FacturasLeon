<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\VentasTestCase;

class DashboardTest extends VentasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El soporte no inicia Laravel ni carga .env; solo abre esta conexión.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Carbon::setTestNow(Carbon::parse('2026-10-31 15:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 15:00:00', 'UTC'));
        Schema::create('inventarios_compra', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('unidad_medida')->default('GRAMO');
            $table->string('cantidad')->default('0.0000000000');
            $table->string('stock_minimo')->default('0.0000000000');
            $table->boolean('estado')->default(true);
        });
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_anulacion')->nullable();
            $table->string('importe_total');
            $table->string('moneda')->default('GTQ');
            $table->boolean('estado')->default(true);
            $table->boolean('inventario_aplicado')->default(true);
        });
        Schema::create('detalles_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras');
            $table->boolean('estado')->default(true);
        });
        Schema::create('producciones', function (Blueprint $table) {
            $table->id();
            $table->integer('cantidad');
            $table->string('estado_produccion')->default('CONFIRMADA');
            $table->boolean('inventario_aplicado')->default(true);
            $table->boolean('producto_terminado_aplicado')->default(true);
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cuenta_dte_por_emision_y_conserva_su_anulacion_en_otro_dia(): void
    {
        $vigente = $this->certificada('2026-10-02 14:00:00', '112.00');
        $anulada = $this->certificada('2026-10-02 18:00:00', '224.00');
        $this->anular($anulada, '2026-10-05');
        $this->crearVenta(); // El borrador nunca es un DTE emitido.
        $antes = $this->foto();

        $datos = $this->dashboard();

        $ventas = $datos['ventas'];
        $this->assertSame(2, $ventas['emitidos']);
        $this->assertSame(1, $ventas['anulados']);
        $this->assertSame(2, $ventas['dias']['2026-10-02']['emitidos']);
        $this->assertSame(0, $ventas['dias']['2026-10-05']['emitidos']);
        $this->assertSame(1, $ventas['dias']['2026-10-05']['anulados']);
        $this->assertSame('336.00', $ventas['importes']['GTQ']['emitido']);
        $this->assertSame('224.00', $ventas['importes']['GTQ']['anulado']);
        $this->assertSame('112.00', $ventas['importes']['GTQ']['neto']);
        $this->assertSame('-224.00', $ventas['dias']['2026-10-05']['importes']['GTQ']['neto']);
        $this->assertCount(31, $ventas['dias']);
        $this->assertSame(0, $ventas['dias']['2026-10-03']['emitidos']);
        $this->assertSame($antes, $this->foto());
        $this->assertSame($vigente, $datos['actividad']->last()['id']);
    }

    public function test_los_limites_del_dia_son_de_guatemala_y_el_fin_es_exclusivo(): void
    {
        $this->certificada('2026-10-01 05:59:59', '10.00'); // Septiembre local.
        $this->certificada('2026-10-01 06:00:00', '20.00');
        $this->certificada('2026-10-02 05:59:59', '30.00');
        $this->certificada('2026-10-02 06:00:00', '40.00');

        $datos = $this->dashboard(['periodo' => 'personalizado', 'desde' => '2026-10-01', 'hasta' => '2026-10-01']);

        $this->assertSame('2026-10-01 06:00:00', $datos['periodo']['inicio_utc']);
        $this->assertSame('2026-10-02 06:00:00', $datos['periodo']['fin_utc']);
        $this->assertSame(2, $datos['ventas']['emitidos']);
        $this->assertSame('50.00', $datos['ventas']['importes']['GTQ']['neto']);
        $this->assertCount(1, $datos['ventas']['dias']);
        $this->assertStringContainsString('<circle', $this->pagina(['periodo' => 'personalizado', 'desde' => '2026-10-01', 'hasta' => '2026-10-01']));
    }

    public function test_el_mes_predeterminado_sigue_el_dia_local_aunque_utc_ya_cambio_de_mes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 03:00:00', 'UTC'));
        $datos = $this->dashboard();
        $this->assertSame('2026-10-01', $datos['periodo']['desde']->toDateString());
        $this->assertSame('2026-10-31', $datos['periodo']['hasta']->toDateString());
        $hoy = $this->dashboard(['periodo' => 'hoy']);
        $this->assertSame('2026-10-31', $hoy['periodo']['desde']->toDateString());
        $this->assertSame('2026-10-31', $hoy['periodo']['hasta']->toDateString());
    }

    public function test_anulacion_de_emision_anterior_solo_resta_en_su_fecha(): void
    {
        $id = $this->certificada('2026-09-30 14:00:00', '112.00');
        $this->anular($id, '2026-10-02');
        $datos = $this->dashboard();
        $this->assertSame(0, $datos['ventas']['emitidos']);
        $this->assertSame(1, $datos['ventas']['anulados']);
        $this->assertSame('-112.00', $datos['ventas']['importes']['GTQ']['neto']);
    }

    public function test_inciertas_no_restan_y_confirmadas_pendientes_de_reintegro_si(): void
    {
        $incierta = $this->certificada('2026-10-03 14:00:00', '100.00');
        $confirmada = $this->certificada('2026-10-03 14:00:00', '200.00');
        $this->anular($incierta, '2026-10-04', 'INCIERTA');
        $this->anular($confirmada, '2026-10-04', 'CONFIRMADA');
        $datos = $this->dashboard();
        $this->assertSame(2, $datos['ventas']['emitidos']);
        $this->assertSame(1, $datos['ventas']['anulados']);
        $this->assertSame('100.00', $datos['ventas']['importes']['GTQ']['neto']);
        $this->assertSame(2, $datos['ventas']['alertas']['total']);
        $this->assertStringContainsString('reintegro pendiente', $this->pagina());
    }

    public function test_fel_no_certificada_y_cancelacion_local_no_entran_en_la_facturacion(): void
    {
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'CONFIRMADA']);
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => 'INCIERTA']);
        $cancelada = $this->crearVenta();
        DB::table('ventas')->where('id', $cancelada->id)->update(['estado_venta' => 'ANULADA']);
        $datos = $this->dashboard();
        $this->assertSame(0, $datos['ventas']['emitidos']);
        $this->assertSame([], $datos['ventas']['importes']);
        $this->assertSame(1, $datos['ventas']['alertas']['total']);
    }

    public function test_compras_separan_monedas_historicas_y_anulaciones(): void
    {
        $this->compra('2026-10-01 00:00:00', '100.10');
        $this->compra('2026-10-03 10:00:00', '20.20', ['estado' => false, 'fecha_anulacion' => '2026-10-04 14:00:00']);
        $this->compra('2026-10-01 10:00:00', '10.00', ['moneda' => 'USD']);
        $this->compra('2026-10-01 10:00:00', '999.00', ['inventario_aplicado' => false]);
        $sinDetalle = $this->compra('2026-10-01 10:00:00', '888.00');
        DB::table('detalles_compra')->where('compra_id', $sinDetalle)->delete();
        $datos = $this->dashboard();
        $this->assertSame('100.10', $datos['compras']['importes']['GTQ']['neto']);
        $this->assertSame('120.30', $datos['compras']['importes']['GTQ']['emitido']);
        $this->assertSame('20.20', $datos['compras']['importes']['GTQ']['anulado']);
        $this->assertSame('10.00', $datos['compras']['importes']['USD']['neto']);
        $this->assertSame(1, $datos['compras']['historicas']);
        $this->assertStringContainsString('compras históricas por revisar', $this->pagina());
    }

    public function test_importes_grandes_conservan_los_centavos(): void
    {
        // SQLite convierte DECIMAL a REAL; TEXT conserva el importe como MySQL DECIMAL.
        DB::statement('ALTER TABLE ventas RENAME COLUMN importe_total TO importe_total_original');
        DB::statement("ALTER TABLE ventas ADD COLUMN importe_total TEXT NOT NULL DEFAULT '0.00'");
        $this->certificada('2026-10-01 10:00:00', '90071992547409.91');
        $this->certificada('2026-10-02 10:00:00', '0.10');
        $this->assertSame('90071992547410.01', $this->dashboard()['ventas']['importes']['GTQ']['neto']);
        $this->assertStringContainsString('90,071,992,547,410.01', $this->pagina());
    }

    public function test_produccion_ignora_borradores_y_separa_reversiones(): void
    {
        DB::table('producciones')->insert([
            ['cantidad' => 5, 'estado_produccion' => 'CONFIRMADA', 'inventario_aplicado' => true, 'producto_terminado_aplicado' => true, 'fecha_confirmacion' => '2026-10-01 14:00:00', 'fecha_anulacion' => null],
            ['cantidad' => 3, 'estado_produccion' => 'ANULADA', 'inventario_aplicado' => true, 'producto_terminado_aplicado' => true, 'fecha_confirmacion' => '2026-09-30 14:00:00', 'fecha_anulacion' => '2026-10-02 14:00:00'],
            ['cantidad' => 10, 'estado_produccion' => 'ANULADA', 'inventario_aplicado' => false, 'producto_terminado_aplicado' => false, 'fecha_confirmacion' => null, 'fecha_anulacion' => '2026-10-02 14:00:00'],
            ['cantidad' => 20, 'estado_produccion' => 'BORRADOR', 'inventario_aplicado' => false, 'producto_terminado_aplicado' => false, 'fecha_confirmacion' => null, 'fecha_anulacion' => null],
        ]);
        $datos = $this->dashboard()['produccion'];
        $this->assertSame(1, $datos['ordenes']);
        $this->assertSame(1, $datos['reversiones']);
        $this->assertSame(5, $datos['unidades']);
        $this->assertSame(3, $datos['revertidas']);
        $this->assertSame(2, $datos['neto']);
    }

    public function test_alertas_de_stock_cuentan_referencias_y_no_mezclan_unidades(): void
    {
        DB::table('inventarios')->update(['cantidad' => 0, 'stock_minimo' => 0]);
        foreach (range(1, 8) as $id) {
            DB::table('inventarios_compra')->insert(['nombre' => 'Materia '.$id, 'cantidad' => '0.0000000000', 'stock_minimo' => '0', 'unidad_medida' => $id % 2 ? 'GRAMO' : 'UNIDAD']);
        }
        DB::table('inventarios_compra')->insert(['nombre' => 'Inactiva', 'cantidad' => '0', 'stock_minimo' => '10', 'estado' => false]);
        DB::table('inventarios_compra')->insert(['nombre' => 'Existencias suficientes', 'cantidad' => '10', 'stock_minimo' => '2']);
        DB::table('inventarios_compra')->insert(['nombre' => 'Mínimo decimal', 'cantidad' => '0.0000000001', 'stock_minimo' => '0.0000000002']);
        $datos = $this->dashboard();
        $this->assertSame(1, $datos['inventarios']['inventarios']['total']);
        $this->assertSame(1, $datos['inventarios']['inventarios']['agotados']);
        $this->assertSame(9, $datos['inventarios']['inventarios_compra']['total']);
        $this->assertSame(8, $datos['inventarios']['inventarios_compra']['agotados']);
        $this->assertCount(5, $datos['inventarios']['inventarios_compra']['items']);
        $html = $this->pagina();
        $this->assertStringContainsString('Se muestran 5 de 9 referencias', $html);
        $this->assertStringContainsString('GRAMO', $html);
        $this->assertStringContainsString('UNIDAD', $html);
        DB::table('productos')->where('id', 31)->update(['estado' => false]);
        $this->assertSame(0, $this->dashboard()['inventarios']['inventarios']['total']);
    }

    public function test_sin_permisos_no_consulta_tablas_operativas_ni_expone_indicadores(): void
    {
        $this->permisos([]);
        DB::connection()->enableQueryLog();
        $html = $this->pagina();
        $consultas = implode(' ', array_column(DB::connection()->getQueryLog(), 'query'));
        foreach (['ventas', 'documentos_fel', 'compras', 'producciones', 'inventarios', 'inventarios_compra'] as $tabla) {
            $this->assertStringNotContainsString('from "'.$tabla.'"', $consultas);
        }
        $this->assertStringContainsString('Sin secciones de consulta disponibles', $html);
        $this->assertStringNotContainsString('DTE emitidos por día', $html);
        $this->assertStringNotContainsString('Facturación neta', $html);
        $this->assertStringNotContainsString('Nueva venta', $html);
    }

    public function test_crear_no_otorga_consulta_y_cada_modulo_filtra_sus_datos(): void
    {
        $this->permisos(['ventas' => ['crear'], 'inventarios_compra' => ['ver']]);
        $datos = $this->dashboard();
        $this->assertNull($datos['ventas']);
        $this->assertNull($datos['compras']);
        $this->assertNull($datos['produccion']);
        $this->assertSame(['inventarios_compra'], array_keys($datos['inventarios']));
        $html = $this->pagina();
        $this->assertStringContainsString('Nueva venta', $html);
        $this->assertStringNotContainsString('DTE emitidos por día', $html);
        $this->assertStringNotContainsString('Nueva compra', $html);
        $this->assertStringNotContainsString('Productos terminados', $html);
    }

    public function test_vista_escapa_nombres_y_muestra_dias_sin_dte(): void
    {
        DB::table('users')->where('id', 7)->update(['name' => '<script>alert(1)</script>']);
        DB::table('inventarios_compra')->insert(['nombre' => '<img src=x onerror=alert(1)>']);
        $html = $this->pagina();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('01/10/2026', $html);
        $this->assertStringContainsString('31/10/2026', $html);
        $this->assertStringContainsString('0 emitidos', $html);
        $this->assertStringContainsString('Sin operaciones validadas', $html);
    }

    public function test_actividad_reciente_limita_y_ordena_eventos_y_ruta_conserva_protecciones(): void
    {
        foreach (range(1, 10) as $dia) {
            $this->certificada('2026-10-'.str_pad((string) $dia, 2, '0', STR_PAD_LEFT).' 14:00:00', '1.00');
        }
        $datos = $this->dashboard();
        $this->assertCount(8, $datos['actividad']);
        $this->assertSame('2026-10-10', $datos['actividad']->first()['fecha']);
        $this->assertSame('2026-10-03', $datos['actividad']->last()['fecha']);
        $ruta = $this->router->getRoutes()->getByName('dashboard');
        $this->assertSame(['auth', 'verified'], $ruta->middleware());
        $this->assertSame(DashboardController::class.'@index', $ruta->getActionName());
    }

    #[DataProvider('periodosInvalidos')]
    public function test_rechaza_periodos_invalidos(array $parametros): void
    {
        $this->expectException(ValidationException::class);
        $this->dashboard($parametros);
    }

    public static function periodosInvalidos(): array
    {
        return [
            [['periodo' => 'invalido']], [['periodo' => 'personalizado']],
            [['periodo' => 'personalizado', 'desde' => '2026-10-03', 'hasta' => '2026-10-01']],
            [['periodo' => 'personalizado', 'desde' => '2026-10-32', 'hasta' => '2026-10-31']],
            [['periodo' => 'personalizado', 'desde' => '2026-11-01', 'hasta' => '2026-11-02']],
            [['periodo' => 'personalizado', 'desde' => '2025-01-01', 'hasta' => '2026-10-31']],
        ];
    }

    private function dashboard(array $parametros = []): array
    {
        return (new DashboardController)->index($this->request($parametros, 'GET'))->getData();
    }

    private function pagina(array $parametros = []): string
    {
        return (new DashboardController)->index($this->request($parametros, 'GET'))->render();
    }

    /** Carga evidencia fiscal ficticia directamente en SQLite; nunca llama a Ainnova. */
    private function certificada(string $fecha, string $importe): int
    {
        $venta = $this->crearVenta(['fecha' => '2026-01-01']);
        DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'CONFIRMADA', 'importe_total' => $importe]);
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update([
            'estado_fel' => 'CERTIFICADA', 'fecha_hora_emision' => $fecha, 'fecha_certificacion' => $fecha,
            'fel_uuid' => 'certificado-'.$venta->id, 'xml_certificado' => '<certificado/>',
        ]);

        return $venta->id;
    }

    private function anular(int $id, string $fecha, string $estado = 'APLICADA'): void
    {
        $documento = DB::table('documentos_fel')->where('venta_id', $id)->value('id');
        DB::table('anulaciones_venta')->insert(['venta_id' => $id, 'documento_fel_id' => $documento,
            'usuario_id' => 7, 'tipo' => 'FEL', 'estado' => $estado, 'fecha_anulacion' => $fecha,
            'fecha_confirmacion' => $estado === 'INCIERTA' ? null : $fecha.' 14:00:00', 'motivo' => 'Prueba aislada', 'solicitud' => '{}']);
        if ($estado === 'APLICADA') {
            DB::table('ventas')->where('id', $id)->update(['estado_venta' => 'ANULADA']);
            DB::table('documentos_fel')->where('id', $documento)->update(['estado_fel' => 'ANULADA']);
        }
    }

    private function compra(string $fecha, string $importe, array $cambios = []): int
    {
        $id = DB::table('compras')->insertGetId(array_merge(['fecha_emision' => $fecha, 'importe_total' => $importe], $cambios));
        DB::table('detalles_compra')->insert(['compra_id' => $id]);

        return $id;
    }

    private function permisos(array $permisos): void
    {
        DB::table('roles_opciones_acciones')->delete();
        $rol = DB::table('roles_usuarios')->where('user_id', 7)->value('rol_id');
        foreach ($permisos as $ruta => $acciones) {
            foreach ($acciones as $accion) {
                DB::table('roles_opciones_acciones')->insert(['rol_id' => $rol,
                    'opcion_id' => DB::table('opciones')->where('ruta', $ruta)->value('id'),
                    'accion_id' => DB::table('acciones')->where('clave', $accion)->value('id')]);
            }
        }
    }

    private function foto(): array
    {
        $foto = [];
        foreach (['ventas', 'documentos_fel', 'anulaciones_venta', 'inventarios', 'compras', 'producciones', 'roles_opciones_acciones'] as $tabla) {
            $foto[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }

        return $foto;
    }
}
