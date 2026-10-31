@php
    $nombreRuta = request()->route()?->getName();
    $prefijo = $nombreRuta ? \Illuminate\Support\Str::beforeLast($nombreRuta, '.') : null;
    $seccionActual = $prefijo ? \App\Models\Opcion::with('modulo')
        ->where('ruta', $prefijo)->where('estado', true)
        ->whereHas('modulo', fn ($consulta) => $consulta->where('estado', true))->first() : null;
@endphp
@if ($seccionActual)
    <nav aria-label="Ubicación actual">
        <ol class="breadcrumb small mb-3">
            <li class="breadcrumb-item">{{ $seccionActual->modulo->nombre }}</li>
            <li class="breadcrumb-item active" aria-current="page">{{ $seccionActual->nombre }}</li>
        </ol>
    </nav>
@endif
