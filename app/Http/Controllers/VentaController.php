<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\TipoDocumento;
use App\Models\Venta;
use App\Services\CalculadoraVentaService;
use App\Services\VentaInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    private const CAMPOS_RECEPTOR = [
        'receptor_identificacion', 'receptor_tipo_identificacion_codigo', 'receptor_tipo_identificacion_nombre',
        'receptor_nombre', 'receptor_direccion', 'receptor_codigo_postal',
        'receptor_municipio', 'receptor_municipio_codigo', 'receptor_departamento', 'receptor_departamento_codigo',
        'receptor_pais', 'receptor_pais_codigo', 'receptor_correo',
    ];

    private const CAMPOS_PRODUCTO = ['producto_codigo', 'producto_nombre', 'descripcion', 'unidad_medida', 'bien_servicio'];

    public function __construct(
        private CalculadoraVentaService $calculadora = new CalculadoraVentaService,
        private VentaInventarioService $confirmacion = new VentaInventarioService
    ) {}

    public function index(Request $request)
    {
        $reglas = [
            'id' => ['nullable', 'integer', 'min:1'],
            'estado_venta' => ['nullable', Rule::in(Venta::ESTADOS)],
            'moneda' => ['nullable', Rule::in(['GTQ'])],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_creacion' => ['nullable', 'date_format:Y-m-d'],
            'importe_total' => ['nullable', 'regex:/^[0-9]{1,18}(?:\.[0-9]{1,2})?$/D'],
        ];
        foreach (['receptor_nombre', 'receptor_identificacion', 'observacion', 'tipo_documento', 'metodo_pago', 'creador', 'referencia'] as $filtro) {
            $reglas[$filtro] = ['nullable', 'string', 'max:700'];
        }
        $request->validate($reglas);
        $query = Venta::with(['tipoDocumento', 'metodoPago', 'usuarioCreador', 'documentoFel']);
        foreach (['id', 'estado_venta', 'moneda'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        foreach (['receptor_nombre', 'receptor_identificacion', 'observacion'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, 'like', '%'.$request->input($campo).'%');
            }
        }
        foreach (['fecha' => 'fecha', 'fecha_creacion' => 'created_at'] as $filtro => $columna) {
            if ($request->filled($filtro)) {
                $query->whereDate($columna, $request->input($filtro));
            }
        }
        foreach (['tipo_documento' => ['tipoDocumento', 'codigo'], 'metodo_pago' => ['metodoPago', 'nombre'], 'creador' => ['usuarioCreador', 'name'], 'referencia' => ['documentoFel', 'referencia']] as $filtro => [$relacion, $columna]) {
            if ($request->filled($filtro)) {
                $query->whereHas($relacion, fn ($consulta) => $consulta->where($columna, 'like', '%'.$request->input($filtro).'%'));
            }
        }
        if ($request->filled('importe_total')) {
            $query->where('importe_total', $request->importe_total);
        }
        $ventas = $query->latest()->paginate(10)->withQueryString();

        return view('ventas.index', compact('ventas'));
    }

    public function create()
    {
        return view('ventas.create', $this->catalogos());
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $datos = $request->validate($this->reglas());
            $calculo = $this->calculadora->calcular($datos['detalles']);
            $venta = Venta::create(array_merge(
                Arr::only($datos, ['cliente_id', 'tipo_documento_id', 'metodo_pago_id', 'fecha', 'moneda', 'observacion']),
                $this->snapshotCliente((int) $datos['cliente_id']),
                $calculo['totales'],
                ['usuario_creador_id' => $request->user()->id]
            ));
            $this->guardarDetalles($venta, $datos['detalles'], $calculo['detalles']);
            $venta->documentoFel()->create(['tipo_documento_id' => $venta->tipo_documento_id]);
        });

        return redirect()->route('ventas.index')->with('success', 'Venta BORRADOR creada correctamente.');
    }

    public function show(Venta $venta)
    {
        $venta->load(['detalles', 'documentoFel.ultimoIntento', 'documentoFel.ultimoIntentoConRespuesta', 'tipoDocumento', 'metodoPago', 'usuarioCreador', 'usuarioModificador']);

        return view('ventas.show', compact('venta'));
    }

    public function edit(Venta $venta)
    {
        $venta->refresh()->exigirBorrador();
        $venta->load(['detalles', 'documentoFel']);

        return view('ventas.edit', array_merge($this->catalogos(), compact('venta')));
    }

    public function confirmar(Request $request, Venta $venta)
    {
        $this->confirmacion->confirmarVenta((int) $venta->id, (int) $request->user()->id);

        return redirect()->route('ventas.show', $venta)->with('success', 'Venta confirmada. Inventario actualizado y documento FEL PENDIENTE.');
    }

    public function update(Request $request, Venta $venta)
    {
        DB::transaction(function () use ($request, $venta) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $venta->exigirBorrador();
            $documento = $venta->documentoFel()->lockForUpdate()->firstOrFail();
            if ($documento->estado_fel !== 'PENDIENTE' || $documento->xml_solicitud !== null) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya tiene una solicitud FEL y no puede editarse.']);
            }
            $datos = $request->validate($this->reglas($venta));
            $calculo = $this->calculadora->calcular($datos['detalles']);
            $snapshot = (int) $datos['cliente_id'] === (int) $venta->cliente_id
                ? Arr::only($venta->getAttributes(), self::CAMPOS_RECEPTOR)
                : $this->snapshotCliente((int) $datos['cliente_id']);
            $this->guardarDetalles($venta, $datos['detalles'], $calculo['detalles']);
            $venta->update(array_merge(
                Arr::only($datos, ['cliente_id', 'tipo_documento_id', 'metodo_pago_id', 'fecha', 'moneda', 'observacion']),
                $snapshot, $calculo['totales'], ['usuario_modificador_id' => $request->user()->id]
            ));
        });

        return redirect()->route('ventas.index')->with('success', 'Venta BORRADOR actualizada correctamente.');
    }

    private function reglas(?Venta $venta = null): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('estado', true)],
            'tipo_documento_id' => ['required', 'integer', Rule::exists('tipos_documento', 'id')->where('codigo', 'FACT')->where('estado', true)],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('estado', true)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'moneda' => ['required', Rule::in(['GTQ'])],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'detalles' => ['required', 'array', 'min:1', 'max:200'],
            'detalles.*' => ['required', 'array'],
            'detalles.*.id' => $venta
                ? ['nullable', 'integer', 'distinct', Rule::exists('detalles_ventas', 'id')->where('venta_id', $venta->id)]
                : ['prohibited'],
            'detalles.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('estado', true)],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_unitario' => ['required', 'regex:/^[0-9]{1,14}(?:\.[0-9]{1,6})?$/D'],
            'detalles.*.porcentaje_descuento' => ['required', 'regex:/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D'],
            'detalles.*.tratamiento_tributario' => ['required', Rule::in(CalculadoraVentaService::TRATAMIENTOS)],
        ];
    }

    private function guardarDetalles(Venta $venta, array $datos, array $calculados): void
    {
        $anteriores = $venta->detalles()->get()->keyBy('id');
        $nuevos = [];
        foreach (array_values($datos) as $indice => $detalle) {
            $anterior = $anteriores->get($detalle['id'] ?? null);
            if ($anterior && (int) $anterior->producto_id === (int) $detalle['producto_id']) {
                $snapshot = Arr::only($anterior->getAttributes(), self::CAMPOS_PRODUCTO);
            } else {
                $producto = Producto::whereKey($detalle['producto_id'])->where('estado', true)->firstOrFail();
                $snapshot = [
                    'producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion ?: $producto->nombre,
                    'unidad_medida' => 'UNI', 'bien_servicio' => 'B',
                ];
            }
            $nuevos[] = array_merge($snapshot, $calculados[$indice], [
                'numero_linea' => $indice + 1, 'producto_id' => $detalle['producto_id'],
            ]);
        }
        // Los detalles se reemplazan únicamente mientras la venta es borrador.
        $venta->detalles()->delete();
        $venta->detalles()->createMany($nuevos);
    }

    private function snapshotCliente(int $clienteId): array
    {
        $cliente = Cliente::with(['tipoIdentificacion', 'persona', 'sociedad', 'direccion.municipio.departamento.pais'])
            ->whereKey($clienteId)->where('estado', true)->firstOrFail();
        $direccion = $cliente->direccion;
        $municipio = $direccion?->municipio;
        $departamento = $municipio?->departamento;
        $pais = $departamento?->pais;
        if (! $cliente->tipoIdentificacion?->estado || ! $direccion?->estado || ! $municipio?->estado || ! $departamento?->estado || ! $pais?->estado
            || (bool) $cliente->persona === (bool) $cliente->sociedad) {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente necesita identificación, dirección activa y una clasificación válida.']);
        }
        $nombre = $cliente->sociedad?->nombre ?? implode(' ', array_filter([
            $cliente->persona?->nombre1, $cliente->persona?->nombre2, $cliente->persona?->nombre3,
            $cliente->persona?->apellido1, $cliente->persona?->apellido2, $cliente->persona?->apellido_casada,
        ], fn ($parte) => $parte !== null && trim($parte) !== ''));
        if (trim($nombre) === '') {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente necesita un nombre fiscal.']);
        }

        return [
            'receptor_identificacion' => $cliente->numero_identificacion,
            'receptor_tipo_identificacion_codigo' => $cliente->tipoIdentificacion->codigo,
            'receptor_tipo_identificacion_nombre' => $cliente->tipoIdentificacion->nombre,
            'receptor_nombre' => trim($nombre), 'receptor_direccion' => $direccion->direccion,
            'receptor_codigo_postal' => $direccion->codigo_postal,
            'receptor_municipio' => $municipio->nombre, 'receptor_municipio_codigo' => $municipio->codigo,
            'receptor_departamento' => $departamento->nombre, 'receptor_departamento_codigo' => $departamento->codigo,
            'receptor_pais' => $pais->nombre, 'receptor_pais_codigo' => $pais->codigo,
            'receptor_correo' => $cliente->correo,
        ];
    }

    private function catalogos(): array
    {
        return [
            'clientes' => Cliente::with(['persona', 'sociedad'])->where('estado', true)->orderBy('numero_identificacion')->get(),
            'tiposDocumento' => TipoDocumento::where('codigo', 'FACT')->where('estado', true)->get(),
            'metodosPago' => MetodoPago::where('estado', true)->orderBy('nombre')->get(),
            'productos' => Producto::where('estado', true)->orderBy('nombre')->get(),
        ];
    }
}
