<?php

namespace App\Http\Controllers;

use App\Models\Produccion;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProduccionController extends Controller
{
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
                    '%' . $request->producto . '%'
                );
            });
        }

        if ($request->filled('usuario')) {
            $query->whereHas('usuario', function ($q) use ($request) {
                $q->where(
                    'name',
                    'like',
                    '%' . $request->usuario . '%'
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
                '%' . $request->observacion . '%'
            );
        }

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->estado
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

    public function create()
    {
        $productos = Producto::where(
            'estado',
            true
        )
            ->orderBy('nombre')
            ->get();

        return view(
            'producciones.create',
            compact('productos')
        );
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
            ],

            'fecha_produccion' => [
                'required',
                'date',
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

        Produccion::create([
            'producto_id' =>
            $validated['producto_id'],

            'user_id' =>
            Auth::id(),

            'cantidad' =>
            $validated['cantidad'],

            'fecha_produccion' =>
            $validated['fecha_produccion'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

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
        ]);

        return view(
            'producciones.show',
            compact('produccion')
        );
    }

    public function edit(Produccion $produccion)
    {
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

        return view(
            'producciones.edit',
            compact(
                'produccion',
                'productos'
            )
        );
    }

    public function update(
        Request $request,
        Produccion $produccion
    ) {
        $validated = $request->validate([
            'producto_id' => [
                'required',
                'exists:productos,id',
            ],

            'cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'fecha_produccion' => [
                'required',
                'date',
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

        $produccion->update([
            'producto_id' =>
            $validated['producto_id'],

            'cantidad' =>
            $validated['cantidad'],

            'fecha_produccion' =>
            $validated['fecha_produccion'],

            'observacion' =>
            $validated['observacion'] ?? null,

            'estado' =>
            $request->has('estado'),
        ]);

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
        $produccion->update([
            'estado' => !$produccion->estado,
        ]);

        return redirect()
            ->route('producciones.index')
            ->with(
                'success',
                'Estado actualizado correctamente.'
            );
    }
}
