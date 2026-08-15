<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = Producto::with([
            'categoria',
            'marca'
        ])
            ->latest()
            ->paginate(10);

        return view('productos.index', compact('productos'));
    }

    public function create()
    {
        $categorias = Categoria::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $marcas = Marca::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'productos.create',
            compact('categorias', 'marcas')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:50',
                'unique:productos,codigo',
            ],

            'nombre' => [
                'required',
                'string',
                'max:80',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'categoria_id' => [
                'required',
                'exists:categorias,id',
            ],

            'marca_id' => [
                'required',
                'exists:marcas,id',
            ],

            'imagen' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $imgPath = null;

        if ($request->hasFile('imagen')) {
            $imgPath = $request->file('imagen')
                ->store('productos', 'public');
        }

        Producto::create([
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'categoria_id' => $validated['categoria_id'],
            'marca_id' => $validated['marca_id'],
            'img_path' => $imgPath,
            'estado' => $request->has('estado'),
        ]);

        return redirect()
            ->route('productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Producto $producto)
    {
        $producto->load([
            'categoria',
            'marca'
        ]);

        return view(
            'productos.show',
            compact('producto')
        );
    }

    public function edit(Producto $producto)
    {
        $categorias = Categoria::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $marcas = Marca::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'productos.edit',
            compact(
                'producto',
                'categorias',
                'marcas'
            )
        );
    }

    public function update(
        Request $request,
        Producto $producto
    ) {
        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:50',

                Rule::unique('productos', 'codigo')
                    ->ignore($producto->id),
            ],

            'nombre' => [
                'required',
                'string',
                'max:80',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'categoria_id' => [
                'required',
                'exists:categorias,id',
            ],

            'marca_id' => [
                'required',
                'exists:marcas,id',
            ],

            'imagen' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],
        ]);

        $imgPath = $producto->img_path;

        if ($request->hasFile('imagen')) {

            if (
                $producto->img_path &&
                Storage::disk('public')
                ->exists($producto->img_path)
            ) {
                Storage::disk('public')
                    ->delete($producto->img_path);
            }

            $imgPath = $request->file('imagen')
                ->store('productos', 'public');
        }

        $producto->update([
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'categoria_id' => $validated['categoria_id'],
            'marca_id' => $validated['marca_id'],
            'img_path' => $imgPath,
            'estado' => $request->has('estado'),
        ]);

        return redirect()
            ->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function cambiarEstado(Producto $producto)
    {
        $producto->update([
            'estado' => !$producto->estado,
        ]);

        return redirect()
            ->route('productos.index')
            ->with(
                'success',
                'Estado del producto actualizado correctamente.'
            );
    }
}
