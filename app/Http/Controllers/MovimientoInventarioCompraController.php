<?php

namespace App\Http\Controllers;

use App\Models\DetalleCompra;
use App\Models\InventarioCompra;
use App\Models\MovimientoInventarioCompra;
use App\Models\Produccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = MovimientoInventarioCompra::with([
            'inventarioCompra',
            'detalleCompra',
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
                        '%' . $request->inventario_compra . '%'
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
                '%' . $request->motivo . '%'
            );
        }

        if ($request->filled('observacion')) {
            $query->where(
                'observacion',
                'like',
                '%' . $request->observacion . '%'
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

        $detallesCompra = DetalleCompra::where(
            'estado',
            true
        )
            ->orderBy('id', 'desc')
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
                'detallesCompra',
                'producciones'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validarMovimiento($request);

        DB::transaction(function () use ($validated, $request) {

            $inventario = InventarioCompra::where(
                'id',
                $validated['inventario_compra_id']
            )
                ->lockForUpdate()
                ->firstOrFail();

            $delta = $this->calcularDelta(
                $validated['tipo_movimiento'],
                $validated['cantidad']
            );

            $nuevoStock = (float) $inventario->cantidad + $delta;

            if ($nuevoStock < 0) {
                throw ValidationException::withMessages([
                    'cantidad' =>
                    'El movimiento dejaría el inventario con una cantidad negativa.',
                ]);
            }

            MovimientoInventarioCompra::create([
                'inventario_compra_id' =>
                $validated['inventario_compra_id'],

                'detalle_compra_id' =>
                $validated['detalle_compra_id'] ?? null,

                'produccion_id' =>
                $validated['produccion_id'] ?? null,

                'tipo_movimiento' =>
                $validated['tipo_movimiento'],

                'cantidad' =>
                $validated['cantidad'],

                'fecha_movimiento' =>
                $validated['fecha_movimiento'],

                'motivo' =>
                $validated['motivo'] ?? null,

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has('estado'),
            ]);

            /*
             * Solamente modificamos el stock si el movimiento
             * se registra como activo.
             */
            if ($request->has('estado')) {
                $inventario->update([
                    'cantidad' => $nuevoStock,
                ]);
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
            'detalleCompra',
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

        $detallesCompra = DetalleCompra::where(
            'estado',
            true
        )
            ->orWhere(
                'id',
                $movimientoInventarioCompra->detalle_compra_id
            )
            ->orderBy('id', 'desc')
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
                'detallesCompra',
                'producciones'
            )
        );
    }

    public function update(
        Request $request,
        MovimientoInventarioCompra $movimientoInventarioCompra
    ) {
        $validated = $this->validarMovimiento($request);

        DB::transaction(function () use (
            $validated,
            $request,
            $movimientoInventarioCompra
        ) {

            /*
             * 1. Revertir el movimiento anterior si estaba activo.
             */
            if ($movimientoInventarioCompra->estado) {

                $inventarioAnterior = InventarioCompra::where(
                    'id',
                    $movimientoInventarioCompra->inventario_compra_id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $deltaAnterior = $this->calcularDelta(
                    $movimientoInventarioCompra->tipo_movimiento,
                    $movimientoInventarioCompra->cantidad
                );

                $stockRevertido =
                    (float) $inventarioAnterior->cantidad
                    - $deltaAnterior;

                if ($stockRevertido < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' =>
                        'No es posible modificar este movimiento porque su reversión dejaría el inventario negativo.',
                    ]);
                }

                $inventarioAnterior->update([
                    'cantidad' => $stockRevertido,
                ]);
            }

            /*
             * 2. Aplicar el nuevo movimiento si estará activo.
             */
            if ($request->has('estado')) {

                $inventarioNuevo = InventarioCompra::where(
                    'id',
                    $validated['inventario_compra_id']
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $deltaNuevo = $this->calcularDelta(
                    $validated['tipo_movimiento'],
                    $validated['cantidad']
                );

                $nuevoStock =
                    (float) $inventarioNuevo->cantidad
                    + $deltaNuevo;

                if ($nuevoStock < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' =>
                        'El movimiento dejaría el inventario con una cantidad negativa.',
                    ]);
                }

                $inventarioNuevo->update([
                    'cantidad' => $nuevoStock,
                ]);
            }

            /*
             * 3. Actualizar movimiento.
             */
            $movimientoInventarioCompra->update([
                'inventario_compra_id' =>
                $validated['inventario_compra_id'],

                'detalle_compra_id' =>
                $validated['detalle_compra_id'] ?? null,

                'produccion_id' =>
                $validated['produccion_id'] ?? null,

                'tipo_movimiento' =>
                $validated['tipo_movimiento'],

                'cantidad' =>
                $validated['cantidad'],

                'fecha_movimiento' =>
                $validated['fecha_movimiento'],

                'motivo' =>
                $validated['motivo'] ?? null,

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has('estado'),
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

            $inventario = InventarioCompra::where(
                'id',
                $movimientoInventarioCompra->inventario_compra_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $delta = $this->calcularDelta(
                $movimientoInventarioCompra->tipo_movimiento,
                $movimientoInventarioCompra->cantidad
            );

            /*
             * Si está activo:
             * lo estamos inactivando, por lo tanto revertimos.
             */
            if ($movimientoInventarioCompra->estado) {

                $nuevoStock =
                    (float) $inventario->cantidad - $delta;
            } else {

                /*
                 * Si está inactivo:
                 * lo estamos activando nuevamente.
                 */
                $nuevoStock =
                    (float) $inventario->cantidad + $delta;
            }

            if ($nuevoStock < 0) {
                throw ValidationException::withMessages([
                    'cantidad' =>
                    'No es posible cambiar el estado porque el inventario quedaría negativo.',
                ]);
            }

            $inventario->update([
                'cantidad' => $nuevoStock,
            ]);

            $movimientoInventarioCompra->update([
                'estado' =>
                !$movimientoInventarioCompra->estado,
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

            'detalle_compra_id' => [
                'nullable',
                'exists:detalles_compra,id',
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

        /*
         * ENTRADA y SALIDA deben utilizar cantidades positivas.
         * AJUSTE puede ser positivo o negativo.
         */
        if (
            in_array(
                $validated['tipo_movimiento'],
                ['ENTRADA', 'SALIDA']
            ) &&
            $validated['cantidad'] <= 0
        ) {
            throw ValidationException::withMessages([
                'cantidad' =>
                'Las entradas y salidas deben utilizar una cantidad mayor a cero.',
            ]);
        }

        /*
         * Evitamos tener dos orígenes distintos
         * simultáneamente.
         */
        if (
            !empty($validated['detalle_compra_id']) &&
            !empty($validated['produccion_id'])
        ) {
            throw ValidationException::withMessages([
                'detalle_compra_id' =>
                'El movimiento no puede pertenecer a una compra y a una producción al mismo tiempo.',
            ]);
        }

        return $validated;
    }

    private function calcularDelta(
        string $tipoMovimiento,
        $cantidad
    ): float {
        $cantidad = (float) $cantidad;

        return match ($tipoMovimiento) {
            'ENTRADA' => abs($cantidad),
            'SALIDA' => -abs($cantidad),
            'AJUSTE' => $cantidad,
            default => 0,
        };
    }
}
