{{--
    Gráfico de líneas en SVG, sin librerías: una serie principal (línea sólida con área) y, opcionalmente, una de
    comparación (línea punteada). Valores enteros desde 0. Al pasar el puntero o con las flechas del teclado muestra
    el valor de cada serie en ese punto; una tabla oculta lleva los mismos datos para lectores de pantalla.

    etiquetas: texto de cada punto del eje X ("13 sep", "13–15 sep").
    series: [['nombre' => 'Periodo actual', 'valores' => [...], 'principal' => true], ['nombre' => 'Periodo anterior', 'valores' => [...]]]
    descripcion: resumen en una frase para quien no ve el gráfico.
--}}
@props(['etiquetas', 'series', 'descripcion', 'unidad' => 'postulaciones', 'id' => 'grafico'])

@php
    $ancho = 640;
    $alto = 220;
    $izq = 34;
    $der = 12;
    $arriba = 12;
    $abajo = 26;
    $n = count($etiquetas);
    $maximo = max(1, ...array_merge(...array_map(fn (array $s) => $s['valores'], $series)));

    // Escala «agradable»: pasos de 1, 2, 5 o 10 (por la potencia de diez que corresponda), enteros y 4–5 líneas.
    $bruto = $maximo / 4;
    $magnitud = 10 ** floor(log10($bruto));
    $paso = 1;
    foreach ([1, 2, 5, 10] as $multiplo) {
        $paso = max(1, $multiplo * $magnitud);
        if ($paso >= $bruto) {
            break;
        }
    }
    $paso = (int) $paso;
    $tope = (int) (ceil($maximo / $paso) * $paso);

    $util = $ancho - $izq - $der;
    $x = fn (int $i): float => $izq + ($n > 1 ? $i * $util / ($n - 1) : $util / 2);
    $y = fn (int|float $v): float => $arriba + ($alto - $arriba - $abajo) * (1 - $v / $tope);
    $linea = fn (array $valores): string => collect($valores)->map(fn ($v, $i) => ($i === 0 ? 'M' : 'L').round($x($i), 1).' '.round($y($v), 1))->implode(' ');

    $marcasX = $n <= 5 ? range(0, $n - 1) : array_values(array_unique([0, (int) round(($n - 1) / 4), (int) round(($n - 1) / 2), (int) round(3 * ($n - 1) / 4), $n - 1]));
@endphp

<div
    class="relative"
    x-data="{ activo: null, n: {{ $n }} }"
    x-on:mouseleave="activo = null"
    x-on:keydown.arrow-right.prevent="activo = Math.min(n - 1, (activo ?? -1) + 1)"
    x-on:keydown.arrow-left.prevent="activo = Math.max(0, (activo ?? n) - 1)"
    x-on:keydown.escape="activo = null"
    x-on:blur.capture="activo = null"
    tabindex="0"
    role="group"
    aria-label="{{ $descripcion }}. Con las flechas del teclado recorres los puntos."
>
    <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" class="block h-auto w-full" role="img" aria-label="{{ $descripcion }}" focusable="false">
        {{-- Cuadrícula y eje Y --}}
        @for ($valor = 0; $valor <= $tope; $valor += $paso)
            <line x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ round($y($valor), 1) }}" y2="{{ round($y($valor), 1) }}" class="stroke-neutral-300" stroke-width="1" @if ($valor > 0) stroke-dasharray="3 4" @endif />
            <text x="{{ $izq - 6 }}" y="{{ round($y($valor), 1) + 4 }}" text-anchor="end" font-size="11" class="fill-neutral-700">{{ $valor }}</text>
        @endfor

        {{-- Eje X --}}
        @foreach ($marcasX as $posicion)
            <text x="{{ round($x($posicion), 1) }}" y="{{ $alto - 6 }}" font-size="11" class="fill-neutral-700" text-anchor="{{ $posicion === 0 ? 'start' : ($posicion === $n - 1 ? 'end' : 'middle') }}">{{ $etiquetas[$posicion] }}</text>
        @endforeach

        {{-- Series: la de comparación primero, para que la principal quede encima --}}
        @foreach (array_reverse($series) as $serie)
            @if ($serie['principal'] ?? false)
                <path d="{{ $linea($serie['valores']) }} L{{ round($x($n - 1), 1) }} {{ round($y(0), 1) }} L{{ round($x(0), 1) }} {{ round($y(0), 1) }} Z" class="fill-accent-100" />
                <path d="{{ $linea($serie['valores']) }}" fill="none" class="stroke-accent-700" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
            @else
                <path d="{{ $linea($serie['valores']) }}" fill="none" class="stroke-neutral-600" stroke-width="2" stroke-dasharray="5 4" stroke-linejoin="round" stroke-linecap="round" />
            @endif
        @endforeach

        {{-- Punto activo --}}
        @for ($i = 0; $i < $n; $i++)
            <g x-show="activo === {{ $i }}" x-cloak>
                <line x1="{{ round($x($i), 1) }}" x2="{{ round($x($i), 1) }}" y1="{{ $arriba }}" y2="{{ $alto - $abajo }}" class="stroke-neutral-600" stroke-width="1" />
                @foreach ($series as $serie)
                    <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($serie['valores'][$i]), 1) }}" r="4.5" class="{{ ($serie['principal'] ?? false) ? 'fill-accent-700' : 'fill-neutral-600' }}" stroke="white" stroke-width="1.5" />
                @endforeach
            </g>
        @endfor

        {{-- Zonas sensibles al puntero: una por punto --}}
        @for ($i = 0; $i < $n; $i++)
            <rect
                x="{{ round($x($i) - ($n > 1 ? $util / ($n - 1) / 2 : $util / 2), 1) }}" y="{{ $arriba }}"
                width="{{ round($n > 1 ? $util / ($n - 1) : $util, 1) }}" height="{{ $alto - $arriba - $abajo }}"
                fill="transparent" x-on:mouseenter="activo = {{ $i }}"
            />
        @endfor
    </svg>

    {{-- Valores del punto activo --}}
    @for ($i = 0; $i < $n; $i++)
        <div
            x-show="activo === {{ $i }}" x-cloak
            class="pointer-events-none absolute top-0 z-10 w-max max-w-[220px] rounded border border-divider bg-surface px-3 py-2 text-12 shadow-md"
            style="left: {{ round($x($i) / $ancho * 100, 1) }}%; transform: translateX({{ $i > $n / 2 ? '-105%' : '5%' }});"
        >
            <div class="font-semibold text-ink">{{ $etiquetas[$i] }}</div>
            @foreach ($series as $serie)
                <div class="text-neutral-800">{{ $serie['nombre'] }}: <span class="font-semibold">{{ $serie['valores'][$i] }}</span></div>
            @endforeach
        </div>
    @endfor

    <p class="sr-only" role="status" aria-live="polite" x-text="activo === null ? '' : ({{ \Illuminate\Support\Js::from(array_map(fn ($i) => $etiquetas[$i].'. '.collect($series)->map(fn ($s) => $s['nombre'].': '.$s['valores'][$i].' '.$unidad)->implode('. '), range(0, $n - 1))) }})[activo]"></p>

    {{-- Los mismos datos para lectores de pantalla --}}
    <table class="sr-only">
        <caption>{{ $descripcion }}</caption>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                @foreach ($series as $serie)
                    <th scope="col">{{ $serie['nombre'] }} ({{ $unidad }})</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $n; $i++)
                <tr>
                    <th scope="row">{{ $etiquetas[$i] }}</th>
                    @foreach ($series as $serie)
                        <td>{{ $serie['valores'][$i] }}</td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
</div>
