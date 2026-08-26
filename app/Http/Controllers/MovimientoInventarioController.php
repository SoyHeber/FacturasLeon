<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioController extends Controller
{
    public function index(Request $request)
    {
        $query = MovimientoInventario::with('inventario.producto');

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('producto')) {
            $query->whereHas('inventario.producto', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->producto . '%');
            });
        }

        if ($request->filled('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->filled('cantidad')) {
            $query->where('cantidad', $request->cantidad);
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha_movimiento', $request->fecha);
        }

        if ($request->filled('motivo')) {
            $query->where('motivo', 'like', '%' . $request->motivo . '%');
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $movimientos = $query
            ->orderBy('fecha_movimiento', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'movimientos_inventario.index',
            compact('movimientos')
        );
    }

    public function create()
    {
        $inventarios = Inventario::with('producto')
            ->where('estado', true)
            ->orderBy('id')
            ->get();

        return view(
            'movimientos_inventario.create',
            compact('inventarios')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'inventario_id' => [
                'required',
                'exists:inventarios,id',
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
                'integer',
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

        if (
            in_array(
                $validated['tipo_movimiento'],
                ['ENTRADA', 'SALIDA']
            )
            && $validated['cantidad'] < 1
        ) {
            throw ValidationException::withMessages([
                'cantidad' =>
                'Las entradas y salidas deben utilizar una cantidad mayor a cero.',
            ]);
        }

        DB::transaction(function () use ($validated, $request) {

            $inventario = Inventario::where(
                'id',
                $validated['inventario_id']
            )
                ->lockForUpdate()
                ->firstOrFail();

            $delta = $this->obtenerDelta(
                $validated['tipo_movimiento'],
                $validated['cantidad']
            );

            $nuevaCantidad = $inventario->cantidad + $delta;

            if ($nuevaCantidad < 0) {
                throw ValidationException::withMessages([
                    'cantidad' =>
                    'El movimiento dejaría el inventario con una cantidad negativa.',
                ]);
            }

            MovimientoInventario::create([
                'inventario_id' => $validated['inventario_id'],
                'tipo_movimiento' => $validated['tipo_movimiento'],
                'cantidad' => $validated['cantidad'],
                'fecha_movimiento' => $validated['fecha_movimiento'],
                'motivo' => $validated['motivo'] ?? null,
                'observacion' => $validated['observacion'] ?? null,
                'estado' => $request->has('estado'),
            ]);

            $inventario->update([
                'cantidad' => $nuevaCantidad,
            ]);
        });

        return redirect()
            ->route('movimientos_inventario.index')
            ->with(
                'success',
                'Movimiento registrado correctamente.'
            );
    }

    public function show(MovimientoInventario $movimientoInventario)
    {
        $movimientoInventario->load(
            'inventario.producto'
        );

        return view(
            'movimientos_inventario.show',
            [
                'movimiento' => $movimientoInventario,
            ]
        );
    }

    public function edit(MovimientoInventario $movimientoInventario)
    {
        $movimientoInventario->load(
            'inventario.producto'
        );

        $inventarios = Inventario::with('producto')
            ->where('estado', true)
            ->orderBy('id')
            ->get();

        return view(
            'movimientos_inventario.edit',
            [
                'movimiento' => $movimientoInventario,
                'inventarios' => $inventarios,
            ]
        );
    }

    public function update(
        Request $request,
        MovimientoInventario $movimientoInventario
    ) {
        $validated = $request->validate([
            'inventario_id' => [
                'required',
                'exists:inventarios,id',
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
                'integer',
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

        if (
            in_array(
                $validated['tipo_movimiento'],
                ['ENTRADA', 'SALIDA']
            )
            && $validated['cantidad'] < 1
        ) {
            throw ValidationException::withMessages([
                'cantidad' =>
                'Las entradas y salidas deben utilizar una cantidad mayor a cero.',
            ]);
        }

        DB::transaction(function () use (
            $validated,
            $request,
            $movimientoInventario
        ) {
            /*
             * Primero revertimos el efecto del movimiento anterior.
             */
            if ($movimientoInventario->estado) {

                $inventarioAnterior = Inventario::where(
                    'id',
                    $movimientoInventario->inventario_id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $deltaAnterior = $this->obtenerDelta(
                    $movimientoInventario->tipo_movimiento,
                    $movimientoInventario->cantidad
                );

                $cantidadRevertida =
                    $inventarioAnterior->cantidad - $deltaAnterior;

                if ($cantidadRevertida < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' =>
                        'No es posible revertir el movimiento anterior.',
                    ]);
                }

                $inventarioAnterior->update([
                    'cantidad' => $cantidadRevertida,
                ]);
            }

            /*
             * Ahora aplicamos el movimiento actualizado.
             */
            $nuevoEstado = $request->has('estado');

            if ($nuevoEstado) {

                $nuevoInventario = Inventario::where(
                    'id',
                    $validated['inventario_id']
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $nuevoDelta = $this->obtenerDelta(
                    $validated['tipo_movimiento'],
                    $validated['cantidad']
                );

                $nuevaCantidad =
                    $nuevoInventario->cantidad + $nuevoDelta;

                if ($nuevaCantidad < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' =>
                        'El movimiento dejaría el inventario con una cantidad negativa.',
                    ]);
                }

                $nuevoInventario->update([
                    'cantidad' => $nuevaCantidad,
                ]);
            }

            $movimientoInventario->update([
                'inventario_id' => $validated['inventario_id'],
                'tipo_movimiento' => $validated['tipo_movimiento'],
                'cantidad' => $validated['cantidad'],
                'fecha_movimiento' => $validated['fecha_movimiento'],
                'motivo' => $validated['motivo'] ?? null,
                'observacion' => $validated['observacion'] ?? null,
                'estado' => $nuevoEstado,
            ]);
        });

        return redirect()
            ->route('movimientos_inventario.index')
            ->with(
                'success',
                'Movimiento actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        MovimientoInventario $movimientoInventario
    ) {
        DB::transaction(function () use ($movimientoInventario) {

            $inventario = Inventario::where(
                'id',
                $movimientoInventario->inventario_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $delta = $this->obtenerDelta(
                $movimientoInventario->tipo_movimiento,
                $movimientoInventario->cantidad
            );

            if ($movimientoInventario->estado) {

                // Inactivar = revertir el movimiento.
                $nuevaCantidad =
                    $inventario->cantidad - $delta;
            } else {

                // Activar nuevamente = reaplicar.
                $nuevaCantidad =
                    $inventario->cantidad + $delta;
            }

            if ($nuevaCantidad < 0) {
                throw ValidationException::withMessages([
                    'cantidad' =>
                    'No es posible cambiar el estado porque el inventario quedaría negativo.',
                ]);
            }

            $inventario->update([
                'cantidad' => $nuevaCantidad,
            ]);

            $movimientoInventario->update([
                'estado' => !$movimientoInventario->estado,
            ]);
        });

        return redirect()
            ->route('movimientos_inventario.index')
            ->with(
                'success',
                'Estado del movimiento actualizado correctamente.'
            );
    }

    private function obtenerDelta(
        string $tipoMovimiento,
        int $cantidad
    ): int {
        return match ($tipoMovimiento) {

            'ENTRADA' => abs($cantidad),

            'SALIDA' => -abs($cantidad),

            'AJUSTE' => $cantidad,

            default => 0,
        };
    }
}
