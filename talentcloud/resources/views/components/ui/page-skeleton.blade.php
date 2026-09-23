{{-- Esqueleto de navegación del prototipo (título, línea y tres bloques). --}}
<div {{ $attributes->class('grid max-w-[800px] gap-3') }} role="status" aria-label="Cargando">
    <x-ui.skeleton class="h-[30px] w-[220px]" />
    <x-ui.skeleton class="h-[14px] w-full" />
    <x-ui.skeleton class="h-[64px]" />
    <x-ui.skeleton class="h-[64px]" />
    <x-ui.skeleton class="h-[64px]" />
</div>
