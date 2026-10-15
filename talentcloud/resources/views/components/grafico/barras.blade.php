{{--
    Barras horizontales de una sola serie, con énfasis: la mayor va en el color de acento y las demás en gris, para que
    el ojo vaya directo al dato que cuenta (p. ej. la etapa más lenta). Barras finas, con la punta redondeada y la base
    recta; el valor siempre va en texto al final, así que el dibujo nunca es el único lugar del dato.

    items: lista de ['clave', 'etiqueta', 'valor' (número ≥ 0, mide la barra), 'texto' (el valor ya formateado),
           'nota' (opcional, texto secundario bajo la etiqueta)].
    resaltar: si se marca la barra mayor (por defecto, sí).
--}}
@props(['items', 'rotulo' => 'Barras', 'resaltar' => true])

@php
    $maximo = max(0.0001, ...array_map(fn (array $i) => (float) $i['valor'], $items));
    $indiceMayor = null;
    foreach ($items as $i => $item) {
        if ($indiceMayor === null || (float) $item['valor'] > (float) $items[$indiceMayor]['valor']) {
            $indiceMayor = $i;
        }
    }
@endphp

<ul {{ $attributes->class('viz barras') }} aria-label="{{ $rotulo }}">
    @foreach ($items as $i => $item)
        <li class="barras-fila" wire:key="barras-{{ $item['clave'] }}">
            <span class="barras-etiqueta">
                {{ $item['etiqueta'] }}
                @if (! empty($item['nota']))<small>{{ $item['nota'] }}</small>@endif
            </span>
            <span class="barras-pista" aria-hidden="true">
                <span @class(['barras-barra', 'barras-barra-mayor' => $resaltar && $i === $indiceMayor]) style="width: {{ max(2, round((float) $item['valor'] / $maximo * 100, 1)) }}%"></span>
            </span>
            <span class="barras-valor">{{ $item['texto'] }}</span>
        </li>
    @endforeach
</ul>
