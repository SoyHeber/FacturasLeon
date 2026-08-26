<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PaisController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\MunicipioController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\TipoIdentificacionController;
use App\Http\Controllers\DireccionController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\InventarioController;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\CompraController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $totalCategorias = Categoria::where('estado', 1)->count();
    $totalMarcas = Marca::where('estado', 1)->count();
    $totalProductos = Producto::where('estado', 1)->count();

    return view('dashboard', compact(
        'totalCategorias',
        'totalMarcas',
        'totalProductos'
    ));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*Rutas de Categorias*/
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::get('/categorias/create', [CategoriaController::class, 'create'])->name('categorias.create');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::get('/categorias/{categoria}', [CategoriaController::class, 'show'])->name('categorias.show');
    Route::get('/categorias/{categoria}/edit', [CategoriaController::class, 'edit'])->name('categorias.edit');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    /* Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy'); */
    Route::patch('/categorias/{categoria}/estado', [CategoriaController::class, 'cambiarEstado'])->name('categorias.cambiar-estado');

    /*Rutas de Marcas*/
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::get('/marcas/create', [MarcaController::class, 'create'])->name('marcas.create');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::get('/marcas/{marca}', [MarcaController::class, 'show'])->name('marcas.show');
    Route::get('/marcas/{marca}/edit', [MarcaController::class, 'edit'])->name('marcas.edit');
    Route::put('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    /* Route::delete('/marcas/{marca}', [MarcaController::class, 'destroy'])->name('marcas.destroy'); */
    Route::patch('/marcas/{marca}/estado', [MarcaController::class, 'cambiarEstado'])->name('marcas.cambiar-estado');

    /*Rutas para Paises*/
    Route::get('/paises', [PaisController::class, 'index'])->name('paises.index');
    Route::get('/paises/create', [PaisController::class, 'create'])->name('paises.create');
    Route::post('/paises', [PaisController::class, 'store'])->name('paises.store');
    Route::get('/paises/{pais}', [PaisController::class, 'show'])->name('paises.show');
    Route::get('/paises/{pais}/edit', [PaisController::class, 'edit'])->name('paises.edit');
    Route::put('/paises/{pais}', [PaisController::class, 'update'])->name('paises.update');
    Route::patch('/paises/{pais}/estado', [PaisController::class, 'cambiarEstado'])->name('paises.cambiar-estado');

    /*Rutas para Departamentos*/
    Route::get('/departamentos', [DepartamentoController::class, 'index'])->name('departamentos.index');
    Route::get('/departamentos/create', [DepartamentoController::class, 'create'])->name('departamentos.create');
    Route::post('/departamentos', [DepartamentoController::class, 'store'])->name('departamentos.store');
    Route::get('/departamentos/{departamento}', [DepartamentoController::class, 'show'])->name('departamentos.show');
    Route::get('/departamentos/{departamento}/edit', [DepartamentoController::class, 'edit'])->name('departamentos.edit');
    Route::put('/departamentos/{departamento}', [DepartamentoController::class, 'update'])->name('departamentos.update');
    Route::patch('/departamentos/{departamento}/estado', [DepartamentoController::class, 'cambiarEstado'])->name('departamentos.cambiar-estado');

    /*Rutas para Municipios*/
    Route::get('/municipios', [MunicipioController::class, 'index'])->name('municipios.index');
    Route::get('/municipios/create', [MunicipioController::class, 'create'])->name('municipios.create');
    Route::post('/municipios', [MunicipioController::class, 'store'])->name('municipios.store');
    Route::get('/municipios/{municipio}', [MunicipioController::class, 'show'])->name('municipios.show');
    Route::get('/municipios/{municipio}/edit', [MunicipioController::class, 'edit'])->name('municipios.edit');
    Route::put('/municipios/{municipio}', [MunicipioController::class, 'update'])->name('municipios.update');
    Route::patch('/municipios/{municipio}/estado', [MunicipioController::class, 'cambiarEstado'])->name('municipios.cambiar-estado');

    /*Rutas para Metodos_Pago*/
    Route::get('/metodos-pago', [MetodoPagoController::class, 'index'])->name('metodos_pago.index');
    Route::get('/metodos-pago/create', [MetodoPagoController::class, 'create'])->name('metodos_pago.create');
    Route::post('/metodos-pago', [MetodoPagoController::class, 'store'])->name('metodos_pago.store');
    Route::get('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'show'])->name('metodos_pago.show');
    Route::get('/metodos-pago/{metodoPago}/edit', [MetodoPagoController::class, 'edit'])->name('metodos_pago.edit');
    Route::put('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'update'])->name('metodos_pago.update');
    Route::patch('/metodos-pago/{metodoPago}/estado', [MetodoPagoController::class, 'cambiarEstado'])->name('metodos_pago.cambiar-estado');

    /*Rutas para Tipos_Identificacion*/
    Route::get('/tipos-identificacion', [TipoIdentificacionController::class, 'index'])->name('tipos_identificacion.index');
    Route::get('/tipos-identificacion/create', [TipoIdentificacionController::class, 'create'])->name('tipos_identificacion.create');
    Route::post('/tipos-identificacion', [TipoIdentificacionController::class, 'store'])->name('tipos_identificacion.store');
    Route::get('/tipos-identificacion/{tipoIdentificacion}', [TipoIdentificacionController::class, 'show'])->name('tipos_identificacion.show');
    Route::get('/tipos-identificacion/{tipoIdentificacion}/edit', [TipoIdentificacionController::class, 'edit'])->name('tipos_identificacion.edit');
    Route::put('/tipos-identificacion/{tipoIdentificacion}', [TipoIdentificacionController::class, 'update'])->name('tipos_identificacion.update');
    Route::patch('/tipos-identificacion/{tipoIdentificacion}/estado', [TipoIdentificacionController::class, 'cambiarEstado'])->name('tipos_identificacion.cambiar-estado');

    /*Rutas para Direcciones*/
    Route::get('/direcciones', [DireccionController::class, 'index'])->name('direcciones.index');
    Route::get('/direcciones/create', [DireccionController::class, 'create'])->name('direcciones.create');
    Route::post('/direcciones', [DireccionController::class, 'store'])->name('direcciones.store');
    Route::get('/direcciones/{direccion}', [DireccionController::class, 'show'])->name('direcciones.show');
    Route::get('/direcciones/{direccion}/edit', [DireccionController::class, 'edit'])->name('direcciones.edit');
    Route::put('/direcciones/{direccion}', [DireccionController::class, 'update'])->name('direcciones.update');
    Route::patch('/direcciones/{direccion}/estado', [DireccionController::class, 'cambiarEstado'])->name('direcciones.cambiar-estado');

    /*Rutas para Proveedores*/
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::get('/proveedores/create', [ProveedorController::class, 'create'])->name('proveedores.create');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->name('proveedores.show');
    Route::get('/proveedores/{proveedor}/edit', [ProveedorController::class, 'edit'])->name('proveedores.edit');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::patch('/proveedores/{proveedor}/estado', [ProveedorController::class, 'cambiarEstado'])->name('proveedores.cambiar-estado');

    /*Rutas para Clientes*/
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{cliente}/edit', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::patch('/clientes/{cliente}/estado', [ClienteController::class, 'cambiarEstado'])->name('clientes.cambiar-estado');

    /*Rutas para Productos*/
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/create', [ProductoController::class, 'create'])->name('productos.create');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
    Route::get('/productos/{producto}/edit', [ProductoController::class, 'edit'])->name('productos.edit');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::patch('/productos/{producto}/estado', [ProductoController::class, 'cambiarEstado'])->name('productos.cambiar-estado');

    /*Rutas para Inventarios*/
    Route::get('/inventarios', [InventarioController::class, 'index'])->name('inventarios.index');
    Route::get('/inventarios/create', [InventarioController::class, 'create'])->name('inventarios.create');
    Route::post('/inventarios', [InventarioController::class, 'store'])->name('inventarios.store');
    Route::get('/inventarios/{inventario}', [InventarioController::class, 'show'])->name('inventarios.show');
    Route::get('/inventarios/{inventario}/edit', [InventarioController::class, 'edit'])->name('inventarios.edit');
    Route::put('/inventarios/{inventario}', [InventarioController::class, 'update'])->name('inventarios.update');
    Route::patch('/inventarios/{inventario}/estado', [InventarioController::class, 'cambiarEstado'])->name('inventarios.cambiar-estado');

    /*Rutas para Usuarios*/
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/estado', [UserController::class, 'cambiarEstado'])->name('users.cambiar-estado');

    /*Rutas para Movimientos de Inventario*/
    Route::get('/movimientos-inventario', [MovimientoInventarioController::class, 'index'])->name('movimientos_inventario.index');
    Route::get('/movimientos-inventario/create', [MovimientoInventarioController::class, 'create'])->name('movimientos_inventario.create');
    Route::post('/movimientos-inventario', [MovimientoInventarioController::class, 'store'])->name('movimientos_inventario.store');
    Route::get('/movimientos-inventario/{movimientoInventario}', [MovimientoInventarioController::class, 'show'])->name('movimientos_inventario.show');
    Route::get('/movimientos-inventario/{movimientoInventario}/edit', [MovimientoInventarioController::class, 'edit'])->name('movimientos_inventario.edit');
    Route::put('/movimientos-inventario/{movimientoInventario}', [MovimientoInventarioController::class, 'update'])->name('movimientos_inventario.update');
    Route::patch('/movimientos-inventario/{movimientoInventario}/estado', [MovimientoInventarioController::class, 'cambiarEstado'])->name('movimientos_inventario.cambiar-estado');

    /*Rutas para Compras*/
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/create', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
    Route::get('/compras/{compra}/edit', [CompraController::class, 'edit'])->name('compras.edit');
    Route::put('/compras/{compra}', [CompraController::class, 'update'])->name('compras.update');
    Route::patch('/compras/{compra}/estado', [CompraController::class, 'cambiarEstado'])->name('compras.cambiar-estado');
});

require __DIR__ . '/auth.php';
