{{--
    Radar de coincidencia por requisito (3 a 10 ejes): cuánto respalda el CV cada competencia de un vistazo.
    puntos: lista de ['etiqueta' => nombre, 'valor' => 0–100]. Es un resumen visual; la tabla de al lado
    tiene los mismos datos (por eso el dibujo es una imagen con descripción y no el único lugar del dato).
--}}
@props(['puntos'])

@php
    $n = count($puntos);
    $centro = 130;
    $radio = 78;
    $punto = fn (int $i, float $escala): array => [
        round($centro + $radio * $escala * sin(2 * M_PI * $i / $n), 1),
        round($centro - $radio * $escala * cos(2 * M_PI * $i / $n), 1),
    ];
    $poligono = fn (float $escala): string => collect(range(0, $n - 1))->map(fn (int $i) => implode(',', $punto($i, $escala)))->implode(' ');
    $descripcion = collect($puntos)->map(fn (array $p) => $p['etiqueta'].' '.(int) $p['valor'].' %')->implode(', ');
@endphp

@if ($n >= 3)
    <svg viewBox="-60 0 380 260" {{ $attributes->class('h-auto w-full max-w-[340px]') }} role="img" aria-label="Coincidencia por requisito: {{ $descripcion }}">
        {{-- Anillos de referencia al 25, 50, 75 y 100 % --}}
        @foreach ([0.25, 0.5, 0.75, 1.0] as $escala)
            <polygon points="{{ $poligono($escala) }}" fill="none" class="stroke-neutral-300" stroke-width="1" />
        @endforeach
        @foreach (range(0, $n - 1) as $i)
            @php($borde = $punto($i, 1.0))
            <line x1="{{ $centro }}" y1="{{ $centro }}" x2="{{ $borde[0] }}" y2="{{ $borde[1] }}" class="stroke-neutral-300" stroke-width="1" />
        @endforeach

        {{-- Lo que respalda el CV --}}
        <polygon
            points="{{ collect($puntos)->map(fn (array $p, int $i) => implode(',', $punto($i, max(0.03, min(1, $p['valor'] / 100)))))->implode(' ') }}"
            class="fill-accent-100 stroke-accent-700" stroke-width="2" stroke-linejoin="round"
        />
        @foreach ($puntos as $i => $p)
            @php($marca = $punto($i, max(0.03, min(1, $p['valor'] / 100))))
            <circle cx="{{ $marca[0] }}" cy="{{ $marca[1] }}" r="3.5" class="fill-accent-700" />
            @php($rotulo = $punto($i, 1.2))
            <text x="{{ $rotulo[0] }}" y="{{ $rotulo[1] }}" font-size="10" class="fill-neutral-800" text-anchor="{{ $rotulo[0] < $centro - 6 ? 'end' : ($rotulo[0] > $centro + 6 ? 'start' : 'middle') }}" dominant-baseline="middle">{{ \Illuminate\Support\Str::limit($p['etiqueta'], 16, '…') }}</text>
        @endforeach
    </svg>
@endif
