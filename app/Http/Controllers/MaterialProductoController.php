<?php

namespace App\Http\Controllers;

use App\Models\InventarioCompra;
use App\Models\MaterialProducto;
use App\Models\Producto;
use App\Services\CalculadoraProduccionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MaterialProductoController extends Controller
{
    /**
     * Lista una receta por producto y pagina productos, sin dividir sus materias primas.
     * Los filtros de insumo se comprueban sobre una misma línea vigente y el estado se
     * consulta en la receta completa. El resultado conserva sus componentes e historial.
     * El filtro ID corresponde a materiales_producto.id; localizar una línea no oculta
     * las demás materias primas de su receta.
     */
    public function index(Request $request)
    {
        $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'producto' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:100'],
            'cantidad_requerida' => ['nullable', 'string', 'max:80'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'boolean'],
        ]);
        $cantidad = $request->filled('cantidad_requerida')
            ? (new CalculadoraProduccionService)->normalizarCantidadRequerida($request->cantidad_requerida) : null;
        $query = Producto::with('materialesProducto.inventarioCompra');
        if ($request->filled('producto')) {
            $query->where('nombre', 'like', '%'.$request->producto.'%');
        }
        if ($request->filled('estado')) {
            $query->where('receta_activa', $request->boolean('estado'));
        }
        // Los filtros de material coinciden en una misma línea, pero se muestra la receta completa.
        $query->whereHas('materialesProducto', function ($lineas) use ($request, $cantidad) {
            if ($request->filled('id')) {
                $lineas->whereKey($request->id);
            }
            if ($request->filled('material') || $cantidad !== null || $request->filled('observacion')) {
                $lineas->where('estado', true);
            }
            if ($request->filled('material')) {
                $lineas->whereHas('inventarioCompra', fn ($inventario) => $inventario->where('nombre', 'like', '%'.$request->material.'%'));
            }
            if ($cantidad !== null) {
                $lineas->where('cantidad_requerida', $cantidad);
            }
            if ($request->filled('observacion')) {
                $lineas->where('observacion', 'like', '%'.$request->observacion.'%');
            }
        });
        $recetas = $query->latest('id')->paginate(10)->withQueryString();

        return view('materiales_producto.index', compact('recetas'));
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

    /**
     * Agrega varias materias primas a la receta de un único producto.
     * Recibe materiales con cantidades por unidad del producto y conserva hasta 10 decimales
     * mediante la calculadora de producción. Valida el lote antes de escribir, bloquea el
     * producto como las operaciones de producción y comprueba nuevamente los catálogos activos.
     * Todas las líneas se guardan juntas; un duplicado o fallo revierte el lote completo.
     * Agregar una receta no consume stock ni modifica materiales o consumos históricos.
     */
    public function store(Request $request)
    {
        $validated = $this->validarMateriales($request);
        DB::transaction(function () use ($validated) {
            $producto = Producto::whereKey($validated['producto_id'])->lockForUpdate()->firstOrFail();
            if (! $producto->estado) {
                throw ValidationException::withMessages(['producto_id' => 'El producto seleccionado ya no está activo.']);
            }
            $inventarios = InventarioCompra::whereIn('id', array_column($validated['materiales'], 'inventario_compra_id'))
                ->orderBy('id')->sharedLock()->get()->keyBy('id');
            foreach ($validated['materiales'] as $indice => $material) {
                $campo = 'materiales.'.$indice.'.inventario_compra_id';
                $inventario = $inventarios->get($material['inventario_compra_id']);
                if (! $inventario || ! $inventario->estado) {
                    throw ValidationException::withMessages([$campo => 'La materia prima seleccionada ya no está activa o disponible.']);
                }
                $this->exigirMaterialUnico(['producto_id' => $validated['producto_id'], 'inventario_compra_id' => $material['inventario_compra_id']], $campo);
            }
            foreach ($validated['materiales'] as $material) {
                $nuevo = MaterialProducto::create([
                    'producto_id' => $validated['producto_id'], 'inventario_compra_id' => $material['inventario_compra_id'],
                    'cantidad_requerida' => $material['cantidad_requerida'], 'observacion' => $material['observacion'] ?? null,
                    'estado' => true,
                ]);
                if (! $nuevo->exists) {
                    throw new RuntimeException('No se pudieron guardar todos los materiales del producto.');
                }
            }
        }, 3);

        return redirect()
            ->route('materiales_producto.index')
            ->with(
                'success',
                'Materiales del producto registrados correctamente.'
            );
    }

    /** El ID de cualquier línea permite consultar toda su receta, manteniendo los enlaces existentes. */
    public function show(MaterialProducto $materialProducto)
    {
        $materialProducto->refresh();
        $producto = Producto::with('materialesProducto.inventarioCompra')->findOrFail($materialProducto->producto_id);

        return view('materiales_producto.show', compact('materialProducto', 'producto'));
    }

    /** Carga los componentes vigentes y una huella del estado general y todas las líneas para detectar ediciones obsoletas. */
    public function edit(MaterialProducto $materialProducto)
    {
        $materialProducto->refresh();
        $producto = Producto::with('materialesProducto.inventarioCompra')->findOrFail($materialProducto->producto_id);
        $materialesActuales = $producto->materialesProducto->where('estado', true)->values();
        $huellaReceta = $this->huellaReceta($producto, $producto->materialesProducto);
        $inventariosCompra = InventarioCompra::where('estado', true)
            ->orWhereIn('id', $materialesActuales->pluck('inventario_compra_id'))->orderBy('nombre')->get();

        return view('materiales_producto.edit', compact('materialProducto', 'producto', 'materialesActuales', 'huellaReceta', 'inventariosCompra'));
    }

    /**
     * Guarda la receta completa conservando las identidades de sus líneas y consumos anteriores.
     * El producto y la materia prima de una línea existente no se reasignan: para sustituir
     * un insumo se quita del formulario y se agrega otro. Los componentes omitidos se retiran
     * lógicamente; al volver a incluir un insumo retirado se recupera su mismo ID.
     * Modificar permite editar toda la composición, pero no cambia el estado general.
     * La huella detecta cambios de composición o disponibilidad desde la carga del formulario.
     * El bloqueo del producto excluye la confirmación de producción durante el guardado.
     * El lote se confirma o revierte íntegramente, sin modificar stock ni consumos anteriores.
     */
    public function update(
        Request $request,
        MaterialProducto $materialProducto
    ) {
        Gate::authorize('materiales_producto.modificar');
        $limite = max(200, MaterialProducto::where('producto_id', $materialProducto->producto_id)->count());
        $datos = $this->validarMateriales($request, true, $limite);
        DB::transaction(function () use ($datos, $materialProducto) {
            $ancla = $this->bloquearReceta($materialProducto);
            if ((int) $datos['producto_id'] !== (int) $ancla->producto_id) {
                throw ValidationException::withMessages(['producto_id' => 'La receta pertenece a otro producto. Actualice la página antes de editar.']);
            }
            $producto = $ancla->producto;
            $actuales = MaterialProducto::where('producto_id', $ancla->producto_id)->orderBy('id')->lockForUpdate()->get();
            if (! hash_equals($this->huellaReceta($producto, $actuales), $datos['huella_receta'])) {
                throw ValidationException::withMessages(['receta' => 'La receta cambió desde que abrió el formulario. Actualice la página para conservar los cambios más recientes.']);
            }
            $porId = $actuales->keyBy('id');
            $porInventario = $actuales->keyBy('inventario_compra_id');
            $inventarios = InventarioCompra::whereIn('id', array_column($datos['materiales'], 'inventario_compra_id'))
                ->orderBy('id')->sharedLock()->get()->keyBy('id');
            foreach ($datos['materiales'] as $indice => $linea) {
                $existente = isset($linea['id']) ? $porId->get($linea['id']) : null;
                if (isset($linea['id']) && ! $existente) {
                    throw ValidationException::withMessages(['materiales.'.$indice.'.id' => 'La línea no pertenece a esta receta.']);
                }
                if ($existente && (int) $existente->inventario_compra_id !== (int) $linea['inventario_compra_id']) {
                    throw ValidationException::withMessages(['materiales.'.$indice.'.inventario_compra_id' => 'La materia prima de una línea existente se conserva. Quite la fila y agregue el nuevo material.']);
                }
                if (! $existente) {
                    $existente = $porInventario->get($linea['inventario_compra_id']);
                }
                $inventario = $inventarios->get($linea['inventario_compra_id']);
                if ((! $existente || ! $existente->estado)
                    && (! $inventario || ! $inventario->estado || ! $producto->estado)) {
                    throw ValidationException::withMessages(['materiales.'.$indice.'.inventario_compra_id' => 'Para incorporar una materia prima, el producto y el insumo deben estar activos.']);
                }
            }
            $incluidos = [];
            foreach ($datos['materiales'] as $linea) {
                $material = $porInventario->get($linea['inventario_compra_id']) ?? new MaterialProducto([
                    'producto_id' => $ancla->producto_id, 'inventario_compra_id' => $linea['inventario_compra_id'],
                ]);
                $material->fill(['cantidad_requerida' => $linea['cantidad_requerida'],
                    'observacion' => $linea['observacion'] ?? null, 'estado' => true]);
                if (! $material->save()) {
                    throw new RuntimeException('No se pudo guardar la receta completa.');
                }
                $incluidos[] = $material->id;
            }
            // Se conservan las filas retiradas porque los consumos históricos las referencian.
            foreach ($actuales as $material) {
                if ($material->estado && ! in_array($material->id, $incluidos, true) && ! $material->update(['estado' => false])) {
                    throw new RuntimeException('No se pudo retirar un componente de la receta.');
                }
            }
        }, 3);

        return redirect()->route('materiales_producto.show', $materialProducto->id)->with('success', 'Receta del producto actualizada correctamente.');
    }

    /**
     * Cambia la disponibilidad de toda la receta con el permiso de cambio de estado.
     * Bloquea el producto antes de sus líneas, igual que la edición y la producción, para
     * impedir que una confirmación utilice la receta mientras cambia su disponibilidad.
     * Para activar exige producto activo, componentes vigentes, cantidades válidas e insumos
     * activos. No cambia la composición, el estado del producto, el stock ni los consumos.
     */
    public function cambiarEstado(
        MaterialProducto $materialProducto
    ) {
        Gate::authorize('materiales_producto.eliminar');
        $activa = DB::transaction(function () use ($materialProducto) {
            $ancla = $this->bloquearReceta($materialProducto);
            $producto = $ancla->producto;
            $lineas = MaterialProducto::where('producto_id', $producto->id)->orderBy('id')->lockForUpdate()->get()->where('estado', true);
            if (! $producto->receta_activa) {
                if (! $producto->estado || $lineas->isEmpty()) {
                    throw ValidationException::withMessages(['receta' => 'Para activar la receta, el producto debe estar activo y tener al menos un componente vigente.']);
                }
                $inventarios = InventarioCompra::whereIn('id', $lineas->pluck('inventario_compra_id'))->orderBy('id')->sharedLock()->get()->keyBy('id');
                foreach ($lineas as $linea) {
                    (new CalculadoraProduccionService)->normalizarCantidadRequerida($linea->getRawOriginal('cantidad_requerida'));
                    if (! $inventarios->get($linea->inventario_compra_id)?->estado) {
                        throw ValidationException::withMessages(['receta' => 'Todas las materias primas de la receta deben estar activas para habilitarla.']);
                    }
                }
            }
            $producto->forceFill(['receta_activa' => ! $producto->receta_activa]);
            if (! $producto->save()) {
                throw new RuntimeException('No se pudo cambiar el estado de la receta.');
            }

            return $producto->receta_activa;
        }, 3);

        return redirect()->route('materiales_producto.index')->with('success', $activa ? 'Receta activada correctamente.' : 'Receta inactivada correctamente.');
    }

    /**
     * Valida el lote y conserva los errores asociados a su fila. En altas admite hasta 200
     * líneas; en edición permite conservar recetas anteriores que superen ese tamaño.
     * La cantidad representa el consumo por una unidad del producto, en la unidad del
     * inventario seleccionado. Se normaliza como texto decimal exacto, sin usar flotantes.
     */
    private function validarMateriales(Request $request, bool $edicion = false, int $limite = 200): array
    {
        $producto = Rule::exists('productos', 'id');
        $inventario = Rule::exists('inventarios_compra', 'id');
        if (! $edicion) {
            $producto->where('estado', true);
            $inventario->where('estado', true);
        }
        $reglas = [
            'producto_id' => ['required', 'integer', $producto],
            'materiales' => ['required', 'array', 'min:1', 'max:'.$limite],
            'materiales.*' => ['required', 'array:inventario_compra_id,cantidad_requerida,observacion'.($edicion ? ',id' : '')],
            'materiales.*.inventario_compra_id' => ['required', 'integer', 'distinct', $inventario],
            'materiales.*.cantidad_requerida' => ['required'],
            'materiales.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
        if ($edicion) {
            $reglas['huella_receta'] = ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'];
            $reglas['materiales.*.id'] = ['nullable', 'integer', 'min:1', 'distinct'];
        }
        $datos = $request->validate($reglas);
        $calculadora = new CalculadoraProduccionService;
        $errores = [];
        foreach ($datos['materiales'] as $indice => &$material) {
            try {
                $material['cantidad_requerida'] = $calculadora->normalizarCantidadRequerida($material['cantidad_requerida']);
            } catch (ValidationException $excepcion) {
                $errores['materiales.'.$indice.'.cantidad_requerida'] = $excepcion->errors()['cantidad_requerida'];
            }
        }
        unset($material);
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }

    /** La huella incluye disponibilidad y valores originales, con orden estable para detectar cambios simultáneos. */
    private function huellaReceta(Producto $producto, Collection $materiales): string
    {
        return hash('sha256', json_encode([$producto->receta_activa, $materiales->sortBy('id')->map(fn ($material) => [
            $material->id, $material->producto_id, $material->inventario_compra_id,
            $material->getRawOriginal('cantidad_requerida'), $material->observacion, $material->estado,
            $material->getRawOriginal('updated_at'),
        ])->values()->all()], JSON_THROW_ON_ERROR));
    }

    /**
     * Bloquea primero el producto y después la línea de acceso, verificando su pertenencia.
     * Devuelve el producto leído bajo bloqueo como relación: reutilizar esa lectura evita
     * recuperar un estado anterior desde una instantánea de MySQL tras esperar otro cambio.
     */
    private function bloquearReceta(MaterialProducto $material): MaterialProducto
    {
        $actual = MaterialProducto::findOrFail($material->id);
        $producto = Producto::whereKey($actual->producto_id)->lockForUpdate()->firstOrFail();
        $actual = MaterialProducto::whereKey($material->id)->lockForUpdate()->firstOrFail();

        if ((int) $actual->producto_id !== (int) $producto->id) {
            throw ValidationException::withMessages([
                'producto_id' => 'La receta cambió de producto mientras se editaba. Actualiza la página e intenta nuevamente.',
            ]);
        }

        return $actual->setRelation('producto', $producto);
    }

    private function exigirMaterialUnico(array $datos, string $campoError): void
    {
        $query = MaterialProducto::where('producto_id', $datos['producto_id'])
            ->where('inventario_compra_id', $datos['inventario_compra_id']);
        if ($query->lockForUpdate()->first()) {
            throw ValidationException::withMessages([
                $campoError => 'Este material ya está asignado al producto seleccionado. Revise la receta existente, incluidos sus materiales inactivos.',
            ]);
        }
    }
}
