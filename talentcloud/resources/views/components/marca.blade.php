{{--
    Marca de TalentCloud AI (nube + nombre + etiqueta "AI"), igual en la
    presentación, las pantallas de acceso, la barra de la app y los errores.
    tamano: grande (cabecera pública) | mediano (barra de la app) | pequeno (maquetas, pie).
--}}
@props(['tamano' => 'mediano'])

@php
    [$icono, $texto] = match ($tamano) {
        'grande' => [30, 'text-18 sm:text-22'],
        'pequeno' => [16, 'text-14'],
        default => [24, 'text-18'],
    };
@endphp

<span {{ $attributes->class('inline-flex items-center gap-2') }}>
    <x-ui.icon name="cloud" :size="$icono" class="text-accent-700" />
    <span class="flex items-baseline gap-1.5">
        <span @class(['font-heading font-semibold tracking-[-0.01em] text-ink', $texto])>TalentCloud</span>
        <x-ui.tag tone="outline" @class(['max-[359px]:hidden' => $tamano === 'grande', '!px-1.5 !py-0 !text-10' => $tamano === 'pequeno'])>AI</x-ui.tag>
    </span>
</span>
