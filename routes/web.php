<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PaisController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\MunicipioController;

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
    return view('dashboard');
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
});

require __DIR__ . '/auth.php';
