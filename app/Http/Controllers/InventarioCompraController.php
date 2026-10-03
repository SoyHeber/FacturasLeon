<?php

namespace App\Http\Controllers;

use App\Models\InventarioCompra;
use App\Services\InventarioCompraService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioCompraController extends Controller
{
    public function __construct(private InventarioCompraService $inventarioCompraService)
    {
    }

    public function index(Request $request)
    {
        $query = InventarioCompra::query();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('nombre')) {
            $query->where(
                'nombre',
                'like',
                '%' . $request->nombre . '%'
            );
        }

        if ($request->filled('descripcion')) {
            $query->where(
                'descripcion',
                'like',
                '%' . $request->descripcion . '%'
            );
        }

        if ($request->filled('unidad_medida')) {
            $query->where(
                'unidad_medida',
                'like',
                '%' . $request->unidad_medida . '%'
            );
        }

        if ($request->filled('cantidad')) {
            $query->where(
                'cantidad',
                $request->cantidad
            );
        }

        if ($request->filled('stock_minimo')) {
            $query->where(
                'stock_minimo',
                $request->stock_minimo
            );
        }

        if ($request->filled('stock_maximo')) {
            $query->where(
                'stock_maximo',
                $request->stock_maximo
            );
        }

        if ($request->filled('ubicacion')) {
            $query->where(
                'ubicacion',
                'like',
                '%' . $request->ubicacion . '%'
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
            );
        }

        $inventariosCompra = $query
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view(
            'inventarios_compra.index',
            compact('inventariosCompra')
        );
    }

    public function create()
    {
        return view('inventarios_compra.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'unidad_medida' => [
                'required',
                'string',
                'max:30',
            ],

            'stock_minimo' => [
                'required',
                'numeric',
                'min:0',
            ],

            'stock_maximo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'ubicacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated = $this->normalizarCantidades($validated);

        if (
            isset($validated['stock_maximo']) &&
            $validated['stock_maximo'] !== null &&
            BigDecimal::of($validated['stock_maximo'])->isLessThan($validated['stock_minimo'])
        ) {
            return back()
                ->withErrors([
                    'stock_maximo' =>
                    'El stock máximo no puede ser menor al stock mínimo.',
                ])
                ->withInput();
        }

        $datos = [
            'nombre' =>
            $validated['nombre'],

            'descripcion' =>
            $validated['descripcion'] ?? null,

            'unidad_medida' =>
            strtoupper($validated['unidad_medida']),

            'stock_minimo' =>
            $validated['stock_minimo'],

            'stock_maximo' =>
            $validated['stock_maximo'] ?? null,

            'ubicacion' =>
            $validated['ubicacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ];

        InventarioCompra::create($datos);

        return redirect()
            ->route('inventarios_compra.index')
            ->with(
                'success',
                'Inventario de compra registrado correctamente.'
            );
    }

    public function show(InventarioCompra $inventarioCompra)
    {
        return view(
            'inventarios_compra.show',
            compact('inventarioCompra')
        );
    }

    public function edit(InventarioCompra $inventarioCompra)
    {
        return view(
            'inventarios_compra.edit',
            compact('inventarioCompra')
        );
    }

    public function update(
        Request $request,
        InventarioCompra $inventarioCompra
    ) {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'unidad_medida' => [
                'required',
                'string',
                'max:30',
            ],

            'stock_minimo' => [
                'required',
                'numeric',
                'min:0',
            ],

            'stock_maximo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'ubicacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated = $this->normalizarCantidades($validated);

        if (
            isset($validated['stock_maximo']) &&
            $validated['stock_maximo'] !== null &&
            BigDecimal::of($validated['stock_maximo'])->isLessThan($validated['stock_minimo'])
        ) {
            return back()
                ->withErrors([
                    'stock_maximo' =>
                    'El stock máximo no puede ser menor al stock mínimo.',
                ])
                ->withInput();
        }

        $datos = [
            'nombre' =>
            $validated['nombre'],

            'descripcion' =>
            $validated['descripcion'] ?? null,

            'unidad_medida' =>
            strtoupper($validated['unidad_medida']),

            'stock_minimo' =>
            $validated['stock_minimo'],

            'stock_maximo' =>
            $validated['stock_maximo'] ?? null,

            'ubicacion' =>
            $validated['ubicacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ];

        DB::transaction(function () use ($datos, $inventarioCompra) {
            $inventario = InventarioCompra::whereKey($inventarioCompra->id)->lockForUpdate()->firstOrFail();
            $inventario->update($datos);
        });

        return redirect()
            ->route('inventarios_compra.index')
            ->with(
                'success',
                'Inventario de compra actualizado correctamente.'
            );
    }

    private function normalizarCantidades(array $validated): array
    {
        foreach (['stock_minimo', 'stock_maximo'] as $campo) {
            if (!isset($validated[$campo])) {
                continue;
            }

            $validated[$campo] = $this->inventarioCompraService->normalizarCantidad($validated[$campo], $campo);

            if (BigDecimal::of($validated[$campo])->isNegative()) {
                throw ValidationException::withMessages([$campo => 'La cantidad no puede ser negativa.']);
            }
        }

        return $validated;
    }

    public function cambiarEstado(
        InventarioCompra $inventarioCompra
    ) {
        $inventarioCompra->update([
            'estado' => !$inventarioCompra->estado,
        ]);

        return redirect()
            ->route('inventarios_compra.index')
            ->with(
                'success',
                'Estado actualizado correctamente.'
            );
    }
}
