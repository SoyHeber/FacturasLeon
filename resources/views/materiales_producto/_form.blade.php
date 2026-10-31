@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Corrige los siguientes errores:</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@php
    $edicion = isset($producto);
    $baseLineas = $edicion ? $materialesActuales->map(fn ($material) => [
        'id' => $material->id, 'inventario_compra_id' => $material->inventario_compra_id,
        'cantidad_requerida' => $material->getRawOriginal('cantidad_requerida'),
        'observacion' => $material->observacion,
    ])->all() : [[]];
    $maximoMateriales = max(200, count($baseLineas));
    $lineas = old('materiales', $baseLineas);
    $lineas = is_array($lineas) ? array_slice(array_values(array_filter($lineas, 'is_array')), 0, $maximoMateriales) : [];
    if ($lineas === []) {
        $lineas = $edicion ? $baseLineas : [[]];
    }
    $productoSeleccionado = old('producto_id', request('producto_id', ''));
    $productoSeleccionado = is_scalar($productoSeleccionado) ? $productoSeleccionado : '';
    $huellaFormulario = old('huella_receta', $huellaReceta ?? '');
    $huellaFormulario = is_string($huellaFormulario) ? $huellaFormulario : '';
@endphp
<div class="mb-4">
    <label for="producto_id" class="form-label fw-semibold">Producto</label>
    @if ($edicion)
        <input type="hidden" name="producto_id" value="{{ $producto->id }}">
        <input id="producto_id" class="form-control" value="{{ $producto->nombre }}{{ $producto->estado ? '' : ' · Inactivo' }}" readonly>
    @else
        <select name="producto_id" id="producto_id" class="form-select" required>
            <option value="">Seleccione un producto</option>
            @foreach ($productos as $opcionProducto)
                <option value="{{ $opcionProducto->id }}" @selected($productoSeleccionado == $opcionProducto->id)>{{ $opcionProducto->nombre }}</option>
            @endforeach
        </select>
    @endif
</div>
<div class="d-flex justify-content-between align-items-center gap-2 mb-2">
    <h2 class="h5 fw-bold mb-0">Materias primas</h2>
    @can($edicion ? 'materiales_producto.modificar' : 'materiales_producto.crear')
        <button type="button" id="agregar-material" class="btn btn-outline-warning btn-sm">+ Agregar material</button>
    @endcan
</div>
<p class="text-muted">Indica la cantidad por una unidad del producto, con punto decimal y hasta 10 decimales. Cada material puede aparecer una sola vez en la receta.</p>
<p class="text-muted">Busca por nombre para localizar una materia prima entre todos los materiales activos. Borra la búsqueda para volver a mostrar la lista completa.</p>
@if ($edicion)
    <p>Estado de la receta: <span class="badge {{ $producto->receta_activa ? 'bg-success' : 'bg-secondary' }}">{{ $producto->receta_activa ? 'Activa' : 'Inactiva' }}</span></p>
    <p class="text-muted">Para sustituir un componente, quita su fila y agrega el nuevo material. Los componentes retirados conservan su historial. El estado de toda la receta se gestiona desde Ver receta o el listado.</p>
@endif
<div class="table-responsive mb-4">
    <table class="table align-middle mb-0">
        <thead class="table-dark">
            <tr><th>Línea</th><th>Materia prima</th><th>Unidad</th><th>Cantidad por unidad del producto</th><th>Observación</th><th>Acciones</th></tr>
        </thead>
        <tbody id="lineas-materiales" data-maximo="{{ $maximoMateriales }}">
            @foreach ($lineas as $indice => $linea)
                @include('materiales_producto._linea', compact('indice', 'linea', 'edicion'))
            @endforeach
        </tbody>
    </table>
</div>
<template id="plantilla-material">
    @include('materiales_producto._linea', ['indice' => '__INDICE__', 'linea' => [], 'edicion' => $edicion])
</template>
@if ($edicion)
    {{-- Al final del lote: una solicitud truncada no debe retirar componentes omitidos. --}}
    <input type="hidden" name="huella_receta" value="{{ $huellaFormulario }}">
    @if ($producto->materialesProducto->where('estado', false)->isNotEmpty())
        <details class="mb-4">
            <summary>Componentes retirados de la receta</summary>
            <ul class="mt-2 mb-0">
                @foreach ($producto->materialesProducto->where('estado', false) as $retirado)
                    <li>{{ $retirado->inventarioCompra?->nombre ?? 'Material no disponible' }}: {{ $retirado->getRawOriginal('cantidad_requerida') }} {{ $retirado->inventarioCompra?->unidad_medida }} · {{ $retirado->observacion ?: 'Sin observación' }}</li>
                @endforeach
            </ul>
        </details>
    @endif
@endif
<div class="d-flex gap-2">
    @can($edicion ? 'materiales_producto.modificar' : 'materiales_producto.crear')
        <button type="submit" class="btn btn-warning fw-bold px-4">{{ $edicion ? 'Guardar receta' : 'Guardar materiales' }}</button>
    @endcan
    <a href="{{ $edicion ? route('materiales_producto.show', $materialProducto->id) : route('materiales_producto.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>

@push('scripts')
<script>
    (() => {
        const cuerpo = document.getElementById('lineas-materiales');
        const agregar = document.getElementById('agregar-material');
        const limite = Number(cuerpo.dataset.maximo);
        const normalizarBusqueda = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');

        /**
         * Filtra por nombre las opciones ya cargadas, sin incluir la unidad en la búsqueda.
         * No borra opciones ni cambia la selección de la fila.
         * Conservar la opción seleccionada evita perder un material mientras se busca otro.
         * Al limpiar la búsqueda se recupera el catálogo completo de materiales activos.
         */
        const filtrarMateriales = buscador => {
            const fila = buscador.closest('tr');
            const selector = fila.querySelector('select[data-campo="inventario_compra_id"]');
            const consulta = normalizarBusqueda(buscador.value.trim());
            let coincidencias = 0;
            Array.from(selector.options).forEach(opcion => {
                const coincide = opcion.value !== '' && normalizarBusqueda(opcion.dataset.nombre).includes(consulta);
                opcion.hidden = opcion.value !== '' && !coincide && !opcion.selected;
                if (coincide) coincidencias++;
            });
            const total = selector.options.length - 1;
            fila.querySelector('[data-total-materiales]').textContent = consulta
                ? coincidencias + ' de ' + total + ' materiales coinciden'
                : total + ' materiales activos disponibles';
        };
        const actualizar = () => {
            const seleccionados = new Set();
            cuerpo.querySelectorAll('tr').forEach((fila, indice) => {
                fila.querySelector('[data-linea]').textContent = String(indice + 1);
                fila.querySelectorAll('[data-campo]').forEach(campo => {
                    campo.name = 'materiales[' + indice + '][' + campo.dataset.campo + ']';
                });
                const material = fila.querySelector('[data-campo="inventario_compra_id"]');
                material.setCustomValidity(material.value && seleccionados.has(material.value)
                    ? 'Este material se repite en la receta. Seleccione otro material.' : '');
                if (material.value) seleccionados.add(material.value);
                fila.querySelector('td[data-unidad]').textContent = material.selectedOptions?.[0]?.dataset.unidad || material.dataset.unidad || '—';
                const buscador = fila.querySelector('[data-buscar-material]');
                if (buscador) filtrarMateriales(buscador);
                const quitar = fila.querySelector('[data-quitar]');
                if (quitar) quitar.disabled = cuerpo.children.length <= 1;
            });
            if (agregar) agregar.disabled = cuerpo.children.length >= limite;
        };
        agregar?.addEventListener('click', () => {
            if (cuerpo.children.length >= limite) return;
            cuerpo.appendChild(document.getElementById('plantilla-material').content.cloneNode(true));
            actualizar();
        });
        cuerpo.addEventListener('click', evento => {
            const quitar = evento.target.closest('[data-quitar]');
            if (quitar && cuerpo.children.length > 1) {
                quitar.closest('tr').remove();
                actualizar();
            }
        });
        cuerpo.addEventListener('change', evento => {
            if (evento.target.matches('[data-campo="inventario_compra_id"]')) actualizar();
        });
        cuerpo.addEventListener('input', evento => {
            if (evento.target.matches('[data-buscar-material]')) filtrarMateriales(evento.target);
        });
        cuerpo.addEventListener('keydown', evento => {
            if (evento.key === 'Enter' && evento.target.matches('[data-buscar-material]')) evento.preventDefault();
        });
        actualizar();
    })();
</script>
@endpush
