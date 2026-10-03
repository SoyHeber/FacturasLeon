<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Services\InventarioService;
use Brick\Math\BigInteger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioController extends Controller
{
    private InventarioService $stock;

    public function __construct(?InventarioService $stock = null)
    {
        $this->stock = $stock ?? new InventarioService;
    }

    public function index(Request $request)
    {
        $query = MovimientoInventario::with('inventario.producto');

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('producto')) {
            $query->whereHas('inventario.producto', function ($q) use ($request) {
                $q->where('nombre', 'like', '%'.$request->producto.'%');
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
            $query->where('motivo', 'like', '%'.$request->motivo.'%');
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $movimientos = $query->orderBy('fecha_movimiento', 'desc')->paginate(10)->withQueryString();

        return view('movimientos_inventario.index', compact('movimientos'));
    }

    public function create()
    {
        $inventarios = Inventario::with('producto')->where('estado', true)->orderBy('id')->get();

        return view('movimientos_inventario.create', compact('inventarios'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglas());
        $cantidad = $this->cantidadMovimiento($validated);
        $estado = $request->boolean('estado');

        DB::transaction(function () use ($validated, $cantidad, $estado) {
            $this->stock->conInventariosBloqueados([(int) $validated['inventario_id']], function () use ($validated, $cantidad, $estado) {
                if ($estado) {
                    $this->stock->ajustar((int) $validated['inventario_id'], $this->obtenerDelta($validated['tipo_movimiento'], $cantidad));
                }

                MovimientoInventario::create([
                    'inventario_id' => $validated['inventario_id'],
                    'produccion_id' => null,
                    'detalle_venta_id' => null,
                    'tipo_movimiento' => $validated['tipo_movimiento'],
                    'cantidad' => $cantidad,
                    'fecha_movimiento' => $validated['fecha_movimiento'],
                    'motivo' => $validated['motivo'] ?? null,
                    'observacion' => $validated['observacion'] ?? null,
                    'estado' => $estado,
                ]);
            });
        }, 3);

        return redirect()->route('movimientos_inventario.index')->with('success', 'Movimiento registrado correctamente.');
    }

    public function show(MovimientoInventario $movimientoInventario)
    {
        $movimientoInventario->load('inventario.producto');

        return view('movimientos_inventario.show', ['movimiento' => $movimientoInventario]);
    }

    public function edit(MovimientoInventario $movimientoInventario)
    {
        $movimientoInventario->refresh();
        $this->exigirManual($movimientoInventario);
        $movimientoInventario->load('inventario.producto');
        $inventarios = Inventario::with('producto')->where('estado', true)->orderBy('id')->get();

        return view('movimientos_inventario.edit', ['movimiento' => $movimientoInventario, 'inventarios' => $inventarios]);
    }

    public function update(Request $request, MovimientoInventario $movimientoInventario)
    {
        DB::transaction(function () use ($request, $movimientoInventario) {
            $movimiento = MovimientoInventario::whereKey($movimientoInventario->id)->lockForUpdate()->firstOrFail();
            $this->exigirManual($movimiento);
            $validated = $request->validate($this->reglas());
            $cantidad = $this->cantidadMovimiento($validated);
            $estado = $request->boolean('estado');
            $nuevoInventarioId = (int) $validated['inventario_id'];

            $this->stock->conInventariosBloqueados([(int) $movimiento->inventario_id, $nuevoInventarioId], function () use ($movimiento, $validated, $cantidad, $estado, $nuevoInventarioId) {
                $deltas = [];
                if ($movimiento->estado) {
                    $deltas[(int) $movimiento->inventario_id] = BigInteger::of($this->obtenerDelta($movimiento->tipo_movimiento, $movimiento->cantidad))->negated();
                }
                if ($estado) {
                    $deltas[$nuevoInventarioId] = ($deltas[$nuevoInventarioId] ?? BigInteger::zero())
                        ->plus($this->obtenerDelta($validated['tipo_movimiento'], $cantidad));
                }

                // Aplica la diferencia final para no exigir una reversión temporal imposible.
                ksort($deltas, SORT_NUMERIC);
                foreach ($deltas as $id => $delta) {
                    if (! $delta->isZero()) {
                        $this->stock->ajustar($id, (string) $delta);
                    }
                }

                $movimiento->update([
                    'inventario_id' => $nuevoInventarioId,
                    'produccion_id' => null,
                    'detalle_venta_id' => null,
                    'tipo_movimiento' => $validated['tipo_movimiento'],
                    'cantidad' => $cantidad,
                    'fecha_movimiento' => $validated['fecha_movimiento'],
                    'motivo' => $validated['motivo'] ?? null,
                    'observacion' => $validated['observacion'] ?? null,
                    'estado' => $estado,
                ]);
            });
        }, 3);

        return redirect()->route('movimientos_inventario.index')->with('success', 'Movimiento actualizado correctamente.');
    }

    public function cambiarEstado(MovimientoInventario $movimientoInventario)
    {
        DB::transaction(function () use ($movimientoInventario) {
            $movimiento = MovimientoInventario::whereKey($movimientoInventario->id)->lockForUpdate()->firstOrFail();
            $this->exigirManual($movimiento);
            $delta = BigInteger::of($this->obtenerDelta($movimiento->tipo_movimiento, $movimiento->cantidad));
            $this->stock->ajustar((int) $movimiento->inventario_id, (string) ($movimiento->estado ? $delta->negated() : $delta));
            $movimiento->update(['estado' => ! $movimiento->estado]);
        }, 3);

        return redirect()->route('movimientos_inventario.index')->with('success', 'Estado del movimiento actualizado correctamente.');
    }

    private function reglas(): array
    {
        return [
            'inventario_id' => ['required', 'exists:inventarios,id'],
            'tipo_movimiento' => ['required', Rule::in(['ENTRADA', 'SALIDA', 'AJUSTE'])],
            'cantidad' => ['required', function ($atributo, $valor, $fallar) {
                try {
                    $this->stock->normalizarCantidadMovimiento($valor);
                } catch (ValidationException $exception) {
                    $fallar($exception->errors()['cantidad'][0]);
                }
            }],
            'fecha_movimiento' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    private function cantidadMovimiento(array $datos): string
    {
        $cantidad = BigInteger::of($this->stock->normalizarCantidadMovimiento($datos['cantidad']));
        if ($cantidad->isZero() || ($datos['tipo_movimiento'] !== 'AJUSTE' && ! $cantidad->isPositive())) {
            throw ValidationException::withMessages(['cantidad' => 'Use una cantidad distinta de cero; las entradas y salidas deben ser positivas.']);
        }

        return (string) $cantidad;
    }

    private function exigirManual(MovimientoInventario $movimiento): void
    {
        if ($movimiento->esAutomatico()) {
            throw ValidationException::withMessages(['movimiento' => 'Los movimientos automáticos de Producción o Venta son de solo lectura.']);
        }
    }

    private function obtenerDelta(string $tipoMovimiento, string $cantidad): string
    {
        $cantidad = BigInteger::of($cantidad);

        return (string) match ($tipoMovimiento) {
            'ENTRADA' => $cantidad->abs(),
            'SALIDA' => $cantidad->abs()->negated(),
            'AJUSTE' => $cantidad,
        };
    }
}
