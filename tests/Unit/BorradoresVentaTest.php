<?php

namespace Tests\Unit;

use App\Http\Middleware\VerificarPermiso;
use App\Models\DetalleVenta;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use App\Models\Opcion;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\VentasTestCase;

class BorradoresVentaTest extends VentasTestCase
{
    public function test_crear_venta_ignora_estados_importes_snapshots_y_credenciales_del_navegador(): void
    {
        $venta = $this->crearVenta([
            'estado_venta' => 'CONFIRMADA', 'usuario_creador_id' => 999, 'importe_total' => '0.01',
            'receptor_nombre' => 'Nombre falsificado', 'referencia' => 'Frente al parque',
            'documento_fel' => ['estado_fel' => 'CERTIFICADA', 'p_tipo_doc' => 17, 'pPassword' => 'no-guardar', 'xml_solicitud' => '<xml/>'],
            'detalles' => [$this->linea(['importe_total' => '0.01', 'importe_iva' => '999',
                'importe_exento' => '999', 'producto_codigo' => 'ALTERADO', 'numero_linea' => 99, 'bien_servicio' => 'S'])],
        ]);
        $this->assertSame('BORRADOR', $venta->estado_venta);
        $this->assertSame(7, $venta->usuario_creador_id);
        $this->assertSame('Ana María López Pérez', $venta->receptor_nombre);
        $this->assertSame('201.60', $venta->importe_total);
        $this->assertSame('180.00', $venta->importe_neto);
        $this->assertSame('21.60', $venta->importe_iva);
        $detalle = $venta->detalles->sole();
        $this->assertSame(1, $detalle->numero_linea);
        $this->assertSame('AN-01', $detalle->producto_codigo);
        $this->assertSame('UNI', $detalle->unidad_medida);
        $this->assertSame('B', $detalle->bien_servicio);
        $this->assertSame('201.60', $detalle->importe_total);
        $this->assertSame('PENDIENTE', $venta->documentoFel->estado_fel);
        $this->assertSame(1, $venta->documentoFel->p_tipo_doc);
        $this->assertSame(17, $venta->documentoFel->tipo_documento_id);
        $this->assertSame('D', $venta->documentoFel->p_tipo_respuesta);
        $this->assertNull($venta->documentoFel->xml_solicitud);
        $this->assertSame(0, IntentoFel::count());
        $this->assertStringNotContainsString('no-guardar', $venta->documentoFel->toJson());
    }

    public function test_referencias_son_unicas_y_no_cambian_al_editar(): void
    {
        $venta = $this->crearVenta();
        $otra = $this->crearVenta();
        $referencia = $venta->documentoFel->referencia;
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{20}$/', $referencia);
        $this->assertNotSame($referencia, $otra->documentoFel->referencia);
        $this->assertNull($venta->documentoFel->fel_uuid);
        $this->controller->update($this->request($this->datos([
            'referencia' => 'otra', 'estado_venta' => 'ANULADA', 'importe_total' => '9999',
            'detalles' => [$this->linea(['id' => $venta->detalles->sole()->id, 'cantidad' => '3'])],
        ]), 'PUT'), $venta);
        $venta->refresh();
        $this->assertSame($referencia, $venta->documentoFel->referencia);
        $this->assertSame('BORRADOR', $venta->estado_venta);
        $this->assertSame('302.40', $venta->importe_total);
        $this->assertSame(7, $venta->usuario_modificador_id);
        $this->assertSame(2, DocumentoFel::count());
    }

    public function test_solo_fact_activo_es_admitido_y_el_id_local_no_es_p_tipo_doc(): void
    {
        $this->rechaza(fn () => $this->crearVenta(['tipo_documento_id' => 18]), ValidationException::class);
        DB::table('tipos_documento')->where('id', 17)->update(['estado' => false]);
        $this->rechaza(fn () => $this->crearVenta(), ValidationException::class);
        $this->assertSame(0, Venta::count());
        DB::table('tipos_documento')->where('id', 17)->update(['estado' => true]);
        $venta = $this->crearVenta();
        $this->assertSame(17, $venta->tipo_documento_id);
        $this->assertSame(1, $venta->documentoFel->p_tipo_doc);
        $this->rechaza(fn () => $this->controller->update($this->request($this->datos(['tipo_documento_id' => 18]), 'PUT'), $venta), ValidationException::class);
    }

    public function test_snapshot_de_persona_direccion_geografia_e_identificacion(): void
    {
        $venta = $this->crearVenta();
        $this->assertSame('1234567K', $venta->receptor_identificacion);
        $this->assertSame('1', $venta->receptor_tipo_identificacion_codigo);
        $this->assertSame('Número de identificación tributaria', $venta->receptor_tipo_identificacion_nombre);
        $this->assertSame('Ana María López Pérez', $venta->receptor_nombre);
        $this->assertSame('Zona 1', $venta->receptor_direccion);
        $this->assertSame('01001', $venta->receptor_codigo_postal);
        $this->assertSame('Guatemala', $venta->receptor_municipio);
        $this->assertSame('0101', $venta->receptor_municipio_codigo);
        $this->assertSame('01', $venta->receptor_departamento_codigo);
        $this->assertSame('GT', $venta->receptor_pais_codigo);
        $this->assertSame('ana@example.test', $venta->receptor_correo);
        $this->assertNotSame('Frente al parque', $venta->documentoFel->referencia);
    }

    public function test_sociedad_y_cambio_de_cliente_actualizan_el_snapshot_intencionalmente(): void
    {
        $venta = $this->crearVenta();
        $this->controller->update($this->request($this->datos(['cliente_id' => 12]), 'PUT'), $venta);
        $venta->refresh();
        $this->assertSame('Empresa Ejemplo, S.A.', $venta->receptor_nombre);
        $this->assertSame('7654321K', $venta->receptor_identificacion);
        $this->assertNull($venta->receptor_correo);
    }

    public function test_varios_productos_se_numeran_y_recalculan_al_reordenar_y_quitar(): void
    {
        $venta = $this->crearVenta(['detalles' => [
            $this->linea(), $this->linea(['producto_id' => 32, 'cantidad' => '1', 'precio_unitario' => '25', 'porcentaje_descuento' => '0', 'tratamiento_tributario' => 'EXENTO']),
        ]]);
        $this->assertSame([1, 2], $venta->detalles->pluck('numero_linea')->all());
        $this->assertSame('Pulsera', $venta->detalles[1]->descripcion);
        $this->assertSame('226.60', $venta->importe_total);
        $this->assertSame('25.00', $venta->importe_exento);
        $segunda = $venta->detalles[1];
        $this->controller->update($this->request($this->datos(['detalles' => [
            $this->linea(['id' => $segunda->id, 'producto_id' => 32, 'cantidad' => '2', 'precio_unitario' => '25', 'porcentaje_descuento' => '0', 'tratamiento_tributario' => 'EXENTO']),
        ]]), 'PUT'), $venta);
        $venta->refresh();
        $this->assertCount(1, $venta->detalles);
        $this->assertSame(1, $venta->detalles->sole()->numero_linea);
        $this->assertSame('50.00', $venta->importe_total);
    }

    public static function cantidadesInvalidas(): array
    {
        return [['0'], ['-1'], ['1.5'], ['1.0'], ['1e2'], [1.0], ['9223372036854775808']];
    }

    /** @dataProvider cantidadesInvalidas */
    public function test_cantidad_entera_positiva_obligatoria(mixed $cantidad): void
    {
        $this->rechaza(fn () => $this->crearVenta(['detalles' => [$this->linea(['cantidad' => $cantidad])]]), ValidationException::class);
        $this->assertSame(0, Venta::count());
        $this->assertSame(0, DetalleVenta::count());
        $this->assertSame(0, DocumentoFel::count());
    }

    public function test_no_se_admiten_catalogos_inactivos_ni_cliente_sin_clasificacion(): void
    {
        foreach (['clientes' => 11, 'productos' => 31, 'metodos_pago' => 6] as $tabla => $id) {
            DB::table($tabla)->where('id', $id)->update(['estado' => false]);
            $this->rechaza(fn () => $this->crearVenta(), ValidationException::class);
            DB::table($tabla)->where('id', $id)->update(['estado' => true]);
        }
        DB::table('personas')->delete();
        $this->rechaza(fn () => $this->crearVenta(), ValidationException::class);
        $this->assertSame(0, Venta::count());
    }

    public function test_estados_no_editables_se_verifican_con_datos_actuales_del_servidor(): void
    {
        foreach (['CONFIRMADA', 'ANULADA'] as $estado) {
            $venta = $this->crearVenta();
            DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => $estado]);
            $antes = DB::table('detalles_ventas')->where('venta_id', $venta->id)->get()->toJson();
            $this->rechaza(fn () => $this->controller->update($this->request($this->datos(['estado_venta' => 'BORRADOR', 'observacion' => 'Alterada']), 'PUT'), $venta), ValidationException::class);
            $this->rechaza(fn () => $this->controller->edit($venta), ValidationException::class);
            $this->assertSame($estado, $venta->fresh()->estado_venta);
            $this->assertSame('Venta de prueba', $venta->fresh()->observacion);
            $this->assertSame($antes, DB::table('detalles_ventas')->where('venta_id', $venta->id)->get()->toJson());
        }
    }

    public function test_historia_se_conserva_al_cambiar_catalogos_y_editar_la_observacion(): void
    {
        $venta = $this->crearVenta();
        $detalle = $venta->detalles->sole();
        DB::table('personas')->where('cliente_id', 11)->update(['nombre1' => 'Otro nombre']);
        DB::table('clientes')->where('id', 11)->update(['numero_identificacion' => '9999999K', 'correo' => 'otra@example.test']);
        DB::table('tipos_identificacion')->where('id', 5)->update(['codigo' => 'OTRO', 'nombre' => 'Otra identificación']);
        DB::table('direcciones')->where('id', 4)->update(['direccion' => 'Otra dirección', 'codigo_postal' => '99999']);
        foreach (['municipios' => 3, 'departamentos' => 2, 'paises' => 1] as $tabla => $id) {
            DB::table($tabla)->where('id', $id)->update(['nombre' => 'Otro lugar', 'codigo' => 'ZZ']);
        }
        DB::table('productos')->where('id', 31)->update(['codigo' => 'OTRO', 'nombre' => 'Otro producto', 'descripcion' => 'Otra descripción']);
        $venta->refresh();
        $this->assertSame('Ana María López Pérez', $venta->receptor_nombre);
        $this->assertSame('Anillo de plata', $venta->detalles->sole()->descripcion);
        $this->controller->update($this->request($this->datos(['observacion' => 'Cambio intencional',
            'detalles' => [$this->linea(['id' => $detalle->id])]]), 'PUT'), $venta);
        $venta->refresh();
        $this->assertSame('1234567K', $venta->receptor_identificacion);
        $this->assertSame('1', $venta->receptor_tipo_identificacion_codigo);
        $this->assertSame('Zona 1', $venta->receptor_direccion);
        $this->assertSame('01001', $venta->receptor_codigo_postal);
        $this->assertSame('GT', $venta->receptor_pais_codigo);
        $this->assertSame('ana@example.test', $venta->receptor_correo);
        $this->assertSame('AN-01', $venta->detalles->sole()->producto_codigo);
        $this->assertSame('Anillo de plata', $venta->detalles->sole()->descripcion);
        $html = $this->controller->show($venta)->render();
        $this->assertStringContainsString('Ana María López Pérez', $html);
        $this->assertStringContainsString('Anillo de plata', $html);
        $this->assertStringNotContainsString('Otro producto', $html);
        $this->assertStringNotContainsString('Otra dirección', $html);
    }

    public function test_detalles_ajenos_no_se_pueden_incorporar_en_la_edicion(): void
    {
        $venta = $this->crearVenta();
        $otra = $this->crearVenta();
        $this->rechaza(fn () => $this->controller->update($this->request($this->datos([
            'detalles' => [$this->linea(['id' => $otra->detalles->sole()->id])],
        ]), 'PUT'), $venta), ValidationException::class);
        $this->assertSame(2, DetalleVenta::count());
    }

    public function test_rollback_si_falla_el_guardado_del_documento(): void
    {
        DocumentoFel::creating(function () {
            throw new \RuntimeException('Fallo simulado');
        });
        $this->rechaza(fn () => $this->crearVenta(), \RuntimeException::class);
        $this->assertSame(0, Venta::count());
        $this->assertSame(0, DetalleVenta::count());
        $this->assertSame(0, DocumentoFel::count());
    }

    public function test_borradores_no_tocan_inventario_ni_crean_intentos(): void
    {
        $antes = DB::table('inventarios')->get()->toJson();
        $venta = $this->crearVenta(['detalles' => [$this->linea(['cantidad' => '100'])]]);
        $this->controller->update($this->request($this->datos(['detalles' => [$this->linea(['cantidad' => '500'])]]), 'PUT'), $venta);
        $this->assertSame($antes, DB::table('inventarios')->get()->toJson());
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
        $this->assertSame(0, IntentoFel::count());
        $this->assertNull($venta->fresh()->documentoFel->hash_xml);
        $this->assertNull($venta->fresh()->documentoFel->xml_certificado);
    }

    public function test_relaciones_y_constraints_de_las_cuatro_tablas(): void
    {
        $venta = $this->crearVenta();
        $otra = $this->crearVenta();
        $detalle = $venta->detalles->sole();
        $documento = $venta->documentoFel;
        $this->assertSame(11, $venta->cliente->id);
        $this->assertSame(17, $venta->tipoDocumento->id);
        $this->assertSame(6, $venta->metodoPago->id);
        $this->assertSame(7, $venta->usuarioCreador->id);
        $this->assertSame($venta->id, $detalle->venta->id);
        $this->assertSame(31, $detalle->producto->id);
        $this->assertSame($venta->id, $documento->venta->id);
        $this->assertSame(17, $documento->tipoDocumento->id);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $documento->intentos());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, (new IntentoFel)->documentoFel());
        $copia = $documento->getAttributes();
        unset($copia['id']);
        $this->rechaza(fn () => DB::table('documentos_fel')->insert(array_replace($copia, ['referencia' => '00000000-0000-4000-8000-000000000000'])), QueryException::class);
        $this->rechaza(fn () => DB::table('documentos_fel')->where('id', $otra->documentoFel->id)->update(['referencia' => $documento->referencia]), QueryException::class);
        $this->rechaza(fn () => DB::table('documentos_fel')->where('id', $documento->id)->update(['venta_id' => 999]), QueryException::class);
        $copiaDetalle = $detalle->getAttributes();
        unset($copiaDetalle['id']);
        $this->rechaza(fn () => DB::table('detalles_ventas')->insert($copiaDetalle), QueryException::class);
        $this->rechaza(fn () => DB::table('detalles_ventas')->where('id', $detalle->id)->update(['producto_id' => 999]), QueryException::class);
        $this->rechaza(fn () => DB::table('productos')->where('id', 31)->delete(), QueryException::class);
        $this->rechaza(fn () => DB::table('clientes')->where('id', 11)->delete(), QueryException::class);
        $this->rechaza(fn () => DB::table('ventas')->where('id', $venta->id)->delete(), QueryException::class);
        $this->rechaza(fn () => DB::table('intentos_fel')->insert(['documento_fel_id' => 999, 'fecha_inicio' => '2026-10-03 12:00:00']), QueryException::class);
        DB::table('documentos_fel')->where('id', $documento->id)->update(['fel_uuid_normalized' => 'UUID-DE-PRUEBA']);
        $this->rechaza(fn () => DB::table('documentos_fel')->where('id', $otra->documentoFel->id)->update(['fel_uuid_normalized' => 'UUID-DE-PRUEBA']), QueryException::class);
    }

    public function test_modelos_protegen_referencia_estado_y_eliminacion_de_venta(): void
    {
        $venta = $this->crearVenta();
        $documento = $venta->documentoFel;
        $this->rechaza(fn () => $documento->forceFill(['referencia' => 'otra'])->save(), ValidationException::class);
        $this->rechaza(fn () => $documento->fresh()->delete(), ValidationException::class);
        $this->rechaza(fn () => $venta->forceFill(['estado_venta' => 'CONFIRMADA'])->save(), ValidationException::class);
        $this->rechaza(fn () => $venta->fresh()->delete(), ValidationException::class);
        $this->assertSame('BORRADOR', $venta->fresh()->estado_venta);
    }

    public function test_documentos_preparados_con_campos_no_secretos_y_sin_certificacion(): void
    {
        $venta = $this->crearVenta();
        foreach (['xml_solicitud', 'hash_xml', 'xml_certificado', 'fel_uuid', 'fel_uuid_normalized', 'fel_serie', 'fel_numero', 'fecha_certificacion', 'nit_certificador', 'nombre_certificador', 'nit_emisor', 'codigo_establecimiento', 'id_maquina', 'frases', 'fecha_hora_emision'] as $campo) {
            $this->assertTrue(Schema::hasColumn('documentos_fel', $campo));
            $this->assertNull($venta->documentoFel->$campo);
        }
        foreach (Schema::getColumnListing('documentos_fel') as $columna) {
            $this->assertDoesNotMatchRegularExpression('/password|credencial|token|secreto|p_usuario/i', $columna);
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_los_filtros_validan_fechas_importes_y_estructuras_invalidas(): void
    {
        foreach ([['fecha' => 'sin-fecha'], ['importe_total' => '1.123'], ['referencia' => ['dato']], ['estado_venta' => 'OTRO']] as $filtros) {
            $this->rechaza(fn () => $this->controller->index($this->request($filtros, 'GET')), ValidationException::class);
        }
    }

    public function test_migraciones_generan_decimal_longtext_enum_y_unicidad_para_mysql_sin_conectarse(): void
    {
        $mysql = new \Illuminate\Database\MySqlConnection(fn () => throw new \LogicException('No se permite una conexión real.'), 'prueba');
        $mysql->setSchemaGrammar(new \Illuminate\Database\Schema\Grammars\MySqlGrammar);
        $schema = Schema::getFacadeRoot();
        Schema::swap($mysql->getSchemaBuilder());
        try {
            $consultas = $mysql->pretend(function () {
                foreach (['000004_create_ventas_table', '000005_create_detalles_ventas_table', '000006_create_documentos_fel_table', '000007_create_intentos_fel_table'] as $nombre) {
                    (require dirname(__DIR__, 2).'/database/migrations/2026_10_03_'.$nombre.'.php')->up();
                }
            });
        } finally {
            Schema::swap($schema);
        }
        $sql = implode("\n", array_column($consultas, 'query'));
        $this->assertStringContainsString('`precio_unitario` decimal(24, 10)', $sql);
        $this->assertStringContainsString('`importe_total` decimal(20, 2)', $sql);
        $this->assertStringContainsString('`cantidad` bigint unsigned', $sql);
        $this->assertStringContainsString('`xml_solicitud` longtext null', $sql);
        $this->assertStringContainsString('`xml_certificado` longtext null', $sql);
        $this->assertStringContainsString('`respuesta_raw` longtext null', $sql);
        $this->assertStringContainsString("enum('BORRADOR', 'CONFIRMADA', 'ANULADA')", $sql);
        $this->assertStringContainsString('add unique `documentos_fel_venta_id_unique`(`venta_id`)', $sql);
        $this->assertStringContainsString('add unique `documentos_fel_referencia_unique`(`referencia`)', $sql);
        $this->assertStringContainsString('on delete restrict', $sql);
    }

    public function test_los_estados_invalidos_se_rechazan_tambien_por_la_base_aislada(): void
    {
        $venta = $this->crearVenta();
        $this->rechaza(fn () => DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'OTRO']), QueryException::class);
        $this->rechaza(fn () => DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => 'OTRO']), QueryException::class);
        $this->assertSame('BORRADOR', $venta->fresh()->estado_venta);
        $this->assertSame('PENDIENTE', $venta->fresh()->documentoFel->estado_fel);
    }

    public function test_vistas_renderizan_y_listado_filtra_por_datos_historicos(): void
    {
        $venta = $this->crearVenta();
        $this->crearVenta(['cliente_id' => 12]);
        $vista = $this->controller->index($this->request(['receptor_nombre' => 'Ana', 'referencia' => $venta->documentoFel->referencia], 'GET'));
        $this->assertSame([$venta->id], $vista->getData()['ventas']->pluck('id')->all());
        $this->assertStringContainsString('+ Nueva venta', $vista->render());
        $this->assertStringContainsString('detalles[0][cantidad]', $this->controller->create()->render());
        $this->assertStringContainsString('Guardar cambios', $this->controller->edit($venta)->render());
        $this->assertStringContainsString('201.60', $this->controller->show($venta)->render());
        DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => 'CONFIRMADA']);
        $this->assertStringNotContainsString('>Editar</a>', $this->controller->show($venta->fresh())->render());
    }

    public function test_permisos_y_rutas_solo_exponen_el_crud_autorizado(): void
    {
        (new SeguridadSeeder)->run();
        $opcion = Opcion::where('ruta', 'ventas')->sole();
        $this->assertSame('Operaciones', $opcion->modulo->nombre);
        $this->assertSame(['crear', 'eliminar', 'modificar', 'ver'], $opcion->acciones->pluck('clave')->sort()->values()->all());
        foreach (['index', 'create', 'store', 'show', 'edit', 'update'] as $metodo) {
            $ruta = $this->router->getRoutes()->getByName('ventas.'.$metodo);
            $this->assertNotNull($ruta);
            $this->assertContains('auth', $ruta->gatherMiddleware());
            $this->assertContains('permiso', $ruta->gatherMiddleware());
            $request = Request::create('/ventas');
            $request->setRouteResolver(fn () => $ruta);
            $request->setUserResolver(fn () => User::findOrFail(7));
            $this->assertSame(200, (new VerificarPermiso)->handle($request, fn () => new Response)->getStatusCode());
        }
        foreach (['destroy', 'cambiar-estado', 'anular'] as $metodo) {
            $this->assertNull($this->router->getRoutes()->getByName('ventas.'.$metodo));
        }
        $ruta = $this->router->getRoutes()->getByName('ventas.update');
        $request = Request::create('/ventas/1', 'PUT');
        $request->setRouteResolver(fn () => $ruta);
        $request->setUserResolver(fn () => new class
        {
            public function tienePermiso($opcion, $accion): bool
            {
                return false;
            }
        });
        // El middleware usa abort(), que requiere el contenedor de Application.
        $app = new class extends Container
        {
            public function abort($code, $message = '', array $headers = []): never
            {
                throw new HttpException($code, $message);
            }
        };
        Container::setInstance($app);
        $this->rechaza(fn () => (new VerificarPermiso)->handle($request, fn () => new Response), HttpException::class);
    }
}
