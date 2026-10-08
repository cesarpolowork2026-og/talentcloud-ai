{{--
    Indicador del dashboard: tarjeta con kicker y cifra en serif 32px. highlight: cifra en accent-700.
    Con una acción (wire:click / x-on:click) es un <button>: se abre también con el teclado (WCAG 2.1.1).
--}}
@props(['label', 'value', 'highlight' => false])

@php($accionable = $attributes->has('wire:click') || $attributes->has('x-on:click'))

<{{ $accionable ? 'button' : 'div' }}
    @if ($accionable) type="button" @endif
    {{ $attributes->class(['card', 'cursor-pointer text-left' => $accionable]) }}
>
    <span class="card-kicker block">{{ $label }}</span>
    <span @class(['mt-1 block font-heading text-32 font-semibold', 'text-accent-700' => $highlight])>{{ $value }}</span>
</{{ $accionable ? 'button' : 'div' }}>
