<?php

namespace App\Http\Controllers;

use App\Models\InventarioCompra;
use App\Models\MaterialProducto;
use App\Models\Producto;
use App\Services\CalculadoraProduccionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = MaterialProducto::with([
            'producto',
            'inventarioCompra',
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

        if ($request->filled('material')) {
            $query->whereHas('inventarioCompra', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%'.$request->material.'%'
                );
            });
        }

        if ($request->filled('cantidad_requerida')) {
            $query->where(
                'cantidad_requerida',
                $request->cantidad_requerida
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

        $materialesProducto = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'materiales_producto.index',
            compact('materialesProducto')
        );
    }

    public function create()
    {
        $productos = Producto::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'materiales_producto.create',
            compact(
                'productos',
                'inventariosCompra'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validarMaterial($request);
        DB::transaction(function () use ($validated) {
            $this->bloquearProductos([(int) $validated['producto_id']]);
            $this->exigirMaterialUnico($validated);
            MaterialProducto::create($validated + ['estado' => true]);
        });

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Material del producto registrado correctamente.'
            );
    }

    public function show(MaterialProducto $materialProducto)
    {
        $materialProducto->load([
            'producto',
            'inventarioCompra',
        ]);

        return view(
            'materiales_producto.show',
            compact('materialProducto')
        );
    }

    public function edit(MaterialProducto $materialProducto)
    {
        $productos = Producto::where('estado', true)
            ->orWhere('id', $materialProducto->producto_id)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where('estado', true)
            ->orWhere(
                'id',
                $materialProducto->inventario_compra_id
            )
            ->orderBy('nombre')
            ->get();

        return view(
            'materiales_producto.edit',
            compact(
                'materialProducto',
                'productos',
                'inventariosCompra'
            )
        );
    }

    public function update(
        Request $request,
        MaterialProducto $materialProducto
    ) {
        $validated = $this->validarMaterial($request);
        DB::transaction(function () use ($validated, $materialProducto) {
            $materialProducto = $this->bloquearReceta($materialProducto, (int) $validated['producto_id']);
            $this->exigirMaterialUnico($validated, $materialProducto->id);
            $materialProducto->update(array_replace(['observacion' => null, 'estado' => false], $validated));
        });

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Material del producto actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        MaterialProducto $materialProducto
    ) {
        DB::transaction(function () use ($materialProducto) {
            $materialProducto = $this->bloquearReceta($materialProducto);
            if (! $materialProducto->estado) {
                (new CalculadoraProduccionService)->normalizarCantidadRequerida($materialProducto->getRawOriginal('cantidad_requerida'));
            }
            $materialProducto->update(['estado' => ! $materialProducto->estado]);
        });

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Estado actualizado correctamente.'
            );
    }

    private function validarMaterial(Request $request): array
    {
        $validated = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'inventario_compra_id' => ['required', 'integer', 'exists:inventarios_compra,id'],
            'cantidad_requerida' => ['required'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'boolean'],
        ]);
        $validated['cantidad_requerida'] = (new CalculadoraProduccionService)->normalizarCantidadRequerida($validated['cantidad_requerida']);
        if (array_key_exists('estado', $validated)) {
            $validated['estado'] = (bool) $validated['estado'];
        }

        return $validated;
    }

    private function bloquearProductos(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            Producto::whereKey($id)->lockForUpdate()->firstOrFail();
        }
    }

    private function bloquearReceta(MaterialProducto $material, ?int $productoDestino = null): MaterialProducto
    {
        $actual = MaterialProducto::findOrFail($material->id);
        $ids = [(int) $actual->producto_id];
        if ($productoDestino !== null) {
            $ids[] = $productoDestino;
        }
        $this->bloquearProductos($ids);
        $actual = MaterialProducto::whereKey($material->id)->lockForUpdate()->firstOrFail();

        if (! in_array((int) $actual->producto_id, $ids, true)) {
            throw ValidationException::withMessages([
                'producto_id' => 'La receta cambió de producto mientras se editaba. Actualiza la página e intenta nuevamente.',
            ]);
        }

        return $actual;
    }

    private function exigirMaterialUnico(array $datos, ?int $ignorarId = null): void
    {
        $query = MaterialProducto::where('producto_id', $datos['producto_id'])
            ->where('inventario_compra_id', $datos['inventario_compra_id']);
        if ($ignorarId !== null) {
            $query->whereKeyNot($ignorarId);
        }
        if ($query->lockForUpdate()->first()) {
            throw ValidationException::withMessages([
                'inventario_compra_id' => 'Este material ya está asignado al producto seleccionado.',
            ]);
        }
    }
}
