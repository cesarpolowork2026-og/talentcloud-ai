{{--
    Embudo de selección: una fila por etapa con una forma que se estrecha según cuántas postulaciones llegaron
    (el ancho de cada forma es su % del total; la cifra exacta va siempre en texto). Rampa de una sola tonalidad,
    de claro a oscuro; el color nunca va solo (cada fila lleva su cifra y su conversión).

    etapas: lista de ['clave', 'etiqueta', 'total', 'porcentaje' (0–100 del total), 'conversion' (% de la etapa
            anterior o null)], de la primera etapa a la última.
--}}
@props(['etapas', 'rotulo' => 'Postulaciones que alcanzaron cada etapa'])

@php
    $n = count($etapas);
    // Un piso visual: una etapa con pocas postulaciones sigue viéndose (la cifra real va en el texto).
    $ancho = fn (int $i): int => max(5, min(100, (int) $etapas[$i]['porcentaje']));
    $punto = fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

<ol {{ $attributes->class('viz embudo') }} aria-label="{{ $rotulo }}">
    @foreach ($etapas as $i => $fila)
        @php
            $arriba = $ancho($i);
            // Cada forma baja hasta el ancho de la siguiente; la última queda recta.
            $abajo = $i + 1 < $n ? $ancho($i + 1) : $arriba;
            $forma = 'polygon('.$punto((100 - $arriba) / 2).'% 0, '.$punto(100 - (100 - $arriba) / 2).'% 0, '.$punto(100 - (100 - $abajo) / 2).'% 100%, '.$punto((100 - $abajo) / 2).'% 100%)';
        @endphp
        <li class="embudo-fila" wire:key="embudo-{{ $fila['clave'] }}">
            <div class="embudo-etapa">
                {{ $fila['etiqueta'] }}
                @if ($fila['conversion'] !== null)
                    <span class="embudo-conversion">{{ $fila['conversion'] }} % de la etapa anterior</span>
                @endif
            </div>
            <div class="embudo-forma" style="--embudo-color: var(--viz-ord-{{ min(5, $i + 1) }}); --embudo-forma: {{ $forma }}; --orden: {{ $i }};" aria-hidden="true"></div>
            <div class="embudo-cifra">
                {{ number_format($fila['total'], 0, ',', ' ') }}
                <small>{{ $fila['porcentaje'] }} % del total</small>
            </div>
        </li>
    @endforeach
</ol>
