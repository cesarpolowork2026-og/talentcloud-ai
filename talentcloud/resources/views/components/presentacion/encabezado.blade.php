{{--
    Encabezado de sección de la presentación: rótulo y título a la izquierda y, desde 1024 px,
    la explicación ($slot) a la derecha, alineada abajo. Así el título no deja medio ancho vacío.
    id: el del <h2>, para el aria-labelledby de la sección.
--}}
@props(['rotulo', 'titulo', 'id' => null])

<div {{ $attributes->class('mb-10 grid gap-4 lg:grid-cols-2 lg:items-end lg:gap-16') }}>
    <div>
        <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">{{ $rotulo }}</p>
        <h2 @if ($id) id="{{ $id }}" @endif class="m-0 max-w-[22ch] text-[clamp(26px,3vw,34px)] leading-[1.15] lg:max-w-none">{{ $titulo }}</h2>
    </div>
    <p class="m-0 max-w-[60ch] text-16 text-neutral-800">{{ $slot }}</p>
</div>
