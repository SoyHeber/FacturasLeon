@extends('layouts.app-bootstrap')

@section('content')
    <div class="row justify-content-center">

        <div class="col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">
                    <h1 class="h4 mb-0">
                        Editar Inventario
                    </h1>
                </div>

                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">

                            <strong>
                                Corrige los siguientes errores:
                            </strong>

                            <ul class="mb-0 mt-2">

                                @foreach ($errors->all() as $error)
                                    <li>
                                        {{ $error }}
                                    </li>
                                @endforeach

                            </ul>

                        </div>
                    @endif

                    <form action="{{ route('inventarios.update', $inventario->id) }}" method="POST">

                        @csrf
                        @method('PUT')

                        <div class="mb-3">

                            <label for="producto_id" class="form-label">
                                Producto
                            </label>

                            <select name="producto_id" id="producto_id" class="form-select" required>

                                <option value="">
                                    Seleccione un producto
                                </option>

                                @foreach ($productos as $producto)
                                    <option value="{{ $producto->id }}"
                                        {{ old('producto_id', $inventario->producto_id) == $producto->id ? 'selected' : '' }}>

                                        {{ $producto->nombre }}
                                        -
                                        {{ $producto->codigo }}

                                    </option>
                                @endforeach

                            </select>

                        </div>

                        <div class="mb-3">

                            <label for="cantidad" class="form-label">
                                Cantidad actual
                            </label>

                            <input type="number" name="cantidad" id="cantidad" class="form-control"
                                value="{{ old('cantidad', $inventario->cantidad) }}" min="0" required>

                        </div>

                        <div class="mb-3">

                            <label for="stock_minimo" class="form-label">
                                Stock mínimo
                            </label>

                            <input type="number" name="stock_minimo" id="stock_minimo" class="form-control"
                                value="{{ old('stock_minimo', $inventario->stock_minimo) }}" min="0" required>

                        </div>

                        <div class="mb-3">

                            <label for="stock_maximo" class="form-label">
                                Stock máximo
                            </label>

                            <input type="number" name="stock_maximo" id="stock_maximo" class="form-control"
                                value="{{ old('stock_maximo', $inventario->stock_maximo) }}" min="0">

                            <small class="text-muted">
                                Debe ser mayor o igual al stock mínimo.
                            </small>

                        </div>

                        <div class="mb-3">

                            <label for="ubicacion" class="form-label">
                                Ubicación
                            </label>

                            <input type="text" name="ubicacion" id="ubicacion" class="form-control"
                                value="{{ old('ubicacion', $inventario->ubicacion) }}" maxlength="100">

                        </div>

                        <div class="form-check mb-3">

                            <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                                {{ old('estado', $inventario->estado) ? 'checked' : '' }}>

                            <label class="form-check-label" for="estado">
                                Activo
                            </label>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary">
                                Actualizar
                            </button>

                            <a href="{{ route('inventarios.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
