{{-- Indicador del dashboard: tarjeta con kicker y cifra en serif 32px. highlight: cifra en accent-700. --}}
@props(['label', 'value', 'highlight' => false])

<div {{ $attributes->class(['card', 'cursor-pointer' => $attributes->has('wire:click') || $attributes->has('x-on:click')]) }}>
    <div class="card-kicker">{{ $label }}</div>
    <div @class(['mt-1 font-heading text-32 font-semibold', 'text-accent-700' => $highlight])>{{ $value }}</div>
</div>
