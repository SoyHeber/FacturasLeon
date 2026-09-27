<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\InventarioCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with([
            'proveedor',
            'user',
        ]);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('proveedor')) {
            $query->whereHas('proveedor', function ($q) use ($request) {
                $q->where(
                    'nombre',
                    'like',
                    '%' . $request->proveedor . '%'
                );
            });
        }

        if ($request->filled('tipo_dte')) {
            $query->where(
                'tipo_dte',
                'like',
                '%' . $request->tipo_dte . '%'
            );
        }

        if ($request->filled('serie')) {
            $query->where(
                'serie',
                'like',
                '%' . $request->serie . '%'
            );
        }

        if ($request->filled('numero')) {
            $query->where(
                'numero',
                $request->numero
            );
        }

        if ($request->filled('numero_autorizacion')) {
            $query->where(
                'numero_autorizacion',
                'like',
                '%' . $request->numero_autorizacion . '%'
            );
        }

        if ($request->filled('fecha_emision')) {
            $query->whereDate(
                'fecha_emision',
                $request->fecha_emision
            );
        }

        if ($request->filled('moneda')) {
            $query->where(
                'moneda',
                $request->moneda
            );
        }

        if ($request->filled('importe_total')) {
            $query->where(
                'importe_total',
                $request->importe_total
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
            );
        }

        $compras = $query
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view(
            'compras.index',
            compact('compras')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    |
    | Ahora cargamos:
    | - Proveedores
    | - Inventarios de compra
    |
    | Los inventarios serán utilizados para agregar las líneas del detalle.
    |
    */
    public function create()
    {
        $proveedores = Proveedor::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        return view(
            'compras.create',
            compact(
                'proveedores',
                'inventariosCompra'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STORE MAESTRO - DETALLE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | ENCABEZADO
            |--------------------------------------------------------------------------
            */

            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],

            'tipo_dte' => [
                'required',
                Rule::in([
                    'FACT',
                ]),
            ],

            'serie' => [
                'required',
                'string',
                'max:20',
            ],

            'numero' => [
                'required',
                'integer',
                'min:0',
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',
                'unique:compras,numero_autorizacion',
            ],

            'fecha_emision' => [
                'required',
                'date',
            ],

            'fecha_certificacion' => [
                'nullable',
                'date',
            ],

            'moneda' => [
                'required',
                Rule::in([
                    'GTQ',
                    'USD',
                ]),
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],


            /*
            |--------------------------------------------------------------------------
            | DETALLES
            |--------------------------------------------------------------------------
            */

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],

            'detalles.*.inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.porcentaje_descuento' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'detalles.*.importe_bruto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_descuento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_exento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_otros' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_neto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_iva' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_total' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.observacion' => [
                'nullable',
                'string',
            ],
        ]);

        DB::transaction(function () use ($validated, $request) {

            /*
            |--------------------------------------------------------------------------
            | 1. CREAR CABECERA
            |--------------------------------------------------------------------------
            |
            | Inicialmente los totales quedan en cero.
            | Después se actualizan según la suma de los detalles.
            |
            */

            $compra = Compra::create([
                'proveedor_id' =>
                $validated['proveedor_id'],

                'user_id' =>
                auth()->id(),

                'tipo_dte' =>
                strtoupper($validated['tipo_dte']),

                'serie' =>
                strtoupper($validated['serie']),

                'numero' =>
                $validated['numero'],

                'numero_autorizacion' =>
                strtoupper(
                    $validated['numero_autorizacion']
                ),

                'fecha_emision' =>
                $validated['fecha_emision'],

                'fecha_certificacion' =>
                $validated['fecha_certificacion'] ?? null,

                'moneda' =>
                strtoupper($validated['moneda']),

                'importe_bruto' => 0,

                'importe_descuento' => 0,

                'importe_exento' => 0,

                'importe_otros' => 0,

                'importe_neto' => 0,

                'importe_iva' => 0,

                'importe_total' => 0,

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has('estado'),
            ]);


            /*
            |--------------------------------------------------------------------------
            | 2. INICIALIZAR TOTALES
            |--------------------------------------------------------------------------
            */

            $totalBruto = 0;
            $totalDescuento = 0;
            $totalExento = 0;
            $totalOtros = 0;
            $totalNeto = 0;
            $totalIva = 0;
            $totalGeneral = 0;


            /*
            |--------------------------------------------------------------------------
            | 3. CREAR DETALLES
            |--------------------------------------------------------------------------
            */

            foreach (
                $validated['detalles']
                as $index => $detalle
            ) {

                /*
                 * El número de línea se genera automáticamente.
                 *
                 * index 0 = línea 1
                 * index 1 = línea 2
                 * index 2 = línea 3
                 */
                $numeroLinea = $index + 1;

                DetalleCompra::create([
                    'compra_id' =>
                    $compra->id,

                    'numero_linea' =>
                    $numeroLinea,

                    'inventario_compra_id' =>
                    $detalle['inventario_compra_id'],

                    'cantidad' =>
                    $detalle['cantidad'],

                    'precio_unitario' =>
                    $detalle['precio_unitario'],

                    'porcentaje_descuento' =>
                    $detalle['porcentaje_descuento'],

                    'importe_bruto' =>
                    $detalle['importe_bruto'],

                    'importe_descuento' =>
                    $detalle['importe_descuento'],

                    'importe_exento' =>
                    $detalle['importe_exento'],

                    'importe_otros' =>
                    $detalle['importe_otros'],

                    'importe_neto' =>
                    $detalle['importe_neto'],

                    'importe_iva' =>
                    $detalle['importe_iva'],

                    'importe_total' =>
                    $detalle['importe_total'],

                    'observacion' =>
                    $detalle['observacion'] ?? null,

                    'estado' => true,
                ]);


                /*
                |--------------------------------------------------------------------------
                | 4. ACUMULAR TOTALES
                |--------------------------------------------------------------------------
                */

                $totalBruto +=
                    (float) $detalle['importe_bruto'];

                $totalDescuento +=
                    (float) $detalle['importe_descuento'];

                $totalExento +=
                    (float) $detalle['importe_exento'];

                $totalOtros +=
                    (float) $detalle['importe_otros'];

                $totalNeto +=
                    (float) $detalle['importe_neto'];

                $totalIva +=
                    (float) $detalle['importe_iva'];

                $totalGeneral +=
                    (float) $detalle['importe_total'];
            }


            /*
            |--------------------------------------------------------------------------
            | 5. ACTUALIZAR TOTALES DE LA COMPRA
            |--------------------------------------------------------------------------
            */

            $compra->update([
                'importe_bruto' =>
                round($totalBruto, 2),

                'importe_descuento' =>
                round($totalDescuento, 2),

                'importe_exento' =>
                round($totalExento, 2),

                'importe_otros' =>
                round($totalOtros, 2),

                'importe_neto' =>
                round($totalNeto, 2),

                'importe_iva' =>
                round($totalIva, 2),

                'importe_total' =>
                round($totalGeneral, 2),
            ]);
        });

        return redirect()
            ->route('compras.index')
            ->with(
                'success',
                'Compra registrada correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Compra $compra)
    {
        $compra->load([
            'proveedor',
            'user',
            'detalles.inventarioCompra',
        ]);

        return view(
            'compras.show',
            compact('compra')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    |
    | De momento seguimos utilizando el formulario de edición actual.
    |
    | En una siguiente etapa convertiremos también EDIT en maestro-detalle.
    |
    */
    public function edit(Compra $compra)
    {
        $proveedores = Proveedor::with('direccion')
            ->where(function ($query) use ($compra) {
                $query->where('estado', true)
                    ->orWhere('id', $compra->proveedor_id);
            })
            ->orderBy('nombre')
            ->get();

        $inventariosCompra = InventarioCompra::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        $compra->load([
            'proveedor.direccion',
            'detalles.inventarioCompra',
        ]);

        return view(
            'compras.edit',
            compact(
                'compra',
                'proveedores',
                'inventariosCompra'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    |
    | Por ahora conservamos el comportamiento actual.
    |
    | Cuando construyamos el formulario maestro-detalle de edición,
    | este método será actualizado para modificar también las líneas.
    |
    */
    public function update(
        Request $request,
        Compra $compra
    ) {
        $validated = $request->validate([

            /*
        |--------------------------------------------------------------------------
        | ENCABEZADO
        |--------------------------------------------------------------------------
        */

            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],

            'tipo_dte' => [
                'required',
                Rule::in([
                    'FACT',
                ]),
            ],

            'serie' => [
                'required',
                'string',
                'max:20',
            ],

            'numero' => [
                'required',
                'integer',
                'min:0',
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',

                Rule::unique(
                    'compras',
                    'numero_autorizacion'
                )->ignore($compra->id),
            ],

            'fecha_emision' => [
                'required',
                'date',
            ],

            'fecha_certificacion' => [
                'nullable',
                'date',
            ],

            'moneda' => [
                'required',
                Rule::in([
                    'GTQ',
                    'USD',
                ]),
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],


            /*
        |--------------------------------------------------------------------------
        | DETALLES
        |--------------------------------------------------------------------------
        */

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],

            'detalles.*.id' => [
                'nullable',
                'integer',
                'exists:detalles_compra,id',
            ],

            'detalles.*.inventario_compra_id' => [
                'required',
                'exists:inventarios_compra,id',
            ],

            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.porcentaje_descuento' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'detalles.*.importe_bruto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_descuento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_exento' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_otros' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_neto' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_iva' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.importe_total' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.observacion' => [
                'nullable',
                'string',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $request,
            $compra
        ) {

            /*
        |--------------------------------------------------------------------------
        | 1. IDs de detalles existentes enviados desde el formulario
        |--------------------------------------------------------------------------
        */

            $idsDetalles = collect(
                $validated['detalles']
            )
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->values();


            /*
        |--------------------------------------------------------------------------
        | 2. Verificar que los IDs realmente pertenezcan a esta compra
        |--------------------------------------------------------------------------
        */

            if ($idsDetalles->isNotEmpty()) {

                $cantidadValidos = $compra
                    ->detalles()
                    ->whereIn(
                        'id',
                        $idsDetalles
                    )
                    ->count();

                if (
                    $cantidadValidos !==
                    $idsDetalles->count()
                ) {
                    throw ValidationException::withMessages([
                        'detalles' =>
                        'Uno de los detalles no pertenece a la compra seleccionada.',
                    ]);
                }
            }


            /*
        |--------------------------------------------------------------------------
        | 3. Eliminar líneas que fueron retiradas del formulario
        |--------------------------------------------------------------------------
        |
        | Por ahora podemos hacerlo porque todavía no hemos automatizado
        | los movimientos de inventario provenientes de compras.
        |
        */

            if ($idsDetalles->isEmpty()) {

                $compra
                    ->detalles()
                    ->delete();
            } else {

                $compra
                    ->detalles()
                    ->whereNotIn(
                        'id',
                        $idsDetalles
                    )
                    ->delete();
            }


            /*
        |--------------------------------------------------------------------------
        | 4. Inicializar totales
        |--------------------------------------------------------------------------
        */

            $totalBruto = 0;
            $totalDescuento = 0;
            $totalExento = 0;
            $totalOtros = 0;
            $totalNeto = 0;
            $totalIva = 0;
            $totalGeneral = 0;


            /*
        |--------------------------------------------------------------------------
        | 5. Actualizar o crear líneas
        |--------------------------------------------------------------------------
        */

            foreach (
                $validated['detalles']
                as $index => $detalle
            ) {

                $numeroLinea = $index + 1;

                $datosDetalle = [
                    'compra_id' =>
                    $compra->id,

                    'numero_linea' =>
                    $numeroLinea,

                    'inventario_compra_id' =>
                    $detalle['inventario_compra_id'],

                    'cantidad' =>
                    $detalle['cantidad'],

                    'precio_unitario' =>
                    $detalle['precio_unitario'],

                    'porcentaje_descuento' =>
                    $detalle['porcentaje_descuento'],

                    'importe_bruto' =>
                    $detalle['importe_bruto'],

                    'importe_descuento' =>
                    $detalle['importe_descuento'],

                    'importe_exento' =>
                    $detalle['importe_exento'],

                    'importe_otros' =>
                    $detalle['importe_otros'],

                    'importe_neto' =>
                    $detalle['importe_neto'],

                    'importe_iva' =>
                    $detalle['importe_iva'],

                    'importe_total' =>
                    $detalle['importe_total'],

                    'observacion' =>
                    $detalle['observacion'] ?? null,

                    'estado' => true,
                ];


                /*
            |--------------------------------------------------------------------------
            | Línea existente
            |--------------------------------------------------------------------------
            */

                if (!empty($detalle['id'])) {

                    $detalleExistente = $compra
                        ->detalles()
                        ->where(
                            'id',
                            $detalle['id']
                        )
                        ->firstOrFail();

                    $detalleExistente->update(
                        $datosDetalle
                    );
                } else {

                    /*
                |--------------------------------------------------------------------------
                | Línea nueva
                |--------------------------------------------------------------------------
                */

                    DetalleCompra::create(
                        $datosDetalle
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Acumular totales
            |--------------------------------------------------------------------------
            */

                $totalBruto +=
                    (float) $detalle['importe_bruto'];

                $totalDescuento +=
                    (float) $detalle['importe_descuento'];

                $totalExento +=
                    (float) $detalle['importe_exento'];

                $totalOtros +=
                    (float) $detalle['importe_otros'];

                $totalNeto +=
                    (float) $detalle['importe_neto'];

                $totalIva +=
                    (float) $detalle['importe_iva'];

                $totalGeneral +=
                    (float) $detalle['importe_total'];
            }


            /*
        |--------------------------------------------------------------------------
        | 6. Actualizar cabecera
        |--------------------------------------------------------------------------
        */

            $compra->update([
                'proveedor_id' =>
                $validated['proveedor_id'],

                'tipo_dte' =>
                strtoupper(
                    $validated['tipo_dte']
                ),

                'serie' =>
                strtoupper(
                    $validated['serie']
                ),

                'numero' =>
                $validated['numero'],

                'numero_autorizacion' =>
                strtoupper(
                    $validated['numero_autorizacion']
                ),

                'fecha_emision' =>
                $validated['fecha_emision'],

                'fecha_certificacion' =>
                $validated['fecha_certificacion'] ?? null,

                'moneda' =>
                strtoupper(
                    $validated['moneda']
                ),

                'importe_bruto' =>
                round(
                    $totalBruto,
                    2
                ),

                'importe_descuento' =>
                round(
                    $totalDescuento,
                    2
                ),

                'importe_exento' =>
                round(
                    $totalExento,
                    2
                ),

                'importe_otros' =>
                round(
                    $totalOtros,
                    2
                ),

                'importe_neto' =>
                round(
                    $totalNeto,
                    2
                ),

                'importe_iva' =>
                round(
                    $totalIva,
                    2
                ),

                'importe_total' =>
                round(
                    $totalGeneral,
                    2
                ),

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has('estado'),
            ]);
        });


        return redirect()
            ->route(
                'compras.show',
                $compra->id
            )
            ->with(
                'success',
                'Compra actualizada correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO
    |--------------------------------------------------------------------------
    */
    public function cambiarEstado(Compra $compra)
    {
        $compra->update([
            'estado' => !$compra->estado,
        ]);

        return redirect()
            ->route('compras.index')
            ->with(
                'success',
                'Estado de la compra actualizado correctamente.'
            );
    }
}
