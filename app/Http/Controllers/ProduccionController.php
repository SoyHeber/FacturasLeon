<?php

namespace App\Http\Controllers;

use App\Models\Produccion;
use App\Models\Producto;
use App\Services\CalculadoraProduccionService;
use App\Services\ProduccionInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProduccionController extends Controller
{
    private CalculadoraProduccionService $calculadora;

    public function __construct(?CalculadoraProduccionService $calculadora = null)
    {
        $this->calculadora = $calculadora ?? new CalculadoraProduccionService;
    }

    public function index(Request $request)
    {
        $query = Produccion::with([
            'producto',
            'usuario',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('producto')) {
            $query->whereHas('producto', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%'.$request->producto.'%'
                );
            });
        }

        if ($request->filled('usuario')) {
            $query->whereHas('usuario', function ($q) use ($request) {
                $q->where(
                    'name',
                    'like',
                    '%'.$request->usuario.'%'
                );
            });
        }

        if ($request->filled('cantidad')) {
            $query->where(
                'cantidad',
                $request->cantidad
            );
        }

        if ($request->filled('fecha_produccion')) {
            $query->whereDate(
                'fecha_produccion',
                $request->fecha_produccion
            );
        }

        if ($request->filled('observacion')) {
            $query->where(
                'observacion',
                'like',
                '%'.$request->observacion.'%'
            );
        }

        if ($request->filled('estado_produccion')) {
            $query->where(
                'estado_produccion',
                $request->estado_produccion
            );
        }

        $producciones = $query
            ->orderBy('fecha_produccion', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'producciones.index',
            compact('producciones')
        );
    }

    public function create(Request $request)
    {
        $productos = Producto::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        $datosFormulario = $this->datosFormulario($request);
        $estimacion = $this->estimarFormulario($datosFormulario);

        return view('producciones.create', compact('productos', 'datosFormulario') + $estimacion);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',
            ],

            'cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],

            'fecha_produccion' => [
                'required',
                'date',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

        ]);

        $cantidad = $this->calculadora->normalizarCantidadProduccion($validated['cantidad']);
        $produccion = new Produccion([
            'producto_id' => $validated['producto_id'],

            'user_id' => Auth::id(),

            'cantidad' => $cantidad,

            'fecha_produccion' => $validated['fecha_produccion'],

            'observacion' => $validated['observacion'] ?? null,

            'estado' => true,
        ]);
        $produccion->forceFill([
            'estado_produccion' => Produccion::BORRADOR,
            'inventario_aplicado' => false,
            'fecha_confirmacion' => null,
            'confirmado_por_id' => null,
            'fecha_anulacion' => null,
            'anulado_por_id' => null,
        ])->save();

        return redirect()
            ->route('producciones.index')
            ->with(
                'success',
                'Producción registrada correctamente.'
            );
    }

    public function show(Produccion $produccion)
    {
        $produccion->load([
            'producto',
            'usuario',
            'confirmadoPor',
            'anuladoPor',
            'consumos',
            'movimientosInventarioCompra',
        ]);

        $estimacion = $produccion->estado_produccion === Produccion::BORRADOR ? $this->estimarFormulario([
            'producto_id' => $produccion->producto_id,
            'cantidad' => $produccion->cantidad,
        ]) : ['estimacion' => [], 'errorEstimacion' => null];

        return view('producciones.show', compact('produccion') + $estimacion);
    }

    public function edit(Produccion $produccion, ?Request $request = null)
    {
        $this->exigirBorrador($produccion);

        $productos = Producto::where(
            'estado',
            true
        )
            ->orWhere(
                'id',
                $produccion->producto_id
            )
            ->orderBy('nombre')
            ->get();

        $datosFormulario = $this->datosFormulario($request ?? request(), $produccion);
        $estimacion = $this->estimarFormulario($datosFormulario);

        return view('producciones.edit', compact('produccion', 'productos', 'datosFormulario') + $estimacion);
    }

    public function update(
        Request $request,
        Produccion $produccion
    ) {
        DB::transaction(function () use ($request, $produccion) {
            $produccion = Produccion::whereKey($produccion->id)->lockForUpdate()->firstOrFail();
            $this->exigirBorrador($produccion);

            $validated = $request->validate([
                'producto_id' => [
                    'required',
                    'exists:productos,id',
                ],

                'cantidad' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:4294967295',
                ],

                'fecha_produccion' => [
                    'required',
                    'date',
                ],

                'observacion' => [
                    'nullable',
                    'string',
                ],

            ]);

            $produccion->update([
                'producto_id' => $validated['producto_id'],

                'cantidad' => $this->calculadora->normalizarCantidadProduccion($validated['cantidad']),

                'fecha_produccion' => $validated['fecha_produccion'],

                'observacion' => $validated['observacion'] ?? null,

            ]);
        });

        return redirect()
            ->route('producciones.index')
            ->with(
                'success',
                'Producción actualizada correctamente.'
            );
    }

    public function cambiarEstado(
        Produccion $produccion
    ) {
        throw ValidationException::withMessages(['produccion' => 'Las producciones no pueden activarse, inactivarse ni reactivarse. Use cancelar o anular.']);
    }

    public function confirmar(Produccion $produccion, ProduccionInventarioService $servicio)
    {
        $servicio->confirmarProduccion((int) $produccion->id, (int) Auth::id());

        return redirect()->route('producciones.show', $produccion->id)
            ->with('success', 'Producción confirmada. Se registraron los consumos y las salidas de materias primas.');
    }

    public function anular(Produccion $produccion, ProduccionInventarioService $servicio)
    {
        $resultado = $servicio->anularProduccion((int) $produccion->id, (int) Auth::id());

        return redirect()->route('producciones.show', $produccion->id)->with('success', $resultado->inventario_aplicado
            ? 'Producción anulada. Se devolvieron las materias primas al inventario.'
            : 'Producción cancelada sin afectar inventario.');
    }

    private function datosFormulario(Request $request, ?Produccion $produccion = null): array
    {
        $datos = [
            'producto_id' => old('producto_id', $request->input('producto_id', $produccion?->producto_id)),
            'cantidad' => old('cantidad', $request->input('cantidad', $produccion?->cantidad ?? 1)),
            'fecha_produccion' => old('fecha_produccion', $request->input('fecha_produccion', ($produccion?->fecha_produccion ?? now())->format('Y-m-d\\TH:i'))),
            'observacion' => old('observacion', $request->input('observacion', $produccion?->observacion)),
        ];

        return array_map(fn ($valor) => is_scalar($valor) || $valor === null ? $valor : '', $datos);
    }

    private function estimarFormulario(array $datos): array
    {
        $resultado = ['estimacion' => [], 'errorEstimacion' => null];
        if (empty($datos['producto_id'])) {
            return $resultado;
        }

        try {
            $entrada = new Request($datos);
            $validados = $entrada->validate([
                'producto_id' => ['required', 'integer', 'exists:productos,id'],
                'cantidad' => ['required', 'integer', 'min:1', 'max:4294967295'],
            ]);
            $resultado['estimacion'] = $this->calculadora->calcular(new Produccion($validados));
        } catch (ValidationException $exception) {
            $resultado['errorEstimacion'] = implode(' ', array_merge(...array_values($exception->errors())));
        }

        return $resultado;
    }

    private function exigirBorrador(Produccion $produccion): void
    {
        if (! $produccion->esEditable()) {
            throw ValidationException::withMessages([
                'produccion' => 'Solo se pueden modificar producciones en BORRADOR sin inventario aplicado. Las LEGADA son únicamente históricas.',
            ]);
        }
    }
}
