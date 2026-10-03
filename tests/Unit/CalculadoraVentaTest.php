<?php

namespace Tests\Unit;

use App\Services\CalculadoraVentaService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class CalculadoraVentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $container));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function linea(array $cambios = []): array
    {
        return array_replace(['cantidad' => '3', 'precio_unitario' => '112.123456',
            'porcentaje_descuento' => '12.5000', 'tratamiento_tributario' => 'GRAVADO'], $cambios);
    }

    public function test_calculo_gravado_exactamente_a_centavos_y_sin_decimales_binarios(): void
    {
        $linea = (new CalculadoraVentaService)->calcularLinea($this->linea());
        $this->assertSame('336.37', $linea['importe_bruto']);
        $this->assertSame('42.05', $linea['importe_descuento']);
        $this->assertSame('294.32', $linea['importe_total']);
        $this->assertSame('262.79', $linea['importe_neto']);
        $this->assertSame('31.53', $linea['importe_iva']);
        $this->assertSame('0.00', $linea['importe_exento']);
        $this->assertSame('112.1234560000', $linea['precio_unitario']);
        $this->assertSame('12.5000', $linea['porcentaje_descuento']);
        foreach ($linea as $valor) {
            $this->assertIsString($valor);
        }
    }

    public function test_exento_y_totales_mixtos_son_suma_de_lineas_redondeadas(): void
    {
        $resultado = (new CalculadoraVentaService)->calcular([
            $this->linea(['cantidad' => '1', 'precio_unitario' => '112', 'porcentaje_descuento' => '0']),
            $this->linea(['cantidad' => '2', 'precio_unitario' => '25.555555', 'porcentaje_descuento' => '10', 'tratamiento_tributario' => 'EXENTO']),
        ]);
        $this->assertSame([
            'importe_bruto' => '163.11', 'importe_descuento' => '5.11', 'importe_exento' => '46.00',
            'importe_neto' => '100.00', 'importe_iva' => '12.00', 'importe_total' => '158.00',
        ], $resultado['totales']);
        $this->assertSame('0.0000', $resultado['detalles'][1]['tasa_iva']);
    }

    public function test_redondeo_mitad_hacia_arriba_precio_alto_y_descuento_completo(): void
    {
        $calculadora = new CalculadoraVentaService;
        $linea = $calculadora->calcularLinea($this->linea(['cantidad' => '1', 'precio_unitario' => '0.005', 'porcentaje_descuento' => '0']));
        $this->assertSame('0.01', $linea['importe_bruto']);
        $this->assertSame('0.01', $linea['importe_total']);
        $linea = $calculadora->calcularLinea($this->linea(['cantidad' => '1', 'precio_unitario' => '99999999999999.999999', 'porcentaje_descuento' => '0']));
        $this->assertSame('100000000000000.00', $linea['importe_total']);
        $linea = $calculadora->calcularLinea($this->linea(['porcentaje_descuento' => '100']));
        $this->assertSame('0.00', $linea['importe_total']);
        $this->assertSame('0.00', $linea['importe_iva']);
    }

    public static function valoresInvalidos(): array
    {
        return [
            ['cantidad', '0'], ['cantidad', '-1'], ['cantidad', '1.5'], ['cantidad', '1.0'], ['cantidad', '1e2'],
            ['cantidad', 1.0], ['cantidad', '9223372036854775808'],
            ['precio_unitario', '1.0000001'], ['precio_unitario', '-1'], ['precio_unitario', 0.1],
            ['precio_unitario', '100000000000000'], ['porcentaje_descuento', '100.0001'],
            ['porcentaje_descuento', '1.00001'], ['tratamiento_tributario', 'OTRO'],
        ];
    }

    /** @dataProvider valoresInvalidos */
    public function test_rechaza_valores_fuera_de_precision_y_cantidades_fraccionarias(string $campo, mixed $valor): void
    {
        $this->expectException(ValidationException::class);
        (new CalculadoraVentaService)->calcularLinea($this->linea([$campo => $valor]));
    }

    public function test_rechaza_desbordamiento_monetario_por_linea(): void
    {
        $this->expectException(ValidationException::class);
        (new CalculadoraVentaService)->calcularLinea($this->linea(['cantidad' => '10001', 'precio_unitario' => '99999999999999', 'porcentaje_descuento' => '0']));
    }

    public function test_rechaza_desbordamiento_de_la_suma(): void
    {
        $this->expectException(ValidationException::class);
        $linea = $this->linea(['cantidad' => '6000', 'precio_unitario' => '99999999999999', 'porcentaje_descuento' => '0']);
        (new CalculadoraVentaService)->calcular([$linea, $linea]);
    }
}
