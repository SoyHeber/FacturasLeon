<?php

namespace App\Http\Controllers;

use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use App\Services\InventarioCompraService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioCompraController extends Controller
{
    public function __construct(private InventarioCompraService $inventarioCompraService) {}

    public function index(Request $request)
    {
        $query = MovimientoInventarioCompra::with([
            'inventarioCompra',
            'detalleCompra.compra',
            'produccion',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('inventario_compra')) {
            $query->whereHas(
                'inventarioCompra',
                function ($q) use ($request) {
                    $q->where(
                        'nombre',
                        'like',
                        '%'.$request->inventario_compra.'%'
                    );
                }
            );
        }

        if ($request->filled('detalle_compra_id')) {
            $query->where(
                'detalle_compra_id',
                $request->detalle_compra_id
            );
        }

        if ($request->filled('produccion_id')) {
            $query->where(
                'produccion_id',
                $request->produccion_id
            );
        }

        if ($request->filled('tipo_movimiento')) {
            $query->where(
                'tipo_movimiento',
                $request->tipo_movimiento
            );
        }

        if ($request->filled('cantidad')) {
            $query->where(
                'cantidad',
                $request->cantidad
            );
        }

        if ($request->filled('fecha_movimiento')) {
            $query->whereDate(
                'fecha_movimiento',
                $request->fecha_movimiento
            );
        }

        if ($request->filled('motivo')) {
            $query->where(
                'motivo',
                'like',
                '%'.$request->motivo.'%'
            );
        }

        if ($request->filled('observacion')) {
            $query->where(
                'observacion',
                'like',
                '%'.$request->observacion.'%'
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
            );
        }

        $movimientosInventarioCompra = $query
            ->orderBy('fecha_movimiento', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'movimientos_inventario_compra.index',
            compact('movimientosInventarioCompra')
        );
    }

    public function create()
    {
        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        $producciones = Produccion::where(
            'estado',
            true
        )
            ->orderBy('fecha_produccion', 'desc')
            ->get();

        return view(
            'movimientos_inventario_compra.create',
            compact(
                'inventariosCompra',
                'producciones'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validarMovimiento($request);

        DB::transaction(function () use ($validated, $request) {

            $delta = $this->calcularDelta(
                $validated['tipo_movimiento'],
                $validated['cantidad']
            );

            $this->inventarioCompraService->comprobarAjuste(
                (int) $validated['inventario_compra_id'],
                $delta
            );

            MovimientoInventarioCompra::create([
                'inventario_compra_id' => $validated['inventario_compra_id'],

                'detalle_compra_id' => null,

                'produccion_id' => $validated['produccion_id'] ?? null,

                'tipo_movimiento' => $validated['tipo_movimiento'],

                'cantidad' => $validated['cantidad'],

                'fecha_movimiento' => $validated['fecha_movimiento'],

                'motivo' => $validated['motivo'] ?? null,

                'observacion' => $validated['observacion'] ?? null,

                'estado' => $request->has('estado'),
            ]);

            /*
             * Solamente modificamos el stock si el movimiento
             * se registra como activo.
             */
            if ($request->has('estado')) {
                $this->inventarioCompraService->ajustar(
                    (int) $validated['inventario_compra_id'],
                    $delta
                );
            }
        });

        return redirect()
            ->route('movimientos_inventario_compra.index')
            ->with(
                'success',
                'Movimiento registrado correctamente.'
            );
    }

    public function show(
        MovimientoInventarioCompra $movimientoInventarioCompra
    ) {
        $movimientoInventarioCompra->load([
            'inventarioCompra',
            'detalleCompra.compra',
            'produccion.producto',
        ]);

        return view(
            'movimientos_inventario_compra.show',
            compact('movimientoInventarioCompra')
        );
    }

    public function edit(
        MovimientoInventarioCompra $movimientoInventarioCompra
    ) {
        $movimientoInventarioCompra = MovimientoInventarioCompra::findOrFail($movimientoInventarioCompra->id);
        $this->exigirMovimientoManual($movimientoInventarioCompra);

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orWhere(
                'id',
                $movimientoInventarioCompra->inventario_compra_id
            )
            ->orderBy('nombre')
            ->get();

        $producciones = Produccion::where(
            'estado',
            true
        )
            ->orWhere(
                'id',
                $movimientoInventarioCompra->produccion_id
            )
            ->orderBy('fecha_produccion', 'desc')
            ->get();

        return view(
            'movimientos_inventario_compra.edit',
            compact(
                'movimientoInventarioCompra',
                'inventariosCompra',
                'producciones'
            )
        );
    }

    public function update(
        Request $request,
        MovimientoInventarioCompra $movimientoInventarioCompra
    ) {
        DB::transaction(function () use (
            $request,
            $movimientoInventarioCompra
        ) {

            $movimientoInventarioCompra = MovimientoInventarioCompra::whereKey($movimientoInventarioCompra->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->exigirMovimientoManual($movimientoInventarioCompra);
            $validated = $this->validarMovimiento($request);

            // Bloquea ambos inventarios en el mismo orden cuando cambia el destino.
            InventarioCompra::whereIn('id', [
                $movimientoInventarioCompra->inventario_compra_id,
                $validated['inventario_compra_id'],
            ])->orderBy('id')->lockForUpdate()->get();

            /*
             * 1. Revertir el movimiento anterior si estaba activo.
             */
            if ($movimientoInventarioCompra->estado) {

                $deltaAnterior = $this->calcularDelta(
                    $movimientoInventarioCompra->tipo_movimiento,
                    $movimientoInventarioCompra->cantidad
                );

                $this->inventarioCompraService->ajustar(
                    (int) $movimientoInventarioCompra->inventario_compra_id,
                    (string) BigDecimal::of($deltaAnterior)->negated()
                );
            }

            /*
             * 2. Aplicar el nuevo movimiento si estará activo.
             */
            if ($request->has('estado')) {

                $deltaNuevo = $this->calcularDelta(
                    $validated['tipo_movimiento'],
                    $validated['cantidad']
                );

                $this->inventarioCompraService->ajustar(
                    (int) $validated['inventario_compra_id'],
                    $deltaNuevo
                );
            }

            /*
             * 3. Actualizar movimiento.
             */
            $movimientoInventarioCompra->update([
                'inventario_compra_id' => $validated['inventario_compra_id'],

                'detalle_compra_id' => null,

                'produccion_id' => $validated['produccion_id'] ?? null,

                'tipo_movimiento' => $validated['tipo_movimiento'],

                'cantidad' => $validated['cantidad'],

                'fecha_movimiento' => $validated['fecha_movimiento'],

                'motivo' => $validated['motivo'] ?? null,

                'observacion' => $validated['observacion'] ?? null,

                'estado' => $request->has('estado'),
            ]);
        });

        return redirect()
            ->route('movimientos_inventario_compra.index')
            ->with(
                'success',
                'Movimiento actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        MovimientoInventarioCompra $movimientoInventarioCompra
    ) {
        DB::transaction(function () use (
            $movimientoInventarioCompra
        ) {

            $movimientoInventarioCompra = MovimientoInventarioCompra::whereKey($movimientoInventarioCompra->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->exigirMovimientoManual($movimientoInventarioCompra);

            $delta = $this->calcularDelta(
                $movimientoInventarioCompra->tipo_movimiento,
                $movimientoInventarioCompra->cantidad
            );

            /*
             * Si está activo:
             * lo estamos inactivando, por lo tanto revertimos.
             */
            if ($movimientoInventarioCompra->estado) {

                $delta = (string) BigDecimal::of($delta)->negated();
            }

            $this->inventarioCompraService->ajustar(
                (int) $movimientoInventarioCompra->inventario_compra_id,
                $delta
            );

            $movimientoInventarioCompra->update([
                'estado' => ! $movimientoInventarioCompra->estado,
            ]);
        });

        return redirect()
            ->route('movimientos_inventario_compra.index')
            ->with(
                'success',
                'Estado del movimiento actualizado correctamente.'
            );
    }

    private function validarMovimiento(Request $request): array
    {
        $validated = $request->validate([
            'inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'produccion_id' => [
                'nullable',
                'exists:producciones,id',
            ],

            'tipo_movimiento' => [
                'required',
                Rule::in([
                    'ENTRADA',
                    'SALIDA',
                    'AJUSTE',
                ]),
            ],

            'cantidad' => [
                'required',
                'numeric',
                'not_in:0',
            ],

            'fecha_movimiento' => [
                'required',
                'date',
            ],

            'motivo' => [
                'nullable',
                'string',
                'max:150',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated['cantidad'] = $this->inventarioCompraService->normalizarCantidad($validated['cantidad']);
        $cantidad = BigDecimal::of($validated['cantidad']);

        if ($cantidad->isZero()) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser distinta de cero.']);
        }

        /*
         * ENTRADA y SALIDA deben utilizar cantidades positivas.
         * AJUSTE puede ser positivo o negativo.
         */
        if (
            in_array(
                $validated['tipo_movimiento'],
                ['ENTRADA', 'SALIDA']
            ) &&
            ! $cantidad->isPositive()
        ) {
            throw ValidationException::withMessages([
                'cantidad' => 'Las entradas y salidas deben utilizar una cantidad mayor a cero.',
            ]);
        }

        return $validated;
    }

    private function exigirMovimientoManual(MovimientoInventarioCompra $movimiento): void
    {
        if ($movimiento->consumo_produccion_id !== null) {
            throw ValidationException::withMessages([
                'movimiento' => 'Los movimientos generados por una producción son históricos y no pueden modificarse manualmente.',
            ]);
        }
        if ($movimiento->detalle_compra_id !== null) {
            throw ValidationException::withMessages([
                'movimiento' => 'Los movimientos generados por una compra son históricos y no pueden modificarse manualmente.',
            ]);
        }
    }

    private function calcularDelta(
        string $tipoMovimiento,
        $cantidad
    ): string {
        $cantidad = BigDecimal::of($this->inventarioCompraService->normalizarCantidad($cantidad));

        return match ($tipoMovimiento) {
            'ENTRADA' => (string) $cantidad->abs(),
            'SALIDA' => (string) $cantidad->abs()->negated(),
            'AJUSTE' => (string) $cantidad,
        };
    }
}
