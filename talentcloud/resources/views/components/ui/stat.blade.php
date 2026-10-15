{{--
    Indicador del dashboard: tarjeta con kicker y cifra en serif 32px. highlight: cifra en accent-700.
    Con una acción (wire:click / x-on:click) es un <button>: se abre también con el teclado (WCAG 2.1.1).
    variacion: % de cambio frente a un periodo anterior (▲ sube, ▼ baja; el símbolo nunca va sin el signo ni sin el texto).
    nota: a qué se compara la variación. serie: valores del minigráfico de tendencia (solo contexto).
--}}
@props(['label', 'value', 'highlight' => false, 'variacion' => null, 'nota' => null, 'serie' => null])

@php($accionable = $attributes->has('wire:click') || $attributes->has('x-on:click'))

<{{ $accionable ? 'button' : 'div' }}
    @if ($accionable) type="button" @endif
    {{ $attributes->class(['card', 'tarjeta-clicable text-left' => $accionable]) }}
>
    <span class="card-kicker block">{{ $label }}</span>
    <span @class(['mt-1 block font-heading text-32 font-semibold', 'text-accent-700' => $highlight])>{{ $value }}</span>
    @if ($variacion !== null)
        <span class="mt-1 flex flex-wrap items-baseline gap-x-2">
            <span @class(['variacion', 'variacion-sube' => $variacion > 0, 'variacion-baja' => $variacion < 0, 'variacion-igual' => $variacion == 0])>
                <span aria-hidden="true">{{ $variacion > 0 ? '▲' : ($variacion < 0 ? '▼' : '=') }}</span>
                <span class="sr-only">{{ $variacion > 0 ? 'Sube' : ($variacion < 0 ? 'Baja' : 'Sin cambio') }}</span>
                {{ ($variacion > 0 ? '+' : '').str_replace('.', ',', (string) $variacion) }} %
            </span>
            @if ($nota)<span class="text-12 text-neutral-700">{{ $nota }}</span>@endif
        </span>
    @elseif ($nota)
        <span class="mt-1 block text-12 text-neutral-700">{{ $nota }}</span>
    @endif
    @if ($serie && count($serie) >= 2)
        <x-grafico.minilinea :valores="$serie" class="mt-2" />
    @endif
</{{ $accionable ? 'button' : 'div' }}>
