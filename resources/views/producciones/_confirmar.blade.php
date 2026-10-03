@if ($produccion->esEditable() && $produccion->estado)
    @can('producciones.modificar')
        <form method="POST" action="{{ route('producciones.confirmar', $produccion->id) }}"
            onsubmit="return confirm('¿Confirmar esta producción? Se descontarán las materias primas del inventario y se ingresará el producto terminado. La producción ya no podrá editarse.')">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-success {{ $claseConfirmar ?? '' }}">Confirmar producción</button>
        </form>
    @endcan
@endif
