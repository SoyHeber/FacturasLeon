@if (in_array($produccion->estado_produccion, ['BORRADOR', 'CONFIRMADA'], true))
    @can('producciones.eliminar')
        <form method="POST" action="{{ route('producciones.anular', $produccion->id) }}"
            onsubmit="return confirm('{{ $produccion->estado_produccion === 'BORRADOR' ? '¿Cancelar esta producción? Quedará ANULADA sin afectar inventario.' : ($produccion->producto_terminado_aplicado ? '¿Anular esta producción? Se retirará el producto terminado y se devolverán las materias primas. Debe existir stock suficiente del producto terminado.' : '¿Anular esta producción? Se devolverán al inventario las materias primas consumidas y quedará ANULADA.') }}')">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-danger {{ $claseAnular ?? '' }}">
                {{ $produccion->estado_produccion === 'BORRADOR' ? 'Cancelar producción' : 'Anular producción' }}
            </button>
        </form>
    @endcan
@endif
