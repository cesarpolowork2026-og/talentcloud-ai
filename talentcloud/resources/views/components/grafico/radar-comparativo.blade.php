{{--
    Radar comparativo: de 2 a 3 personas superpuestas sobre los mismos criterios (3 a 8 ejes, escala 0–10).
    Cada serie lleva su color fijo (azul, naranja, aguamarina) y siempre hay leyenda con trazo; al pasar por una
    leyenda, las demás series se atenúan. Los números exactos van en la tabla de al lado: esto es un resumen visual.

    ejes: lista de ['etiqueta'].
    series: lista de ['nombre', 'valores' (lista de float|null, uno por eje), 'promedio' (float|null)], máximo 3.
--}}
@props(['ejes', 'series', 'maximo' => 10, 'descripcion' => null])

@php
    $n = count($ejes);
    $ancho = 560;
    $alto = 370;
    $cx = $ancho / 2;
    $cy = 185;
    $radio = 128;
    $series = array_slice($series, 0, 3);
    $coma = fn (float|int $v): string => str_replace('.', ',', (string) round($v, 1));
    $punto = fn (int $i, float $escala): array => [
        round($cx + $radio * $escala * sin(2 * M_PI * $i / $n), 1),
        round($cy - $radio * $escala * cos(2 * M_PI * $i / $n), 1),
    ];
    $poligono = fn (float $escala): string => collect(range(0, $n - 1))->map(fn (int $i) => implode(',', $punto($i, $escala)))->implode(' ');
    // Etiquetas largas en dos líneas, partidas por la palabra más cercana a la mitad.
    $lineas = function (string $texto): array {
        if (mb_strlen($texto) <= 18 || ! str_contains($texto, ' ')) {
            return [$texto];
        }
        $palabras = explode(' ', $texto);
        $mejor = 1;
        $mejorDif = PHP_INT_MAX;
        for ($k = 1; $k < count($palabras); $k++) {
            $dif = abs(mb_strlen(implode(' ', array_slice($palabras, 0, $k))) - mb_strlen(implode(' ', array_slice($palabras, $k))));
            if ($dif < $mejorDif) {
                $mejor = $k;
                $mejorDif = $dif;
            }
        }

        return [implode(' ', array_slice($palabras, 0, $mejor)), implode(' ', array_slice($palabras, $mejor))];
    };
    $resumen = $descripcion ?? ('Radar comparativo de '.collect($series)->pluck('nombre')->implode(', ').' en '.collect($ejes)->pluck('etiqueta')->implode(', ').'. Los números exactos están en la tabla.');
@endphp

@if ($n >= 3 && count($series) >= 2)
    <div
        {{ $attributes->class('viz') }}
        x-data="{
            foco: null, tip: null, x: 0, y: 0,
            mostrar(e, titulo, detalle) { this.tip = { titulo, detalle }; this.mover(e); },
            mover(e) { this.x = Math.min(e.clientX + 14, window.innerWidth - 270); this.y = e.clientY + 16; },
            ocultar() { this.tip = null; },
        }"
    >
        <ul class="radar-leyenda mb-2" aria-label="Personas comparadas">
            @foreach ($series as $s => $serie)
                <li>
                    <button type="button" class="radar-boton" x-on:mouseenter="foco = {{ $s }}" x-on:mouseleave="foco = null" x-on:focus="foco = {{ $s }}" x-on:blur="foco = null">
                        <i style="--serie: var(--viz-s{{ $s + 1 }})" aria-hidden="true"></i>
                        <b>{{ $serie['nombre'] }}</b>
                        <span>{{ $serie['promedio'] === null ? 'sin puntaje' : $coma($serie['promedio']).'/10' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" class="mx-auto block h-auto w-full max-w-[560px]" role="img" aria-label="{{ $resumen }}">
            {{-- Anillos de referencia (2,5 · 5 · 7,5 · 10) y ejes --}}
            @foreach ([0.25, 0.5, 0.75, 1.0] as $escala)
                <polygon points="{{ $poligono($escala) }}" fill="none" stroke="var(--viz-rejilla)" stroke-width="1" />
            @endforeach
            @foreach (range(0, $n - 1) as $i)
                @php
                    [$ex, $ey] = $punto($i, 1.0);
                @endphp
                <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ex }}" y2="{{ $ey }}" stroke="var(--viz-rejilla)" stroke-width="1" />
            @endforeach
            <text x="{{ $cx + 5 }}" y="{{ round($cy - $radio * 0.5 + 4, 1) }}" class="radar-valor">{{ $coma($maximo / 2) }}</text>
            <text x="{{ $cx + 5 }}" y="{{ round($cy - $radio + 4, 1) }}" class="radar-valor">{{ $coma($maximo) }}</text>

            {{-- Etiquetas de los ejes --}}
            @foreach ($ejes as $i => $eje)
                @php
                    $seno = sin(2 * M_PI * $i / $n);
                    $coseno = cos(2 * M_PI * $i / $n);
                    $ancla = $seno > 0.3 ? 'start' : ($seno < -0.3 ? 'end' : 'middle');
                    $partes = $lineas($eje['etiqueta']);
                    $lx = round($cx + ($radio + 16) * $seno, 1);
                    $ly = round($cy - ($radio + 16) * $coseno + ($coseno > 0.3 ? -4 : ($coseno < -0.3 ? 14 : 4)) - (count($partes) - 1) * ($coseno < -0.3 ? 0 : 7), 1);
                @endphp
                <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="{{ $ancla }}" class="radar-etiqueta">
                    @foreach ($partes as $k => $parte)
                        <tspan x="{{ $lx }}" dy="{{ $k === 0 ? 0 : 14 }}">{{ $parte }}</tspan>
                    @endforeach
                </text>
            @endforeach

            {{-- Series: área al 10 %, línea de 2 px y un punto de 8 px con anillo del color de la superficie --}}
            @foreach ($series as $s => $serie)
                @php
                    $puntos = collect($serie['valores'])->map(fn ($v, $i) => $punto($i, max(0, min(1, ($v ?? 0) / $maximo))));
                    $color = 'var(--viz-s'.($s + 1).')';
                @endphp
                <g class="radar-poligono" style="--cx: {{ $cx }}px; --cy: {{ $cy }}px; --orden: {{ $s }}" x-bind:data-atenuada="foco !== null && foco !== {{ $s }} ? '' : null">
                    <polygon points="{{ $puntos->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}" fill="{{ $color }}" fill-opacity="0.1" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" />
                    @foreach ($puntos as $i => $p)
                        @php
                            $valor = $serie['valores'][$i];
                        @endphp
                        <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="4" fill="{{ $valor === null ? 'var(--viz-superficie)' : $color }}" stroke="{{ $valor === null ? $color : 'var(--viz-superficie)' }}" stroke-width="2" />
                        <circle
                            cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="13" fill="transparent"
                            x-on:pointerenter="mostrar($event, @js($serie['nombre'].' · '.$ejes[$i]['etiqueta']), @js($valor === null ? 'Sin evaluar en este criterio' : $coma($valor).' de '.$maximo))"
                            x-on:pointermove="mover($event)"
                            x-on:pointerleave="ocultar()"
                        />
                    @endforeach
                </g>
            @endforeach
        </svg>

        <div class="viz-tip" style="display: none" x-show="tip" x-bind:style="`left: ${x}px; top: ${y}px`" aria-hidden="true">
            <strong x-text="tip && tip.titulo"></strong>
            <span x-text="tip && tip.detalle"></span>
        </div>
    </div>
@endif
