<?php

namespace Tests\Unit;

use App\Models\Venta;
use App\Services\FelXmlBuilderService;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\VentasInventarioTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';

class FelXmlBuilderTest extends VentasInventarioTestCase
{
    public function test_fact_tiene_estructura_de_entrada_ainnova_fecha_y_constantes(): void
    {
        $venta = $this->confirmacion->confirmarVenta($this->crearVenta()->id, 7);
        $xml = $this->xml($venta);
        $this->assertSame('DocElectronico', $xml->document->documentElement->tagName);
        $this->assertSame(['Encabezado', 'Detalles'], $this->nombres($xml, '/DocElectronico/*'));
        $this->assertSame(['Receptor', 'InfoDoc', 'Totales', 'DatosAdicionales'], $this->nombres($xml, '/DocElectronico/Encabezado/*'));
        $this->assertSame(['NITReceptor', 'Nombre', 'Direccion'], $this->nombres($xml, '//Receptor/*'));
        foreach (['TipoVenta' => 'B', 'DestinoVenta' => '1', 'Fecha' => '03/10/2026', 'Moneda' => '1', 'Tasa' => '1', 'Referencia' => $venta->documentoFel->referencia,
            'NumeroAcceso' => '', 'SerieAdmin' => '', 'NumeroAdmin' => '', 'Reversion' => ''] as $tag => $esperado) {
            $this->assertSame(1, $xml->query('//InfoDoc/'.$tag)->length);
            $this->assertSame($esperado, $xml->evaluate('string(//InfoDoc/'.$tag.')'));
        }
        foreach (['Medida' => '1', 'TipoVentaDet' => 'B', 'Cantidad' => '2', 'Precio' => '112.000000', 'PorcDesc' => '10.0000', 'ImpOtros' => '0.00', 'ImpIsr' => '0.00'] as $tag => $esperado) {
            $this->assertSame($esperado, $xml->evaluate('string(//Productos/'.$tag.')'));
        }
        $this->assertSame(['Bruto', 'Descuento', 'Exento', 'Otros', 'Neto', 'Isr', 'Iva', 'Total'], $this->nombres($xml, '//Totales/*'));
        $this->assertSame(['Producto', 'Descripcion', 'Medida', 'Cantidad', 'Precio', 'PorcDesc', 'ImpBruto', 'ImpDescuento', 'ImpExento', 'ImpOtros', 'ImpNeto', 'ImpIsr', 'ImpIva', 'ImpTotal', 'TipoVentaDet'], $this->nombres($xml, '//Productos/*'));
        $this->assertSame('0.00', $xml->evaluate('string(//Totales/Isr)'));
        $this->assertSame('0.00', $xml->evaluate('string(//Totales/Otros)'));
        $this->assertSame(0, $xml->query('//GTDocumento')->length);
        $this->assertSame(0, $xml->query('//Receptor/TipoReceptor')->length);
        $this->assertSame(1, $xml->query('/DocElectronico/Encabezado/DatosAdicionales/TipoReceptor')->length);
    }

    public function test_importes_de_varias_lineas_gravadas_y_exentas_son_identicos_al_xml(): void
    {
        $venta = $this->crearVenta(['detalles' => [
            $this->linea(['cantidad' => '1', 'precio_unitario' => '0.333333', 'porcentaje_descuento' => '33.3333']),
            $this->linea(['cantidad' => '2', 'precio_unitario' => '10.005001', 'porcentaje_descuento' => '12.3456', 'tratamiento_tributario' => 'EXENTO']),
        ]]);
        $venta = $this->confirmacion->confirmarVenta($venta->id, 7);
        $xml = $this->xml($venta);
        $this->assertSame(2, $xml->query('/DocElectronico/Detalles/Productos')->length);
        $this->assertSame('17.76', $venta->importe_total);
        foreach (['Bruto' => 'importe_bruto', 'Descuento' => 'importe_descuento', 'Exento' => 'importe_exento', 'Neto' => 'importe_neto', 'Iva' => 'importe_iva', 'Total' => 'importe_total'] as $tag => $campo) {
            $this->assertSame($venta->$campo, $xml->evaluate('string(//Totales/'.$tag.')'));
            foreach ($venta->detalles as $indice => $detalle) {
                $this->assertSame($detalle->$campo, $xml->evaluate('string(//Productos['.($indice + 1).']/Imp'.$tag.')'));
            }
        }
        foreach ($venta->detalles as $indice => $detalle) {
            foreach (['Producto' => 'producto_codigo', 'Descripcion' => 'descripcion', 'Cantidad' => 'cantidad', 'PorcDesc' => 'porcentaje_descuento'] as $tag => $campo) {
                $this->assertSame($detalle->$campo, $xml->evaluate('string(//Productos['.($indice + 1).']/'.$tag.')'));
            }
        }
    }

    /** @dataProvider tiposReceptor */
    public function test_tipo_receptor_se_mapea_solo_desde_snapshot(string $codigo, string $identificacion, string $tipo, string $nit): void
    {
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(['receptor_tipo_identificacion_codigo' => $codigo, 'receptor_identificacion' => $identificacion]);
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $xml = $this->xml($confirmada);
        $this->assertSame($tipo, $xml->evaluate('string(//DatosAdicionales/TipoReceptor)'));
        $this->assertSame($nit, $xml->evaluate('string(//Receptor/NITReceptor)'));
        $this->assertSame($codigo, $confirmada->receptor_tipo_identificacion_codigo);
        $this->assertSame($identificacion, $confirmada->receptor_identificacion);
    }

    public static function tiposReceptor(): array
    {
        return [
            'código local NIT' => ['1', '1234567K', '4', '1234567K'],
            'NIT conserva formato histórico' => ['1', '1234567-K', '4', '1234567-K'],
            'consumidor final' => ['1', 'CF', '4', 'CF'],
            'CF en minúsculas' => ['1', 'cf', '4', 'CF'],
            'CF con mayúsculas mixtas' => ['1', 'Cf', '4', 'CF'],
            'CF con minúsculas mixtas' => ['1', 'cF', '4', 'CF'],
            'CF normalizado' => ['SIN_MAPEO', ' cf ', '4', 'CF'],
        ];
    }

    public function test_mapeos_ainnova_conocidos_no_habilitan_codigos_locales_sin_definir(): void
    {
        $this->assertSame(['1' => 'NIT'], config('fel.tipos_identificacion_locales'));
        $this->assertSame(['NIT' => '4', 'CF' => '4', 'CUI' => '2', 'PASAPORTE' => '3', 'EXTRANJERO' => '3'], config('fel.tipos_receptor_ainnova'));
    }

    /** @dataProvider codigosLocalesNoConfigurados */
    public function test_identificacion_no_soportada_rechaza_y_revierte_inventario(string $codigo): void
    {
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(['receptor_tipo_identificacion_codigo' => $codigo]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function codigosLocalesNoConfigurados(): array
    {
        return [['DESCONOCIDO'], ['2'], ['5'], ['NIT'], ['CUI'], ['PASAPORTE'], ['EXTRANJERO']];
    }

    public function test_snapshot_existente_con_codigo_uno_genera_xml_sin_reescribir_datos(): void
    {
        $venta = $this->crearVenta()->load(['detalles', 'documentoFel']);
        $this->assertSame('1', $venta->receptor_tipo_identificacion_codigo);
        $referencia = $venta->documentoFel->referencia;
        $antes = $this->snapshot();
        $builder = new FelXmlBuilderService;
        $xml = $builder->generarFactura($venta, $venta->documentoFel);
        $venta->receptor_tipo_identificacion_nombre = 'Nombre visible distinto';
        $this->assertSame($xml, $builder->generarFactura($venta, $venta->documentoFel));
        $this->assertSame($antes, $this->snapshot());

        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $this->assertSame($xml, $confirmada->documentoFel->xml_solicitud);
        $this->assertSame($referencia, $confirmada->documentoFel->referencia);
        $this->assertSame(hash('sha256', $xml), $confirmada->documentoFel->hash_xml);
        $this->assertSame(3, DB::table('inventarios')->where('producto_id', 31)->value('cantidad'));
        $this->assertSame(1, DB::table('movimientos_inventario')->where('detalle_venta_id', $confirmada->detalles->sole()->id)->where('tipo_movimiento', 'SALIDA')->count());
    }

    public function test_xml_preserva_snapshots_aunque_se_modifiquen_o_inactiven_catalogos(): void
    {
        $venta = $this->crearVenta();
        $receptor = $venta->receptor_nombre;
        $codigo = $venta->detalles->sole()->producto_codigo;
        DB::table('clientes')->where('id', 11)->update(['numero_identificacion' => 'OTRO', 'correo' => 'nuevo@example.test', 'estado' => false]);
        DB::table('personas')->where('cliente_id', 11)->update(['nombre1' => 'Otra persona']);
        DB::table('direcciones')->where('id', 4)->update(['direccion' => 'Otra dirección', 'estado' => false]);
        DB::table('tipos_identificacion')->where('id', 5)->update(['codigo' => 'NO_SOPORTADO', 'estado' => false]);
        DB::table('productos')->where('id', 31)->update(['codigo' => 'NUEVO', 'nombre' => 'Otro producto', 'descripcion' => 'Otra descripción', 'estado' => false]);
        DB::table('tipos_documento')->where('id', 17)->update(['codigo' => 'OTRO_FACT', 'estado' => false]);
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $xml = $this->xml($confirmada);
        foreach (['//Receptor/Nombre' => $receptor, '//Receptor/NITReceptor' => '1234567K', '//Receptor/Direccion' => 'Zona 1', '//Productos/Producto' => $codigo, '//Productos/Descripcion' => 'Anillo de plata', '//DatosAdicionales/TipoReceptor' => '4'] as $ruta => $esperado) {
            $this->assertSame($esperado, $xml->evaluate('string('.$ruta.')'));
        }
        $this->assertSame($venta->receptor_correo, $confirmada->receptor_correo);
    }

    public function test_dom_escapa_caracteres_y_preserva_unicode_sin_inyectar_elementos(): void
    {
        $texto = 'León & Hijos <script> "María" / Joyas 🦁';
        $venta = $this->crearVenta();
        DB::table('ventas')->where('id', $venta->id)->update(['receptor_nombre' => $texto, 'receptor_direccion' => $texto]);
        DB::table('detalles_ventas')->where('venta_id', $venta->id)->update(['descripcion' => $texto, 'producto_codigo' => 'A&B<1>']);
        $confirmada = $this->confirmacion->confirmarVenta($venta->id, 7);
        $xml = $this->xml($confirmada);
        foreach (['//Receptor/Nombre', '//Receptor/Direccion', '//Productos/Descripcion'] as $ruta) {
            $this->assertSame($texto, $xml->evaluate('string('.$ruta.')'));
        }
        $this->assertSame('A&B<1>', $xml->evaluate('string(//Productos/Producto)'));
        $this->assertSame(0, $xml->query('//script')->length);
        $this->assertStringContainsString('&amp;', $confirmada->documentoFel->xml_solicitud);
        $this->assertStringContainsString('&lt;script&gt;', $confirmada->documentoFel->xml_solicitud);
        $this->assertSame(hash('sha256', $confirmada->documentoFel->xml_solicitud), $confirmada->documentoFel->hash_xml);
    }

    public function test_caracter_invalido_para_xml_revierte_todas_las_operaciones(): void
    {
        $venta = $this->crearVenta();
        DB::table('detalles_ventas')->where('venta_id', $venta->id)->update(['descripcion' => "Texto\x01inválido"]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_referencia_uuid_preexistente_no_se_trunca_ni_regenera_y_no_confirma(): void
    {
        $venta = $this->crearVenta();
        $anterior = '8e511596-ff36-49a8-9dc5-8f482cd2a3dc';
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['referencia' => $anterior]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame($anterior, $venta->fresh()->documentoFel->referencia);
        $this->assertSame('BORRADOR', $venta->fresh()->estado_venta);
    }

    /** @dataProvider snapshotsInvalidos */
    public function test_moneda_receptor_y_clase_incompatibles_no_se_confirman(string $tabla, array $cambios): void
    {
        $venta = $this->crearVenta();
        DB::table($tabla)->where($tabla === 'ventas' ? 'id' : 'venta_id', $venta->id)->update($cambios);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function snapshotsInvalidos(): array
    {
        return [['ventas', ['moneda' => 'USD']], ['ventas', ['receptor_nombre' => '']], ['ventas', ['receptor_direccion' => '']],
            ['ventas', ['receptor_identificacion' => '']], ['detalles_ventas', ['bien_servicio' => 'S']], ['detalles_ventas', ['unidad_medida' => 'OTRA']]];
    }

    public function test_documento_con_hash_o_xml_previos_se_rechaza_sin_reemplazarlo(): void
    {
        $venta = $this->crearVenta();
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['xml_solicitud' => '<Previo/>', 'hash_xml' => hash('sha256', '<Previo/>')]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->confirmacion->confirmarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_ddl_mysql_agrega_fk_unique_indice_y_check_sin_tocar_produccion(): void
    {
        $antes = $this->snapshot();
        $ddl = $this->ddl('8.0.36', 'up');
        $this->assertCount(1, $ddl);
        $sql = $ddl[0];
        $this->assertStringContainsString('ADD COLUMN detalle_venta_id BIGINT UNSIGNED NULL', $sql);
        $this->assertStringContainsString('ADD INDEX movimientos_inventario_detalle_venta_id_index (detalle_venta_id)', $sql);
        $this->assertStringContainsString('REFERENCES `detalles_ventas` (id) ON DELETE RESTRICT', $sql);
        $this->assertStringContainsString('UNIQUE (detalle_venta_id, tipo_movimiento)', $sql);
        $this->assertStringContainsString("detalle_venta_id IS NULL OR (produccion_id IS NULL AND tipo_movimiento IN ('ENTRADA', 'SALIDA') AND cantidad > 0 AND estado = 1)", $sql);
        $this->assertStringNotContainsString('DROP', $sql);
        $this->assertSame($antes, $this->snapshot());

        preg_match('/CHECK\s*\((.*)\)\s*$/s', $sql, $match);
        DB::statement('CREATE TABLE comprobacion_ventas (detalle_venta_id INTEGER, produccion_id INTEGER, tipo_movimiento TEXT, cantidad INTEGER, estado INTEGER, CHECK ('.$match[1].'))');
        foreach ([['produccion_id' => 19], ['tipo_movimiento' => 'AJUSTE'], ['cantidad' => 0], ['cantidad' => -1], ['estado' => 0]] as $cambio) {
            $fila = array_replace(['detalle_venta_id' => 1, 'produccion_id' => null, 'tipo_movimiento' => 'SALIDA', 'cantidad' => 1, 'estado' => 1], $cambio);
            $this->rechaza(fn () => DB::table('comprobacion_ventas')->insert($fila), QueryException::class);
        }
        DB::table('comprobacion_ventas')->insert([
            ['detalle_venta_id' => 1, 'produccion_id' => null, 'tipo_movimiento' => 'SALIDA', 'cantidad' => 1, 'estado' => 1],
            ['detalle_venta_id' => 1, 'produccion_id' => null, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => 1, 'estado' => 1],
            ['detalle_venta_id' => null, 'produccion_id' => null, 'tipo_movimiento' => 'AJUSTE', 'cantidad' => -1, 'estado' => 0],
        ]);
        $this->assertSame(3, DB::table('comprobacion_ventas')->count());
    }

    public function test_down_usa_sintaxis_del_servidor_y_rechaza_movimientos_existentes(): void
    {
        $sql = $this->ddl('8.0.36', 'down')[0];
        $this->assertStringContainsString('DROP CHECK movimientos_inventario_venta_check', $sql);
        $this->assertStringNotContainsString('DROP INDEX movimientos_inventario_produccion', $sql);
        $sql = $this->ddl('5.5.5-10.11.6-MariaDB', 'down')[0];
        $this->assertStringContainsString('DROP CONSTRAINT movimientos_inventario_venta_check', $sql);
        $this->confirmacion->confirmarVenta($this->crearVenta()->id, 7);
        $this->rechaza(fn () => $this->ddl('8.0.36', 'down'), RuntimeException::class);
    }

    public function test_migracion_rechaza_mysql_sin_check_efectivo(): void
    {
        $this->rechaza(fn () => $this->ddl('8.0.15', 'up'), RuntimeException::class);
    }

    private function xml(Venta $venta): DOMXPath
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($venta->documentoFel->xml_solicitud, LIBXML_NONET));

        return new DOMXPath($dom);
    }

    private function nombres(DOMXPath $xml, string $ruta): array
    {
        $nombres = [];
        foreach ($xml->query($ruta) as $nodo) {
            $nombres[] = $nodo->nodeName;
        }

        return $nombres;
    }

    private function ddl(string $version, string $metodo): array
    {
        $conexion = new MySqlConnection(fn () => throw new RuntimeException('Nunca debe abrirse una conexión real.'), 'aislada', '', ['driver' => 'mysql']);
        $conexion->setQueryGrammar(new MySqlGrammar);
        $manager = DB::getFacadeRoot();
        $simulado = new class($conexion, $version, $manager)
        {
            public array $ddl = [];

            public function __construct(private MySqlConnection $conexion, private string $version, private $manager) {}

            public function connection()
            {
                return $this->conexion;
            }

            public function selectOne($sql)
            {
                return (object) ['version' => $this->version];
            }

            public function table($tabla)
            {
                return $this->manager->table($tabla);
            }

            public function statement($sql)
            {
                $this->ddl[] = $sql;

                return true;
            }
        };
        DB::swap($simulado);
        try {
            $migracion = require dirname(__DIR__, 2).'/database/migrations/2026_10_03_000008_vincular_movimientos_inventario_a_detalles_ventas.php';
            $migracion->$metodo();
        } finally {
            DB::swap($manager);
        }

        return $simulado->ddl;
    }
}
