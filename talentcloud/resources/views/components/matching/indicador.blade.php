{{--
    Indicador circular del puntaje global (0–100): un arco sobre un anillo. El número va como
    texto del documento (no solo dibujado) y el dibujo es decorativo: el mismo dato está en las cifras de al lado.
--}}
@props(['valor', 'etiqueta' => 'de coincidencia', 'rotulo' => 'global'])

@php
    $radio = 52;
    $longitud = 2 * M_PI * $radio;
    $pct = max(0, min(100, (int) round($valor)));
@endphp

<div {{ $attributes->class('relative grid h-[132px] w-[132px] shrink-0 place-items-center') }} role="img" aria-label="{{ $pct }} % {{ $etiqueta }}">
    <svg viewBox="0 0 132 132" width="132" height="132" class="absolute inset-0 -rotate-90" aria-hidden="true" focusable="false">
        <circle cx="66" cy="66" r="{{ $radio }}" fill="none" stroke-width="12" class="stroke-neutral-200" />
        <circle cx="66" cy="66" r="{{ $radio }}" fill="none" stroke-width="12" stroke-linecap="round" class="stroke-accent-700"
            stroke-dasharray="{{ round($longitud * $pct / 100, 2) }} {{ round($longitud, 2) }}" />
    </svg>
    <div class="relative text-center leading-none">
        <span class="block font-heading text-32 font-semibold text-ink">{{ $pct }}<span class="text-18">%</span></span>
        <span class="mt-1 block text-11 text-neutral-700">{{ $rotulo }}</span>
    </div>
</div>
