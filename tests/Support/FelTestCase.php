<?php

namespace Tests\Support;

use App\Models\Venta;
use App\Services\AinnovaFelClient;
use App\Services\FelCertificacionService;
use Illuminate\Support\Facades\DB;
use Mockery;

abstract class FelTestCase extends VentasInventarioTestCase
{
    protected array $configuracionFel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuracionFel = [
            'endpoint' => 'https://ainnova.invalid/soap',
            'basic_usuario' => 'basic-usuario-solo-prueba', 'basic_password' => 'basic-password-solo-prueba',
            'ws_usuario' => 'ws-usuario-solo-prueba', 'ws_password' => 'ws-password-solo-prueba',
            'nit_emisor' => '1234567-K', 'establecimiento' => '1', 'id_maquina' => 'TEST-01',
            'timeout' => 60, 'connect_timeout' => 10, 'verificar_ssl' => true,
        ];
        config(['fel.ainnova' => $this->configuracionFel]);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    protected function ventaConfirmada(): Venta
    {
        return $this->confirmacion->confirmarVenta($this->crearVenta()->id, 7);
    }

    protected function certificado(array $cambios = []): string
    {
        $xml = file_get_contents(dirname(__DIR__).'/Fixtures/fel/certificado_fact.xml');

        return str_replace(array_keys($cambios), array_values($cambios), $xml);
    }

    protected function cliente(): AinnovaFelClient
    {
        return Mockery::mock(AinnovaFelClient::class, [$this->configuracionFel])->makePartial();
    }

    protected function servicio(AinnovaFelClient $cliente): FelCertificacionService
    {
        return new FelCertificacionService($cliente);
    }

    protected function protegidas(): array
    {
        $datos = [];
        foreach (['ventas', 'detalles_ventas', 'inventarios', 'movimientos_inventario'] as $tabla) {
            $datos[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }

        return $datos;
    }

    protected function congelados(Venta $venta): array
    {
        return (array) DB::table('documentos_fel')->where('venta_id', $venta->id)->first(['referencia', 'xml_solicitud', 'hash_xml']);
    }
}
