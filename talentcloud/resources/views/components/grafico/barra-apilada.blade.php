{{--
    Una barra apilada horizontal (parte de un todo) con su leyenda siempre visible: cada fila de la leyenda lleva el
    color, el nombre, la cantidad y el porcentaje, así que ni el color ni el dibujo son el único lugar del dato.
    Las categorías tienen un orden natural (p. ej. los planes de menor a mayor), por eso usan la rampa ordinal de un
    solo tono, de claro a oscuro. Las de valor 0 no ocupan barra pero sí aparecen en la leyenda.

    items: lista de ['clave', 'etiqueta', 'valor' (entero ≥ 0)], de la categoría menor a la mayor.
--}}
@props(['items', 'rotulo' => 'Distribución'])

@php
    $total = array_sum(array_map(fn (array $i) => (int) $i['valor'], $items));
    $n = count($items);
    // Pasos de la rampa (1 = el más claro, 5 = el más oscuro) repartidos según cuántas categorías haya.
    $pasos = match (true) {
        $n <= 1 => [3],
        $n === 2 => [1, 5],
        $n === 3 => [1, 3, 5],
        $n === 4 => [1, 2, 4, 5],
        default => array_slice([1, 2, 3, 4, 5], 0, 5),
    };
    $porcentaje = fn (int $v): int => $total > 0 ? (int) round($v * 100 / $total) : 0;
@endphp

<div {{ $attributes->class('viz apilada') }}>
    @if ($total > 0)
        <div class="apilada-barra" aria-hidden="true">
            @foreach ($items as $i => $item)
                @if ((int) $item['valor'] > 0)
                    <span class="apilada-segmento" style="flex: {{ (int) $item['valor'] }} 1 0; background: var(--viz-ord-{{ $pasos[$i] ?? 5 }})"></span>
                @endif
            @endforeach
        </div>
    @endif

    <ul class="apilada-leyenda" aria-label="{{ $rotulo }}">
        @foreach ($items as $i => $item)
            <li wire:key="apilada-{{ $item['clave'] }}">
                <span class="apilada-muestra" style="background: var(--viz-ord-{{ $pasos[$i] ?? 5 }})" aria-hidden="true"></span>
                <span class="apilada-nombre">{{ $item['etiqueta'] }}</span>
                <span class="apilada-cifra">{{ (int) $item['valor'] }}</span>
                <span class="apilada-parte">{{ $porcentaje((int) $item['valor']) }} %</span>
            </li>
        @endforeach
    </ul>
</div>
