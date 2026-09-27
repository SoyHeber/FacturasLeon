@extends('layouts.app-bootstrap')

@section('content')

    @php
        $detallesIniciales = old('detalles');

        if ($detallesIniciales === null) {
            $detallesIniciales = $compra->detalles
                ->sortBy('numero_linea')
                ->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,
                        'inventario_compra_id' => $detalle->inventario_compra_id,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'porcentaje_descuento' => $detalle->porcentaje_descuento,
                        'importe_bruto' => $detalle->importe_bruto,
                        'importe_descuento' => $detalle->importe_descuento,
                        'importe_exento' => $detalle->importe_exento,
                        'importe_otros' => $detalle->importe_otros,
                        'importe_neto' => $detalle->importe_neto,
                        'importe_iva' => $detalle->importe_iva,
                        'importe_total' => $detalle->importe_total,
                        'observacion' => $detalle->observacion,
                    ];
                })
                ->values()
                ->toArray();
        }
    @endphp


    <div class="mb-4">

        <h1 class="fw-bold mb-1">
            Editar Compra
        </h1>

        <p class="text-muted mb-0">
            Modifica el encabezado, los detalles y los totales de la compra.
        </p>

    </div>


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


    <form action="{{ route('compras.update', $compra->id) }}" method="POST" id="formCompra">

        @csrf
        @method('PUT')


        {{-- ====================================================== --}}
        {{-- ENCABEZADO --}}
        {{-- ====================================================== --}}

        <div class="card shadow-sm border-0 rounded-4 mb-4">

            <div class="card-body p-4">

                <h5 class="fw-bold mb-1">
                    Encabezado
                </h5>

                <p class="text-muted mb-4">
                    Información general del DTE y proveedor.
                </p>


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="proveedor_id" class="form-label fw-semibold">
                            Proveedor
                        </label>

                        <select name="proveedor_id" id="proveedor_id" class="form-select" required>

                            @foreach ($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" data-nombre="{{ $proveedor->nombre }}"
                                    data-identificacion="{{ $proveedor->numero_identificacion }}"
                                    data-telefono="{{ $proveedor->telefono }}" data-correo="{{ $proveedor->correo }}"
                                    data-direccion="{{ optional($proveedor->direccion)->direccion }}"
                                    {{ old('proveedor_id', $compra->proveedor_id) == $proveedor->id ? 'selected' : '' }}>

                                    {{ $proveedor->nombre }}

                                </option>
                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label for="tipo_dte" class="form-label fw-semibold">
                            Tipo DTE
                        </label>

                        <select name="tipo_dte" id="tipo_dte" class="form-select" required>

                            <option value="FACT" {{ old('tipo_dte', $compra->tipo_dte) === 'FACT' ? 'selected' : '' }}>

                                FACT - Factura

                            </option>

                        </select>

                    </div>

                </div>


                {{-- DATOS PROVEEDOR --}}

                <div class="border rounded-3 p-3 mb-4 bg-light">

                    <h6 class="fw-bold mb-3">
                        Datos del proveedor
                    </h6>

                    <div class="row">

                        <div class="col-md-3 mb-2">

                            <span class="text-muted d-block small">
                                Nombre
                            </span>

                            <strong id="proveedor_nombre">
                                -
                            </strong>

                        </div>


                        <div class="col-md-3 mb-2">

                            <span class="text-muted d-block small">
                                Identificación
                            </span>

                            <strong id="proveedor_identificacion">
                                -
                            </strong>

                        </div>


                        <div class="col-md-3 mb-2">

                            <span class="text-muted d-block small">
                                Teléfono
                            </span>

                            <strong id="proveedor_telefono">
                                -
                            </strong>

                        </div>


                        <div class="col-md-3 mb-2">

                            <span class="text-muted d-block small">
                                Correo
                            </span>

                            <strong id="proveedor_correo">
                                -
                            </strong>

                        </div>


                        <div class="col-md-12 mt-2">

                            <span class="text-muted d-block small">
                                Dirección
                            </span>

                            <strong id="proveedor_direccion">
                                -
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Serie
                        </label>

                        <input type="text" name="serie" class="form-control" maxlength="20"
                            value="{{ old('serie', $compra->serie) }}" required>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Número
                        </label>

                        <input type="number" name="numero" class="form-control" min="0"
                            value="{{ old('numero', $compra->numero) }}" required>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Moneda
                        </label>

                        <select name="moneda" class="form-select" required>

                            <option value="GTQ" {{ old('moneda', $compra->moneda) === 'GTQ' ? 'selected' : '' }}>

                                GTQ - Quetzales

                            </option>

                            <option value="USD" {{ old('moneda', $compra->moneda) === 'USD' ? 'selected' : '' }}>

                                USD - Dólares

                            </option>

                        </select>

                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label fw-semibold">
                        Número de Autorización / UUID
                    </label>

                    <input type="text" name="numero_autorizacion" class="form-control" maxlength="36"
                        value="{{ old('numero_autorizacion', $compra->numero_autorizacion) }}" required>

                </div>


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Fecha de Emisión
                        </label>

                        <input type="datetime-local" name="fecha_emision" class="form-control"
                            value="{{ old('fecha_emision', $compra->fecha_emision?->format('Y-m-d\TH:i')) }}"
                            required>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Fecha de Certificación
                        </label>

                        <input type="datetime-local" name="fecha_certificacion" class="form-control"
                            value="{{ old('fecha_certificacion', $compra->fecha_certificacion?->format('Y-m-d\TH:i')) }}">

                    </div>

                </div>

            </div>

        </div>


        {{-- ====================================================== --}}
        {{-- DETALLE --}}
        {{-- ====================================================== --}}

        <div class="card shadow-sm border-0 rounded-4 mb-4">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Detalle
                        </h5>

                        <p class="text-muted mb-0">
                            Modifica, agrega o elimina las líneas de la compra.
                        </p>

                    </div>


                    <button type="button" class="btn btn-outline-dark" id="btnAgregarLinea">

                        + Agregar línea

                    </button>

                </div>


                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead class="table-dark">

                            <tr>

                                <th>Línea</th>
                                <th>Material</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>% Desc.</th>
                                <th>Bruto</th>
                                <th>Descuento</th>
                                <th>Exento</th>
                                <th>Otros</th>
                                <th>Neto</th>
                                <th>IVA</th>
                                <th>Total</th>
                                <th>Observación</th>
                                <th>Acción</th>

                            </tr>

                        </thead>


                        <tbody id="detalleBody">

                            @foreach ($detallesIniciales as $index => $detalle)
                                <tr class="detalle-row">

                                    <td class="numero-linea fw-semibold text-center">
                                        {{ $index + 1 }}
                                    </td>


                                    <td>

                                        <input type="hidden" class="detalle-id" value="{{ $detalle['id'] ?? '' }}">


                                        <select class="form-select inventario-compra" required>

                                            <option value="">
                                                Seleccione
                                            </option>

                                            @foreach ($inventariosCompra as $inventarioCompra)
                                                <option value="{{ $inventarioCompra->id }}"
                                                    {{ ($detalle['inventario_compra_id'] ?? '') == $inventarioCompra->id ? 'selected' : '' }}>

                                                    {{ $inventarioCompra->nombre }}
                                                    -
                                                    {{ $inventarioCompra->unidad_medida }}

                                                </option>
                                            @endforeach

                                        </select>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control cantidad" step="0.0001"
                                            min="0.0001" value="{{ $detalle['cantidad'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control precio-unitario" step="0.000001"
                                            min="0" value="{{ $detalle['precio_unitario'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control porcentaje-descuento" step="0.0001"
                                            min="0" max="100" value="{{ $detalle['porcentaje_descuento'] }}"
                                            required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-bruto" step="0.01"
                                            min="0" value="{{ $detalle['importe_bruto'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-descuento" step="0.01"
                                            min="0" value="{{ $detalle['importe_descuento'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-exento" step="0.01"
                                            min="0" value="{{ $detalle['importe_exento'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-otros" step="0.01"
                                            min="0" value="{{ $detalle['importe_otros'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-neto" step="0.01"
                                            min="0" value="{{ $detalle['importe_neto'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-iva" step="0.01"
                                            min="0" value="{{ $detalle['importe_iva'] }}" required>

                                    </td>


                                    <td>

                                        <input type="number" class="form-control importe-total" step="0.01"
                                            min="0" value="{{ $detalle['importe_total'] }}" required>

                                    </td>


                                    <td>

                                        <input type="text" class="form-control detalle-observacion"
                                            value="{{ $detalle['observacion'] ?? '' }}">

                                    </td>


                                    <td>

                                        <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar-linea">

                                            ×

                                        </button>

                                    </td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        {{-- ====================================================== --}}
        {{-- TOTALES --}}
        {{-- ====================================================== --}}

        <div class="card shadow-sm border-0 rounded-4 mb-4">

            <div class="card-body p-4">

                <div class="row">

                    <div class="col-lg-7">

                        <label class="form-label fw-semibold">
                            Observación general
                        </label>

                        <textarea name="observacion" class="form-control" rows="5">{{ old('observacion', $compra->observacion) }}</textarea>


                        <div class="form-check mt-3">

                            <input type="checkbox" class="form-check-input" name="estado" value="1"
                                {{ old('estado', $compra->estado) ? 'checked' : '' }}>

                            <label class="form-check-label">
                                Activo
                            </label>

                        </div>

                    </div>


                    <div class="col-lg-5">

                        <div class="border rounded-3 p-4 bg-light">

                            <h5 class="fw-bold mb-4">
                                Totales
                            </h5>


                            <div class="d-flex justify-content-between mb-2">
                                <span>Importe Bruto</span>
                                <strong id="totalBruto">0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Descuento</span>
                                <strong id="totalDescuento">0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Exento</span>
                                <strong id="totalExento">0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Otros</span>
                                <strong id="totalOtros">0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Neto</span>
                                <strong id="totalNeto">0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>IVA</span>
                                <strong id="totalIva">0.00</strong>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">

                                <span class="fw-bold fs-5">
                                    GRAN TOTAL
                                </span>

                                <strong class="fs-4" id="totalGeneral">
                                    0.00
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="d-flex gap-2">

            <button type="submit" class="btn btn-warning fw-bold px-4">

                Actualizar Compra

            </button>


            <a href="{{ route('compras.show', $compra->id) }}" class="btn btn-outline-secondary">

                Cancelar

            </a>

        </div>

    </form>


    {{-- TEMPLATE NUEVA LÍNEA --}}

    <template id="templateDetalle">

        <tr class="detalle-row">

            <td class="numero-linea fw-semibold text-center">
            </td>

            <td>

                <input type="hidden" class="detalle-id" value="">


                <select class="form-select inventario-compra" required>

                    <option value="">
                        Seleccione
                    </option>

                    @foreach ($inventariosCompra as $inventarioCompra)
                        <option value="{{ $inventarioCompra->id }}">

                            {{ $inventarioCompra->nombre }}
                            -
                            {{ $inventarioCompra->unidad_medida }}

                        </option>
                    @endforeach

                </select>

            </td>

            <td>
                <input type="number" class="form-control cantidad" step="0.0001" min="0.0001" value="1"
                    required>
            </td>

            <td>
                <input type="number" class="form-control precio-unitario" step="0.000001" min="0"
                    value="0" required>
            </td>

            <td>
                <input type="number" class="form-control porcentaje-descuento" step="0.0001" min="0"
                    max="100" value="0" required>
            </td>

            <td>
                <input type="number" class="form-control importe-bruto" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-descuento" step="0.01" min="0"
                    value="0" required>
            </td>

            <td>
                <input type="number" class="form-control importe-exento" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-otros" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-neto" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-iva" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-total" step="0.01" min="0" value="0"
                    required>
            </td>

            <td>
                <input type="text" class="form-control detalle-observacion">
            </td>

            <td>

                <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar-linea">

                    ×

                </button>

            </td>

        </tr>

    </template>

@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const detalleBody =
                document.getElementById('detalleBody');

            const templateDetalle =
                document.getElementById('templateDetalle');

            const btnAgregarLinea =
                document.getElementById('btnAgregarLinea');

            const proveedorSelect =
                document.getElementById('proveedor_id');


            function numero(valor) {

                const resultado = parseFloat(valor);

                return isNaN(resultado) ?
                    0 :
                    resultado;
            }


            /*
            |--------------------------------------------------------------------------
            | PROVEEDOR
            |--------------------------------------------------------------------------
            */

            function actualizarProveedor() {

                const option =
                    proveedorSelect.options[
                        proveedorSelect.selectedIndex
                    ];

                document.getElementById(
                        'proveedor_nombre'
                    ).textContent =
                    option.dataset.nombre || '-';

                document.getElementById(
                        'proveedor_identificacion'
                    ).textContent =
                    option.dataset.identificacion || '-';

                document.getElementById(
                        'proveedor_telefono'
                    ).textContent =
                    option.dataset.telefono || '-';

                document.getElementById(
                        'proveedor_correo'
                    ).textContent =
                    option.dataset.correo || '-';

                document.getElementById(
                        'proveedor_direccion'
                    ).textContent =
                    option.dataset.direccion || '-';
            }


            /*
            |--------------------------------------------------------------------------
            | NOMBRES Y NÚMEROS DE LÍNEA
            |--------------------------------------------------------------------------
            */

            function renumerarLineas() {

                const filas =
                    detalleBody.querySelectorAll(
                        '.detalle-row'
                    );

                filas.forEach((fila, index) => {

                    fila.querySelector(
                            '.numero-linea'
                        ).textContent =
                        index + 1;


                    fila.querySelector(
                            '.detalle-id'
                        ).name =
                        `detalles[${index}][id]`;


                    fila.querySelector(
                            '.inventario-compra'
                        ).name =
                        `detalles[${index}][inventario_compra_id]`;


                    fila.querySelector(
                            '.cantidad'
                        ).name =
                        `detalles[${index}][cantidad]`;


                    fila.querySelector(
                            '.precio-unitario'
                        ).name =
                        `detalles[${index}][precio_unitario]`;


                    fila.querySelector(
                            '.porcentaje-descuento'
                        ).name =
                        `detalles[${index}][porcentaje_descuento]`;


                    fila.querySelector(
                            '.importe-bruto'
                        ).name =
                        `detalles[${index}][importe_bruto]`;


                    fila.querySelector(
                            '.importe-descuento'
                        ).name =
                        `detalles[${index}][importe_descuento]`;


                    fila.querySelector(
                            '.importe-exento'
                        ).name =
                        `detalles[${index}][importe_exento]`;


                    fila.querySelector(
                            '.importe-otros'
                        ).name =
                        `detalles[${index}][importe_otros]`;


                    fila.querySelector(
                            '.importe-neto'
                        ).name =
                        `detalles[${index}][importe_neto]`;


                    fila.querySelector(
                            '.importe-iva'
                        ).name =
                        `detalles[${index}][importe_iva]`;


                    fila.querySelector(
                            '.importe-total'
                        ).name =
                        `detalles[${index}][importe_total]`;


                    fila.querySelector(
                            '.detalle-observacion'
                        ).name =
                        `detalles[${index}][observacion]`;
                });
            }


            function agregarLinea() {

                const fragment =
                    templateDetalle.content.cloneNode(
                        true
                    );

                detalleBody.appendChild(
                    fragment
                );

                renumerarLineas();

                recalcularTotales();
            }


            /*
            |--------------------------------------------------------------------------
            | TOTALES
            |--------------------------------------------------------------------------
            */

            function recalcularTotales() {

                let bruto = 0;
                let descuento = 0;
                let exento = 0;
                let otros = 0;
                let neto = 0;
                let iva = 0;
                let total = 0;


                detalleBody
                    .querySelectorAll('.detalle-row')
                    .forEach(fila => {

                        bruto += numero(
                            fila.querySelector(
                                '.importe-bruto'
                            ).value
                        );

                        descuento += numero(
                            fila.querySelector(
                                '.importe-descuento'
                            ).value
                        );

                        exento += numero(
                            fila.querySelector(
                                '.importe-exento'
                            ).value
                        );

                        otros += numero(
                            fila.querySelector(
                                '.importe-otros'
                            ).value
                        );

                        neto += numero(
                            fila.querySelector(
                                '.importe-neto'
                            ).value
                        );

                        iva += numero(
                            fila.querySelector(
                                '.importe-iva'
                            ).value
                        );

                        total += numero(
                            fila.querySelector(
                                '.importe-total'
                            ).value
                        );
                    });


                document.getElementById(
                        'totalBruto'
                    ).textContent =
                    bruto.toFixed(2);

                document.getElementById(
                        'totalDescuento'
                    ).textContent =
                    descuento.toFixed(2);

                document.getElementById(
                        'totalExento'
                    ).textContent =
                    exento.toFixed(2);

                document.getElementById(
                        'totalOtros'
                    ).textContent =
                    otros.toFixed(2);

                document.getElementById(
                        'totalNeto'
                    ).textContent =
                    neto.toFixed(2);

                document.getElementById(
                        'totalIva'
                    ).textContent =
                    iva.toFixed(2);

                document.getElementById(
                        'totalGeneral'
                    ).textContent =
                    total.toFixed(2);
            }


            /*
            |--------------------------------------------------------------------------
            | EVENTOS
            |--------------------------------------------------------------------------
            */

            btnAgregarLinea.addEventListener(
                'click',
                agregarLinea
            );


            proveedorSelect.addEventListener(
                'change',
                actualizarProveedor
            );


            detalleBody.addEventListener(
                'input',
                recalcularTotales
            );


            detalleBody.addEventListener(
                'click',
                function(event) {

                    if (
                        event.target.classList.contains(
                            'btn-eliminar-linea'
                        )
                    ) {

                        const filas =
                            detalleBody.querySelectorAll(
                                '.detalle-row'
                            );

                        if (filas.length <= 1) {

                            alert(
                                'La compra debe contener al menos una línea.'
                            );

                            return;
                        }

                        event.target
                            .closest('.detalle-row')
                            .remove();

                        renumerarLineas();

                        recalcularTotales();
                    }
                }
            );


            /*
            |--------------------------------------------------------------------------
            | INICIALIZACIÓN
            |--------------------------------------------------------------------------
            */

            renumerarLineas();

            recalcularTotales();

            actualizarProveedor();

        });
    </script>
@endpush
