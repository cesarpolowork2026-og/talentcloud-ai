{{--
    Encabezado de pantalla del prototipo: h1 30px + subtítulo 15px.
    actions: botones a la derecha del título. breadcrumbs: <x-ui.breadcrumb> encima.
    compact: menos aire bajo el subtítulo (pantallas con filtros debajo).
--}}
@props(['title', 'subtitle' => null, 'compact' => false])

<header {{ $attributes }}>
    {{ $breadcrumbs ?? '' }}

    @isset($actions)
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-3">
            <h1 class="m-0 text-30">{{ $title }}</h1>
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        </div>
    @else
        <h1 class="mb-1 text-30">{{ $title }}</h1>
    @endisset

    @if ($subtitle)
        <p @class(['text-15 text-neutral-700', 'mb-4' => $compact, 'mb-6' => ! $compact])>{{ $subtitle }}</p>
    @endif
</header>
