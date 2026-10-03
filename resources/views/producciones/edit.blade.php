@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Editar Producción
        </h1>

        <p class="text-muted mb-0">
            Modifica la información del registro de producción.
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

            <form action="{{ route('producciones.update', $produccion->id) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="producto_id" class="form-label fw-semibold">
                            Producto
                        </label>

                        <select name="producto_id" id="producto_id" class="form-select" required>

                            @foreach ($productos as $producto)
                                <option value="{{ $producto->id }}"
                                    {{ $datosFormulario['producto_id'] == $producto->id ? 'selected' : '' }}>
                                    {{ $producto->nombre }}
                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label for="cantidad" class="form-label fw-semibold">
                            Cantidad
                        </label>

                        <input type="number" name="cantidad" id="cantidad" class="form-control" min="1" max="4294967295" step="1"
                            value="{{ $datosFormulario['cantidad'] }}" required>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label for="fecha_produccion" class="form-label fw-semibold">
                            Fecha de producción
                        </label>

                        <input type="datetime-local" name="fecha_produccion" id="fecha_produccion" class="form-control"
                            value="{{ $datosFormulario['fecha_produccion'] }}"
                            required>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="observacion" class="form-label fw-semibold">
                        Observación
                    </label>

                    <textarea name="observacion" id="observacion" class="form-control" rows="4">{{ $datosFormulario['observacion'] }}</textarea>

                </div>

                <p><span class="badge bg-secondary">BORRADOR</span></p>

                @include('producciones._estimacion', ['rutaEstimacion' => route('producciones.edit', $produccion->id)])

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-warning fw-bold px-4">
                        Actualizar
                    </button>

                    <a href="{{ route('producciones.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

@endsection
