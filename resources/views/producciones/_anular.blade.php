@if (in_array($produccion->estado_produccion, ['BORRADOR', 'CONFIRMADA'], true))
    @can('producciones.eliminar')
        <form method="POST" action="{{ route('producciones.anular', $produccion->id) }}"
            onsubmit="return confirm('{{ $produccion->estado_produccion === 'BORRADOR' ? '¿Cancelar esta producción? Quedará ANULADA sin afectar inventario.' : '¿Anular esta producción? Se devolverán al inventario las materias primas consumidas y quedará ANULADA.' }}')">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-danger {{ $claseAnular ?? '' }}">
                {{ $produccion->estado_produccion === 'BORRADOR' ? 'Cancelar producción' : 'Anular producción' }}
            </button>
        </form>
    @endcan
@endif
