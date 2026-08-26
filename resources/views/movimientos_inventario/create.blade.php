@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Nuevo Movimiento de Inventario
        </h1>

        <p class="text-muted mb-0">
            Registra una entrada, salida o ajuste sobre las existencias de un producto.
        </p>
    </div>

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            @if ($errors->any())
                <div class="alert alert-danger">

                    <strong>Corrige los siguientes errores:</strong>

                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>
            @endif

            <form action="{{ route('movimientos_inventario.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="inventario_id" class="form-label fw-semibold">
                            Producto
                        </label>

                        <select name="inventario_id" id="inventario_id" class="form-select" required>

                            <option value="">
                                Seleccione un producto
                            </option>

                            @foreach ($inventarios as $inventario)
                                <option value="{{ $inventario->id }}"
                                    {{ old('inventario_id') == $inventario->id ? 'selected' : '' }}>

                                    {{ $inventario->producto->nombre ?? 'Sin producto' }}
                                    -
                                    {{ $inventario->producto->codigo ?? 'Sin código' }}
                                    -
                                    Stock: {{ $inventario->cantidad }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="tipo_movimiento" class="form-label fw-semibold">
                            Tipo de Movimiento
                        </label>

                        <select name="tipo_movimiento" id="tipo_movimiento" class="form-select" required>

                            <option value="">
                                Seleccione una opción
                            </option>

                            <option value="ENTRADA" {{ old('tipo_movimiento') === 'ENTRADA' ? 'selected' : '' }}>
                                Entrada
                            </option>

                            <option value="SALIDA" {{ old('tipo_movimiento') === 'SALIDA' ? 'selected' : '' }}>
                                Salida
                            </option>

                            <option value="AJUSTE" {{ old('tipo_movimiento') === 'AJUSTE' ? 'selected' : '' }}>
                                Ajuste
                            </option>

                        </select>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="cantidad" class="form-label fw-semibold">
                            Cantidad
                        </label>

                        <input type="number" name="cantidad" id="cantidad" class="form-control"
                            value="{{ old('cantidad') }}" required>

                        <small id="ayuda-cantidad" class="text-muted">
                            Seleccione primero el tipo de movimiento.
                        </small>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="fecha_movimiento" class="form-label fw-semibold">
                            Fecha del Movimiento
                        </label>

                        <input type="datetime-local" name="fecha_movimiento" id="fecha_movimiento" class="form-control"
                            value="{{ old('fecha_movimiento') }}" required>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="motivo" class="form-label fw-semibold">
                        Motivo
                    </label>

                    <input type="text" name="motivo" id="motivo" class="form-control" value="{{ old('motivo') }}"
                        maxlength="150" placeholder="Ej. Compra de mercadería">

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4"
                        placeholder="Información adicional del movimiento">{{ old('observacion') }}</textarea>

                </div>

                <div class="form-check mb-4">

                    <input class="form-check-input" type="checkbox" name="estado" id="estado" value="1"
                        {{ old('estado', true) ? 'checked' : '' }}>

                    <label class="form-check-label" for="estado">
                        Activo
                    </label>

                </div>

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Guardar
                    </button>

                    <a href="{{ route('movimientos_inventario.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const tipo = document.getElementById('tipo_movimiento');
            const ayuda = document.getElementById('ayuda-cantidad');

            function actualizarAyuda() {

                if (tipo.value === 'ENTRADA') {
                    ayuda.textContent =
                        'Ingrese una cantidad positiva. Se sumará al inventario.';
                } else if (tipo.value === 'SALIDA') {
                    ayuda.textContent =
                        'Ingrese una cantidad positiva. Se restará del inventario.';
                } else if (tipo.value === 'AJUSTE') {
                    ayuda.textContent =
                        'Use un número positivo para aumentar o negativo para disminuir el inventario.';
                } else {
                    ayuda.textContent =
                        'Seleccione primero el tipo de movimiento.';
                }
            }

            tipo.addEventListener('change', actualizarAyuda);

            actualizarAyuda();
        });
    </script>

@endsection
