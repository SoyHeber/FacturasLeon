@extends('layouts.app-bootstrap')

@section('content')

    <div class="mb-4">
        <h1 class="fw-bold mb-1">
            Nueva Compra
        </h1>

        <p class="text-muted mb-0">
            Registra el encabezado, detalle y totales del DTE de compra.
        </p>
    </div>

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

    <form action="{{ route('compras.store') }}" method="POST" id="formCompra">

        @csrf

        {{-- ====================================================== --}}
        {{-- ENCABEZADO --}}
        {{-- ====================================================== --}}

        <div class="card shadow-sm border-0 rounded-4 mb-4">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-1">
                            Encabezado
                        </h5>

                        <p class="text-muted mb-0">
                            Información general de la compra y del proveedor.
                        </p>
                    </div>
                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="proveedor_id" class="form-label fw-semibold">
                            Proveedor
                        </label>

                        <select name="proveedor_id" id="proveedor_id" class="form-select" required>

                            <option value="">
                                Seleccione un proveedor
                            </option>

                            @foreach ($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" data-nombre="{{ $proveedor->nombre }}"
                                    data-identificacion="{{ $proveedor->numero_identificacion }}"
                                    data-telefono="{{ $proveedor->telefono }}" data-correo="{{ $proveedor->correo }}"
                                    data-direccion="{{ optional($proveedor->direccion)->direccion }}"
                                    {{ old('proveedor_id') == $proveedor->id ? 'selected' : '' }}>

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

                            <option value="">
                                Seleccione un tipo de DTE
                            </option>

                            <option value="FACT" {{ old('tipo_dte') === 'FACT' ? 'selected' : '' }}>
                                FACT - Factura
                            </option>

                        </select>

                    </div>

                </div>

                {{-- Datos del proveedor --}}
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

                        <label for="serie" class="form-label fw-semibold">
                            Serie
                        </label>

                        <input type="text" name="serie" id="serie" class="form-control"
                            value="{{ old('serie') }}" maxlength="20" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="numero" class="form-label fw-semibold">
                            Número
                        </label>

                        <input type="number" name="numero" id="numero" class="form-control"
                            value="{{ old('numero') }}" min="0" required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label for="moneda" class="form-label fw-semibold">
                            Moneda
                        </label>

                        <select name="moneda" id="moneda" class="form-select" required>

                            <option value="">
                                Seleccione una moneda
                            </option>

                            <option value="GTQ" {{ old('moneda', 'GTQ') === 'GTQ' ? 'selected' : '' }}>
                                GTQ - Quetzales
                            </option>

                            <option value="USD" {{ old('moneda') === 'USD' ? 'selected' : '' }}>
                                USD - Dólares
                            </option>

                        </select>

                    </div>

                </div>

                <div class="mb-3">

                    <label for="numero_autorizacion" class="form-label fw-semibold">
                        Número de Autorización / UUID
                    </label>

                    <input type="text" name="numero_autorizacion" id="numero_autorizacion" class="form-control"
                        value="{{ old('numero_autorizacion') }}" maxlength="36"
                        placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" required>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="fecha_emision" class="form-label fw-semibold">
                            Fecha de Emisión
                        </label>

                        <input type="datetime-local" name="fecha_emision" id="fecha_emision" class="form-control"
                            value="{{ old('fecha_emision') }}" required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="fecha_certificacion" class="form-label fw-semibold">
                            Fecha de Certificación
                        </label>

                        <input type="datetime-local" name="fecha_certificacion" id="fecha_certificacion"
                            class="form-control" value="{{ old('fecha_certificacion') }}">

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
                            Agrega los materiales o insumos incluidos en la compra.
                        </p>
                    </div>

                    <button type="button" class="btn btn-outline-dark" id="btnAgregarLinea">
                        + Agregar línea
                    </button>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle" id="tablaDetalles">

                        <thead class="table-dark">

                            <tr>
                                <th style="width: 70px;">
                                    Línea
                                </th>

                                <th style="min-width: 200px;">
                                    Material
                                </th>

                                <th style="min-width: 110px;">
                                    Cantidad
                                </th>

                                <th style="min-width: 130px;">
                                    Precio Unit.
                                </th>

                                <th style="min-width: 110px;">
                                    % Desc.
                                </th>

                                <th style="min-width: 130px;">
                                    Bruto
                                </th>

                                <th style="min-width: 130px;">
                                    Descuento
                                </th>

                                <th style="min-width: 130px;">
                                    Exento
                                </th>

                                <th style="min-width: 130px;">
                                    Otros
                                </th>

                                <th style="min-width: 130px;">
                                    Neto
                                </th>

                                <th style="min-width: 130px;">
                                    IVA
                                </th>

                                <th style="min-width: 130px;">
                                    Total
                                </th>

                                <th style="min-width: 180px;">
                                    Observación
                                </th>

                                <th style="width: 80px;">
                                    Acción
                                </th>
                            </tr>

                        </thead>

                        <tbody id="detalleBody">
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

                        <label for="observacion" class="form-label fw-semibold">
                            Observación general
                        </label>

                        <textarea name="observacion" id="observacion" class="form-control" rows="5">{{ old('observacion') }}</textarea>

                        <div class="form-check mt-3">

                            <input class="form-check-input" type="checkbox" name="estado" id="estado"
                                value="1" {{ old('estado', true) ? 'checked' : '' }}>

                            <label class="form-check-label" for="estado">
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
                                <strong id="totalBruto">
                                    0.00
                                </strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Descuento</span>
                                <strong id="totalDescuento">
                                    0.00
                                </strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Exento</span>
                                <strong id="totalExento">
                                    0.00
                                </strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Otros</span>
                                <strong id="totalOtros">
                                    0.00
                                </strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Neto</span>
                                <strong id="totalNeto">
                                    0.00
                                </strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>IVA</span>
                                <strong id="totalIva">
                                    0.00
                                </strong>
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


        {{-- BOTONES --}}
        <div class="d-flex gap-2">

            <button type="submit" class="btn btn-warning fw-bold px-4">
                Guardar Compra
            </button>

            <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">
                Cancelar
            </a>

        </div>

    </form>


    {{-- ====================================================== --}}
    {{-- TEMPLATE PARA DETALLES --}}
    {{-- ====================================================== --}}

    <template id="templateDetalle">

        <tr class="detalle-row">

            <td class="numero-linea fw-semibold text-center">
            </td>

            <td>

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
                <input type="number" class="form-control cantidad" step="0.00001" min="0.00001" value="1.00000"
                    required>
            </td>

            <td>
                <input type="number" class="form-control precio-unitario" step="0.000001" min="0"
                    value="0.000000" required>
            </td>

            <td>
                <input type="number" class="form-control porcentaje-descuento" step="0.0001" min="0"
                    max="100" value="0.0000" required>
            </td>

            <td>
                <input type="number" class="form-control importe-bruto" step="0.01" min="0" value="0.00"
                    readonly>
            </td>

            <td>
                <input type="number" class="form-control importe-descuento" step="0.01" min="0"
                    value="0.00" readonly>
            </td>

            <td>
                <input type="number" class="form-control importe-exento" step="0.01" min="0" value="0.00"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-otros" step="0.01" min="0" value="0.00"
                    required>
            </td>

            <td>
                <input type="number" class="form-control importe-neto" step="0.01" min="0" value="0.00"
                    readonly>
            </td>

            <td>
                <input type="number" class="form-control importe-iva" step="0.01" min="0" value="0.00"
                    readonly>
            </td>

            <td>
                <input type="number" class="form-control importe-total" step="0.01" min="0" value="0.00"
                    readonly>
            </td>

            <td>
                <input type="text" class="form-control detalle-observacion" maxlength="255">
            </td>

            <td class="text-center">

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


            /*
            |--------------------------------------------------------------------------
            | FUNCIONES NUMÉRICAS
            |--------------------------------------------------------------------------
            */

            function numero(valor) {

                const resultado =
                    parseFloat(valor);

                return isNaN(resultado) ?
                    0 :
                    resultado;
            }


            function redondear(valor, decimales = 2) {

                const factor =
                    Math.pow(
                        10,
                        decimales
                    );

                return Math.round(
                    (
                        Number(valor) +
                        Number.EPSILON
                    ) * factor
                ) / factor;
            }


            /*
            |--------------------------------------------------------------------------
            | CÁLCULO DE UNA LÍNEA
            |--------------------------------------------------------------------------
            |
            | Reglas utilizadas según Ainnova:
            |
            | Bruto = Cantidad × Precio
            |
            | Descuento =
            | Bruto × (% Descuento / 100)
            |
            | Base =
            | Bruto - Descuento - Exento - Otros
            |
            | Neto = Base / 1.12
            |
            | IVA = Base - Neto
            |
            | Total =
            | Neto + IVA + Exento + Otros
            |
            */

            function calcularLinea(fila) {

                const cantidad =
                    numero(
                        fila.querySelector(
                            '.cantidad'
                        ).value
                    );

                const precio =
                    numero(
                        fila.querySelector(
                            '.precio-unitario'
                        ).value
                    );

                const porcentajeDescuento =
                    numero(
                        fila.querySelector(
                            '.porcentaje-descuento'
                        ).value
                    );

                const exento =
                    numero(
                        fila.querySelector(
                            '.importe-exento'
                        ).value
                    );

                const otros =
                    numero(
                        fila.querySelector(
                            '.importe-otros'
                        ).value
                    );


                /*
                |--------------------------------------------------------------------------
                | BRUTO
                |--------------------------------------------------------------------------
                */

                const bruto =
                    redondear(
                        cantidad * precio,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | DESCUENTO
                |--------------------------------------------------------------------------
                */

                const descuento =
                    redondear(
                        bruto *
                        (
                            porcentajeDescuento /
                            100
                        ),
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | BASE AFECTA
                |--------------------------------------------------------------------------
                */

                let baseAfecta =
                    redondear(
                        bruto -
                        descuento -
                        exento -
                        otros,
                        2
                    );


                if (baseAfecta < 0) {
                    baseAfecta = 0;
                }


                /*
                |--------------------------------------------------------------------------
                | NETO
                |--------------------------------------------------------------------------
                */

                const neto =
                    redondear(
                        baseAfecta / 1.12,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | IVA
                |--------------------------------------------------------------------------
                */

                const iva =
                    redondear(
                        baseAfecta - neto,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | TOTAL
                |--------------------------------------------------------------------------
                */

                const total =
                    redondear(
                        neto +
                        iva +
                        exento +
                        otros,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | ASIGNAR RESULTADOS
                |--------------------------------------------------------------------------
                */

                fila.querySelector(
                        '.importe-bruto'
                    ).value =
                    bruto.toFixed(2);

                fila.querySelector(
                        '.importe-descuento'
                    ).value =
                    descuento.toFixed(2);

                fila.querySelector(
                        '.importe-neto'
                    ).value =
                    neto.toFixed(2);

                fila.querySelector(
                        '.importe-iva'
                    ).value =
                    iva.toFixed(2);

                fila.querySelector(
                        '.importe-total'
                    ).value =
                    total.toFixed(2);
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
            | RENUMERAR LÍNEAS
            |--------------------------------------------------------------------------
            */

            function renumerarLineas() {

                const filas =
                    detalleBody.querySelectorAll(
                        '.detalle-row'
                    );

                filas.forEach(
                    (fila, index) => {

                        fila.querySelector(
                                '.numero-linea'
                            ).textContent =
                            index + 1;


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
                    }
                );
            }


            /*
            |--------------------------------------------------------------------------
            | AGREGAR LÍNEA
            |--------------------------------------------------------------------------
            */

            function agregarLinea() {

                const fragment =
                    templateDetalle.content.cloneNode(
                        true
                    );

                detalleBody.appendChild(
                    fragment
                );

                renumerarLineas();


                const filas =
                    detalleBody.querySelectorAll(
                        '.detalle-row'
                    );

                const ultimaFila =
                    filas[
                        filas.length - 1
                    ];


                calcularLinea(
                    ultimaFila
                );

                recalcularTotales();
            }


            /*
            |--------------------------------------------------------------------------
            | TOTALES GENERALES
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
                    .querySelectorAll(
                        '.detalle-row'
                    )
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

            proveedorSelect.addEventListener(
                'change',
                actualizarProveedor
            );


            btnAgregarLinea.addEventListener(
                'click',
                agregarLinea
            );


            detalleBody.addEventListener(
                'input',
                function(event) {

                    const fila =
                        event.target.closest(
                            '.detalle-row'
                        );

                    if (!fila) {
                        return;
                    }


                    const campoCalculable =

                        event.target.classList.contains(
                            'cantidad'
                        ) ||

                        event.target.classList.contains(
                            'precio-unitario'
                        ) ||

                        event.target.classList.contains(
                            'porcentaje-descuento'
                        ) ||

                        event.target.classList.contains(
                            'importe-exento'
                        ) ||

                        event.target.classList.contains(
                            'importe-otros'
                        );


                    if (!campoCalculable) {
                        return;
                    }


                    calcularLinea(
                        fila
                    );

                    recalcularTotales();
                }
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
                            .closest(
                                '.detalle-row'
                            )
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

            agregarLinea();

            actualizarProveedor();

        });
    </script>
@endpush
