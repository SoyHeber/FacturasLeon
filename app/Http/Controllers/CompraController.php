<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\InventarioCompra;
use App\Services\CalculadoraDteService;
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


    public function create()
    {
        $proveedores = Proveedor::with('direccion')
            ->where('estado', true)
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


    public function store(
        Request $request,
        CalculadoraDteService $calculadoraDte
    ) {
        $validated = $request->validate([
            // Encabezado

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

                Rule::unique(
                    'compras',
                    'numero'
                )->where(function ($query) use ($request) {
                    return $query
                        ->where(
                            'proveedor_id',
                            $request->proveedor_id
                        )
                        ->where(
                            'serie',
                            strtoupper($request->serie)
                        );
                }),
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


            // Detalles

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

            'detalles.*.observacion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'numero.unique' =>
            'Ya existe una compra para este proveedor con la misma serie y número.',

            'numero_autorizacion.unique' =>
            'El número de autorización ya se encuentra registrado.',
        ]);


        // Calcula los valores en el servidor

        $calculo = $calculadoraDte->calcularCompra(
            $validated['detalles']
        );

        $detallesCalculados =
            $calculo['detalles'];

        $totales =
            $calculo['totales'];


        DB::transaction(function () use (
            $validated,
            $request,
            $detallesCalculados,
            $totales
        ) {

            // Crea la compra

            $compra = Compra::create([
                'proveedor_id' =>
                $validated['proveedor_id'],

                'user_id' =>
                auth()->id(),

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
                $totales['importe_bruto'],

                'importe_descuento' =>
                $totales['importe_descuento'],

                'importe_exento' =>
                $totales['importe_exento'],

                'importe_otros' =>
                $totales['importe_otros'],

                'importe_neto' =>
                $totales['importe_neto'],

                'importe_iva' =>
                $totales['importe_iva'],

                'importe_total' =>
                $totales['importe_total'],

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has('estado'),
            ]);


            // Crea los detalles

            foreach (
                $detallesCalculados
                as $index => $detalle
            ) {
                DetalleCompra::create([
                    'compra_id' =>
                    $compra->id,

                    'numero_linea' =>
                    $index + 1,

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
            }
        });


        return redirect()
            ->route('compras.index')
            ->with(
                'success',
                'Compra registrada correctamente.'
            );
    }


    public function show(Compra $compra)
    {
        $compra->load([
            'proveedor.direccion',
            'user',
            'detalles.inventarioCompra',
        ]);

        return view(
            'compras.show',
            compact('compra')
        );
    }


    public function edit(Compra $compra)
    {
        $proveedores = Proveedor::with('direccion')
            ->where(function ($query) use ($compra) {
                $query
                    ->where('estado', true)
                    ->orWhere(
                        'id',
                        $compra->proveedor_id
                    );
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


    public function update(
        Request $request,
        Compra $compra,
        CalculadoraDteService $calculadoraDte
    ) {
        $validated = $request->validate([
            // Encabezado

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

                Rule::unique(
                    'compras',
                    'numero'
                )
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where(
                                'proveedor_id',
                                $request->proveedor_id
                            )
                            ->where(
                                'serie',
                                strtoupper(
                                    $request->serie
                                )
                            );
                    })
                    ->ignore(
                        $compra->id
                    ),
            ],

            'numero_autorizacion' => [
                'required',
                'string',
                'size:36',

                Rule::unique(
                    'compras',
                    'numero_autorizacion'
                )->ignore(
                    $compra->id
                ),
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


            // Detalles

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

            'detalles.*.observacion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'numero.unique' =>
            'Ya existe una compra para este proveedor con la misma serie y número.',

            'numero_autorizacion.unique' =>
            'El número de autorización ya se encuentra registrado.',
        ]);


        // Calcula nuevamente los importes

        $calculo = $calculadoraDte->calcularCompra(
            $validated['detalles']
        );

        $detallesCalculados =
            $calculo['detalles'];

        $totales =
            $calculo['totales'];


        DB::transaction(function () use (
            $validated,
            $request,
            $compra,
            $detallesCalculados,
            $totales
        ) {

            // Obtiene los detalles enviados

            $idsDetalles = collect(
                $detallesCalculados
            )
                ->pluck('id')
                ->filter()
                ->map(
                    fn($id) =>
                    (int) $id
                )
                ->values();


            // Valida que pertenezcan a la compra

            if ($idsDetalles->isNotEmpty()) {

                $cantidadValidos =
                    $compra
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


            // Elimina detalles retirados

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


            // Actualiza o crea los detalles

            foreach (
                $detallesCalculados
                as $index => $detalle
            ) {
                $datosDetalle = [
                    'compra_id' =>
                    $compra->id,

                    'numero_linea' =>
                    $index + 1,

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


                if (!empty($detalle['id'])) {

                    $detalleExistente =
                        $compra
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

                    DetalleCompra::create(
                        $datosDetalle
                    );
                }
            }


            // Actualiza la compra

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
                $totales['importe_bruto'],

                'importe_descuento' =>
                $totales['importe_descuento'],

                'importe_exento' =>
                $totales['importe_exento'],

                'importe_otros' =>
                $totales['importe_otros'],

                'importe_neto' =>
                $totales['importe_neto'],

                'importe_iva' =>
                $totales['importe_iva'],

                'importe_total' =>
                $totales['importe_total'],

                'observacion' =>
                $validated['observacion'] ?? null,

                'estado' =>
                $request->has(
                    'estado'
                ),
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


    public function cambiarEstado(Compra $compra)
    {
        $compra->update([
            'estado' =>
            !$compra->estado,
        ]);

        return redirect()
            ->route('compras.index')
            ->with(
                'success',
                'Estado de la compra actualizado correctamente.'
            );
    }
}
