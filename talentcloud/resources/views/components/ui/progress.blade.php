{{-- Barra de coincidencia del prototipo: 6px, fondo neutral-200, relleno accent. value: 0–100. --}}
@props(['value' => 0, 'label' => null])

@php($pct = max(0, min(100, (int) round($value))))

<div
    {{ $attributes->class('h-[6px] overflow-hidden rounded-lg bg-neutral-200') }}
    role="progressbar"
    aria-valuemin="0"
    aria-valuemax="100"
    aria-valuenow="{{ $pct }}"
    @if ($label) aria-label="{{ $label }}" @endif
>
    <div class="h-full bg-accent" style="width: {{ $pct }}%"></div>
</div>
