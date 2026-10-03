<?php

namespace Tests\Unit;

use App\Data\RespuestaAinnova;
use App\Http\Controllers\FelController;
use App\Http\Middleware\VerificarPermiso;
use App\Models\DocumentoFel;
use App\Models\IntentoFel;
use App\Services\AinnovaFelClient;
use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\Support\FelTestCase;

require_once dirname(__DIR__).'/Support/VentasTestCase.php';
require_once dirname(__DIR__).'/Support/VentasInventarioTestCase.php';
require_once dirname(__DIR__).'/Support/FelTestCase.php';

class FelCertificacionTest extends FelTestCase
{
    public function test_respuesta_soap_completa_se_procesa_sin_guardar_envelope_como_certificado(): void
    {
        $venta = $this->ventaConfirmada();
        $raw = file_get_contents(dirname(__DIR__).'/Fixtures/fel/respuesta_soap_ainnova.xml');
        $antes = $this->protegidas();
        $congelados = $this->congelados($venta);
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, respuestaRaw: $raw));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $this->certificado())), $documento->xml_certificado);
        $this->assertSame($raw, $documento->ultimoIntento->respuesta_raw);
        $this->assertSame($antes, $this->protegidas());
        $this->assertSame($congelados, $this->congelados($venta));
    }

    public function test_reserva_hace_commit_envia_xml_exacto_y_certifica_sin_modificar_venta_ni_stock(): void
    {
        $venta = $this->ventaConfirmada();
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $certificado = $this->certificado();
        $raw = '<SOAP_DE_PRUEBA>'.$certificado.'</SOAP_DE_PRUEBA>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->with($congelados['xml_solicitud'], 1, 'D')->andReturnUsing(function () use ($venta, $certificado, $raw) {
            $this->assertSame(0, DB::connection()->transactionLevel());
            $this->assertSame('EN_PROCESO', $venta->fresh()->documentoFel->estado_fel);
            $intento = IntentoFel::sole();
            $this->assertSame('EN_PROCESO', $intento->resultado);
            $this->assertSame(7, $intento->usuario_id);
            $this->assertSame(7, $intento->usuario->id);
            $this->assertNull($intento->fecha_fin);

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $certificado, $raw, duracionMs: 100);
        });
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame('8e511596-ff36-49a8-9dc5-8f482cd2a3dc', $documento->fel_uuid);
        $this->assertSame('8E511596-FF36-49A8-9DC5-8F482CD2A3DC', $documento->fel_uuid_normalized);
        $this->assertSame('A001', $documento->fel_serie);
        $this->assertSame('000123', $documento->fel_numero);
        $this->assertSame('2026-10-03 20:15:16', $documento->fecha_certificacion->format('Y-m-d H:i:s'));
        $this->assertSame('9876543K', $documento->nit_certificador);
        $this->assertSame('Certificador & Pruebas', $documento->nombre_certificador);
        $this->assertSame($certificado, $documento->xml_certificado);
        $this->assertSame($raw, $documento->ultimoIntento->respuesta_raw);
        $this->assertSame('CERTIFICADA', $documento->ultimoIntento->resultado);
        $this->assertNotNull($documento->ultimoIntento->fecha_fin);
        $this->assertNull($documento->ultimoIntento->mensaje_error);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
        $this->assertSame(1, DocumentoFel::count());
        $this->assertSame(1, IntentoFel::count());
    }

    /** @dataProvider respuestasFallidas */
    public function test_respuestas_fallidas_conservan_raw_y_no_cambian_venta_inventario_o_xml(RespuestaAinnova $respuesta, string $estado, ?string $codigo): void
    {
        $venta = $this->ventaConfirmada();
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn($respuesta);
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame($estado, $documento->estado_fel);
        $this->assertSame($estado, $documento->ultimoIntento->resultado);
        $this->assertSame($codigo, $documento->ultimoIntento->codigo_error);
        $this->assertSame($respuesta->respuestaRaw ?? $respuesta->texto, $documento->ultimoIntento->respuesta_raw);
        $this->assertNotNull($documento->ultimoIntento->fecha_fin);
        $this->assertNull($documento->xml_certificado);
        $this->assertNull($documento->fel_uuid);
        if ($estado === 'ERROR') {
            $this->assertNotEmpty($documento->ultimoIntento->mensaje_error);
        } else {
            $this->assertNotEmpty($documento->ultimoIntento->error_tecnico);
        }
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
    }

    public static function respuestasFallidas(): array
    {
        return [
            'rechazo XML código alfanumérico' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, '<Resultado><Error><Codigo>E-NIT</Codigo><Mensaje>NIT inválido</Mensaje></Error></Resultado>'), 'ERROR', 'E-NIT'],
            'rechazo XML código numérico' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, '<Error><Codigo>42</Codigo><Mensaje>NIT inválido</Mensaje></Error>'), 'ERROR', '42'],
            'rechazo textual sin código' => [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'Documento rechazado por identificación inválida'), 'ERROR', null],
            'error textual inequívoco' => [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'ERROR: documento con NIT inválido'), 'ERROR', null],
            'texto ambiguo' => [new RespuestaAinnova(RespuestaAinnova::TEXTUAL, 'Operación finalizada'), 'INCIERTA', null],
            'error interno ambiguo' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, '<Error><Mensaje>Error interno del servidor</Mensaje></Error>'), 'INCIERTA', null],
            'timeout' => [new RespuestaAinnova(RespuestaAinnova::TIMEOUT, respuestaRaw: 'Respuesta parcial'), 'INCIERTA', null],
            'transporte' => [new RespuestaAinnova(RespuestaAinnova::TRANSPORTE), 'INCIERTA', null],
            'SOAP Fault' => [new RespuestaAinnova(RespuestaAinnova::SOAP_FAULT, respuestaRaw: '<Fault>Sin resultado concluyente</Fault>'), 'INCIERTA', null],
            'autenticación HTTP' => [new RespuestaAinnova(RespuestaAinnova::HTTP), 'INCIERTA', null],
            'vacía' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, ''), 'INCIERTA', null],
            'ilegible' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA), 'INCIERTA', null],
            'XML inválido' => [new RespuestaAinnova(RespuestaAinnova::RESPUESTA, '<GTDocumento'), 'INCIERTA', null],
        ];
    }

    /** @dataProvider certificadosInvalidos */
    public function test_certificado_incompatible_o_incompleto_termina_incierta(array $cambios): void
    {
        $venta = $this->ventaConfirmada();
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $xml = $this->certificado($cambios);
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $this->assertSame($xml, $documento->ultimoIntento->respuesta_raw);
        $this->assertNull($documento->xml_certificado);
        $this->assertNull($documento->fel_uuid);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
    }

    public static function certificadosInvalidos(): array
    {
        return [
            'total distinto' => [['201.60' => '201.61']],
            'total fraccionario distinto' => [['201.60' => '201.6000001']],
            'total exponencial' => [['201.60' => '2.016e2']],
            'NIT distinto' => [['1234567K' => '1111111K']],
            'Tipo distinto' => [['Tipo="FACT"' => 'Tipo="FCAM"']],
            'UUID vacío' => [['8e511596-ff36-49a8-9dc5-8f482cd2a3dc' => '']],
            'serie faltante' => [[' Serie="A001"' => '']],
            'número faltante' => [[' Numero="000123"' => '']],
            'fecha inválida' => [['2026-10-03T14:15:16-06:00' => '2026-02-30T14:15:16-06:00']],
            'fecha relativa' => [['2026-10-03T14:15:16-06:00' => 'tomorrow']],
            'certificador faltante' => [['<dte:NITCertificador>9876543K</dte:NITCertificador>' => '']],
            'namespace incorrecto' => [['http://www.sat.gob.gt/dte/fel/0.2.0' => 'urn:incorrecto']],
            'UUID duplicado' => [['</dte:NumeroAutorizacion>' => '</dte:NumeroAutorizacion><dte:NumeroAutorizacion Serie="X" Numero="1">otro</dte:NumeroAutorizacion>']],
        ];
    }

    public function test_wrapper_resultado_aisla_certificado_y_valida_total_con_mas_decimales(): void
    {
        $venta = $this->ventaConfirmada();
        $interior = preg_replace('/^<\?xml[^>]*>\s*/', '', $this->certificado(['201.60' => '201.600000']));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Resultado>'.$interior.'</Resultado>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(rtrim($interior), $documento->xml_certificado);
        $this->assertSame($xml, $documento->ultimoIntento->respuesta_raw);
    }

    public function test_resultado_con_cdata_extrae_xml_certificado_sin_reformatearlo(): void
    {
        $venta = $this->ventaConfirmada();
        $interior = $this->certificado();
        $xml = '<Resultado><![CDATA['.$interior.']]></Resultado>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame($interior, $documento->xml_certificado);
        $this->assertSame($xml, $documento->ultimoIntento->respuesta_raw);
    }

    public function test_certificado_utf8_con_bom_conserva_todos_los_bytes(): void
    {
        $venta = $this->ventaConfirmada();
        $xml = "\xEF\xBB\xBF".$this->certificado();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame($xml, $documento->xml_certificado);
    }

    public function test_namespace_predeterminado_y_wrapper_resultado_son_soportados(): void
    {
        $venta = $this->ventaConfirmada();
        $interior = preg_replace('/^<\?xml[^>]*>\s*/', '', $this->certificado());
        $interior = str_replace(['xmlns:dte=', 'dte:'], ['xmlns=', ''], $interior);
        $xml = '<Resultado>'.$interior.'</Resultado>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(rtrim($interior), $documento->xml_certificado);
    }

    public function test_xml_supuestamente_certificado_con_namespace_incorrecto_no_es_rechazo_explicito(): void
    {
        $venta = $this->ventaConfirmada();
        $xml = '<GTDocumento xmlns="urn:incompatible"><Error><Mensaje>NIT inválido</Mensaje></Error></GTDocumento>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $this->assertSame($xml, $documento->ultimoIntento->respuesta_raw);
    }

    public function test_entidades_externas_no_se_resuelven_y_dejan_incierta(): void
    {
        $venta = $this->ventaConfirmada();
        $xml = '<!DOCTYPE Resultado [<!ENTITY externa SYSTEM "file:///no-existe-fel-prueba">]><Resultado>&externa;</Resultado>';
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $xml));
        $resoluciones = 0;
        libxml_set_external_entity_loader(function () use (&$resoluciones) {
            $resoluciones++;

            return null;
        });
        try {
            $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
            $this->assertSame('INCIERTA', $documento->estado_fel);
            $this->assertSame(0, $resoluciones);
        } finally {
            libxml_set_external_entity_loader(null);
        }
    }

    /** @dataProvider estadosNoEnviables */
    public function test_estado_fel_no_pendiente_no_se_envia(string $estado): void
    {
        $venta = $this->ventaConfirmada();
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => $estado]);
        $antes = $this->snapshot();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function estadosNoEnviables(): array
    {
        return [['EN_PROCESO'], ['CERTIFICADA'], ['ERROR'], ['INCIERTA'], ['ANULADA']];
    }

    public function test_borrador_y_anulada_no_se_certifican(): void
    {
        $venta = $this->crearVenta();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        foreach (['BORRADOR', 'ANULADA'] as $estado) {
            DB::table('ventas')->where('id', $venta->id)->update(['estado_venta' => $estado]);
            $antes = $this->snapshot();
            $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
            $this->assertSame($antes, $this->snapshot());
        }
    }

    /** @dataProvider documentosAlterados */
    public function test_hash_o_contexto_alterado_rechaza_antes_de_llamar(array $cambios): void
    {
        $venta = $this->ventaConfirmada();
        DB::table('documentos_fel')->where('venta_id', $venta->id)->update($cambios);
        $antes = $this->snapshot();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function documentosAlterados(): array
    {
        return array_map(fn ($cambio) => [$cambio], [
            ['hash_xml' => str_repeat('0', 64)], ['xml_solicitud' => '<Alterado/>'], ['xml_solicitud' => ''],
            ['xml_solicitud' => null], ['p_tipo_doc' => 2], ['p_tipo_respuesta' => 'C'], ['nit_emisor' => 'OTRO'],
        ]);
    }

    public function test_doble_clic_concurrente_y_posterior_no_crean_segundo_envio(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $servicio = $this->servicio($cliente);
        $cliente->shouldReceive('generarDocumento')->once()->andReturnUsing(function () use ($servicio, $venta) {
            $this->rechaza(fn () => $servicio->certificarVenta($venta->id, 7), ValidationException::class);
            $this->assertSame(1, IntentoFel::count());

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado());
        });
        $servicio->certificarVenta($venta->id, 7);
        $this->rechaza(fn () => $servicio->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame(1, IntentoFel::count());
    }

    public function test_transaccion_externa_se_rechaza_para_no_llamar_con_locks_activos(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        DB::transaction(function () use ($cliente, $venta) {
            $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        });
        $this->assertSame(0, IntentoFel::count());
    }

    public function test_excepcion_de_red_no_filtra_mensaje_o_credenciales_y_no_reenvia(): void
    {
        $venta = $this->ventaConfirmada();
        $protegidas = $this->protegidas();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andThrow(new RuntimeException('Conexión interrumpida '.$this->configuracionFel['ws_password']));
        Log::swap(Mockery::mock()->shouldIgnoreMissing());
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $this->assertStringNotContainsString($this->configuracionFel['ws_password'], IntentoFel::sole()->toJson());
        $this->assertSame($protegidas, $this->protegidas());
        Log::getFacadeRoot()->shouldNotHaveReceived('error');
        Log::getFacadeRoot()->shouldNotHaveReceived('info');
    }

    public function test_respuesta_que_devuelve_secretos_se_oculta_y_no_se_certifica(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $secretos = implode(' ', array_intersect_key($this->configuracionFel, array_flip(['basic_usuario', 'basic_password', 'ws_usuario', 'ws_password'])));
        $raw = '<Error><Mensaje>ERROR: '.$secretos.'</Mensaje><pPassword>otro-secreto</pPassword></Error>';
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $raw));
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame('INCIERTA', $documento->estado_fel);
        $datos = IntentoFel::sole()->toJson().$documento->toJson().$this->controller->show($venta->fresh())->render();
        foreach (['basic_usuario', 'basic_password', 'ws_usuario', 'ws_password'] as $campo) {
            $this->assertStringNotContainsString($this->configuracionFel[$campo], $datos);
        }
        $this->assertStringNotContainsString('otro-secreto', $datos);
        $this->assertStringContainsString('[REDACTADO]', $documento->ultimoIntento->respuesta_raw);
    }

    public function test_fallo_de_persistencia_final_revierte_resultado_y_deja_reserva_para_conciliacion(): void
    {
        $venta = $this->ventaConfirmada();
        $protegidas = $this->protegidas();
        $congelados = $this->congelados($venta);
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        IntentoFel::updating(fn () => throw new RuntimeException('Fallo de persistencia simulado'));
        $servicio = $this->servicio($cliente);
        $this->rechaza(fn () => $servicio->certificarVenta($venta->id, 7), RuntimeException::class);
        $documento = $venta->fresh()->documentoFel;
        $this->assertSame('EN_PROCESO', $documento->estado_fel);
        $this->assertNull($documento->xml_certificado);
        $this->assertSame('EN_PROCESO', IntentoFel::sole()->resultado);
        $this->assertNull(IntentoFel::sole()->fecha_fin);
        $this->assertNull(IntentoFel::sole()->respuesta_raw);
        $this->assertSame($congelados, $this->congelados($venta));
        $this->assertSame($protegidas, $this->protegidas());
        $this->rechaza(fn () => $servicio->certificarVenta($venta->id, 7), ValidationException::class);
    }

    public function test_deadlock_al_persistir_reintenta_solo_bd_y_llama_soap_una_vez(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        $guardados = 0;
        IntentoFel::updating(function () use (&$guardados) {
            if (++$guardados < 3) {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        });
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame(3, $guardados);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(1, IntentoFel::count());
    }

    public function test_deadlock_al_reservar_revierte_intento_y_envia_solo_despues_del_commit(): void
    {
        $venta = $this->ventaConfirmada();
        $reservas = 0;
        IntentoFel::created(function () use (&$reservas) {
            if (++$reservas === 1) {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        });
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturnUsing(function () {
            $this->assertSame(0, DB::connection()->transactionLevel());
            $this->assertSame(1, IntentoFel::count());

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado());
        });
        $documento = $this->servicio($cliente)->certificarVenta($venta->id, 7);
        $this->assertSame(2, $reservas);
        $this->assertSame('CERTIFICADA', $documento->estado_fel);
        $this->assertSame(1, IntentoFel::count());
    }

    public function test_intento_pendiente_abandonado_no_se_recupera_automaticamente(): void
    {
        $venta = $this->ventaConfirmada();
        IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'resultado' => 'EN_PROCESO', 'fecha_inicio' => '2026-01-01 00:00:00', 'usuario_id' => 7]);
        $antes = $this->snapshot();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_auditoria_del_intento_tiene_fk_restrict_y_migracion_mysql_nullable(): void
    {
        DB::table('users')->insert(['id' => 15, 'name' => 'Otro usuario', 'email' => 'otro@example.test', 'password' => 'sin-uso']);
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::TIMEOUT));
        $this->servicio($cliente)->certificarVenta($venta->id, 15);
        $this->rechaza(fn () => DB::table('users')->where('id', 15)->delete(), QueryException::class);

        $mysql = new \Illuminate\Database\MySqlConnection(fn () => throw new RuntimeException('No se permite una conexión real.'), 'aislada');
        $mysql->setSchemaGrammar(new \Illuminate\Database\Schema\Grammars\MySqlGrammar);
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        \Illuminate\Support\Facades\Schema::swap($mysql->getSchemaBuilder());
        try {
            $queries = $mysql->pretend(function () {
                (require dirname(__DIR__, 2).'/database/migrations/2026_10_03_000009_agregar_usuario_a_intentos_fel.php')->up();
            });
        } finally {
            \Illuminate\Support\Facades\Schema::swap($schema);
        }
        $sql = implode("\n", array_column($queries, 'query'));
        $this->assertStringContainsString('`usuario_id` bigint unsigned null', $sql);
        $this->assertStringContainsString('references `users` (`id`) on delete restrict', $sql);
    }

    public function test_usuario_sin_permiso_no_puede_certificar_ni_ver_boton(): void
    {
        $venta = $this->ventaConfirmada();
        $opcion = DB::table('opciones')->where('ruta', 'ventas')->value('id');
        $accion = DB::table('acciones')->where('clave', 'modificar')->value('id');
        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion)->where('accion_id', $accion)->delete();
        $this->assertStringNotContainsString('Certificar FEL', $this->controller->show($venta)->render());
        $request = $this->request([], 'POST');
        $request->setRouteResolver(fn () => $this->router->getRoutes()->getByName('ventas.certificar'));
        $app = new class extends Container
        {
            public function abort($code, $message = '', array $headers = []): never
            {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
            }
        };
        Container::setInstance($app);
        $this->rechaza(fn () => (new VerificarPermiso)->handle($request, fn () => $this->fail('No debe ingresar a certificar.')), \Symfony\Component\HttpKernel\Exception\HttpException::class);
    }

    public function test_respuesta_de_intento_que_ya_no_es_actual_no_sobrescribe_documento(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturnUsing(function () use ($venta) {
            IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'fecha_inicio' => now(), 'resultado' => 'EN_PROCESO', 'usuario_id' => 7]);

            return new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado());
        });
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame('EN_PROCESO', $venta->fresh()->documentoFel->estado_fel);
        $this->assertNull($venta->fresh()->documentoFel->xml_certificado);
        $this->assertSame(0, IntentoFel::whereNotNull('fecha_fin')->count());
    }

    public function test_fallo_en_reserva_no_envia_y_usuario_es_fk_restrict(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldNotReceive('generarDocumento');
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 999), QueryException::class);
        $this->assertSame($antes, $this->snapshot());
        IntentoFel::creating(fn () => false);
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), RuntimeException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_configuracion_incompleta_no_reserva_ni_envia(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = new AinnovaFelClient([]);
        $antes = $this->snapshot();
        $this->rechaza(fn () => $this->servicio($cliente)->certificarVenta($venta->id, 7), ValidationException::class);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_ruta_y_controller_usar_permiso_modificar_y_mostrar_certificacion(): void
    {
        $venta = $this->ventaConfirmada();
        $cliente = $this->cliente();
        $cliente->shouldReceive('generarDocumento')->once()->andReturn(new RespuestaAinnova(RespuestaAinnova::RESPUESTA, $this->certificado()));
        $ruta = $this->router->getRoutes()->getByName('ventas.certificar');
        $this->assertSame(['POST'], $ruta->methods());
        $this->assertContains('auth', $ruta->gatherMiddleware());
        $this->assertContains('permiso', $ruta->gatherMiddleware());
        $this->assertSame('modificar', VerificarPermiso::ACCIONES_POR_METODO['certificar']);
        $request = $this->request([], 'POST');
        $request->setRouteResolver(fn () => $ruta);
        $controller = new FelController($this->servicio($cliente));
        $respuesta = (new VerificarPermiso)->handle($request, fn ($request) => $controller->certificar($request, $venta));
        $this->assertSame('http://localhost/ventas/'.$venta->id, $respuesta->getTargetUrl());
        $html = $this->controller->show($venta->fresh())->render();
        foreach (['8e511596-ff36-49a8-9dc5-8f482cd2a3dc', 'A001', '000123', 'Certificador &amp; Pruebas', '9876543K', '03/10/2026 14:15:16'] as $dato) {
            $this->assertStringContainsString($dato, $html);
        }
        $this->assertStringNotContainsString('Certificar FEL', $html);
        $this->assertStringNotContainsString('>Editar</a>', $html);
    }

    public function test_ui_solo_pendiente_ofrece_certificar_y_error_escapa_html(): void
    {
        $venta = $this->ventaConfirmada();
        $this->assertStringContainsString('Certificar FEL', $this->controller->show($venta)->render());
        IntentoFel::create(['documento_fel_id' => $venta->documentoFel->id, 'fecha_inicio' => now(), 'resultado' => 'ERROR', 'mensaje_error' => 'ERROR: <script>alert(1)</script>', 'usuario_id' => 7]);
        foreach (['EN_PROCESO', 'CERTIFICADA', 'ERROR', 'INCIERTA'] as $estado) {
            DB::table('documentos_fel')->where('venta_id', $venta->id)->update(['estado_fel' => $estado]);
            $html = $this->controller->show($venta->fresh())->render();
            $this->assertStringNotContainsString('Certificar FEL', $html);
            $this->assertStringNotContainsString('Reintentar', $html);
            if ($estado === 'ERROR') {
                $this->assertStringContainsString('&lt;script&gt;', $html);
                $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            }
            if ($estado === 'INCIERTA') {
                $this->assertStringContainsString('requiere conciliación', $html);
            }
        }
    }
}
