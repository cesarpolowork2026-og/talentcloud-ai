{{-- Migas del prototipo ("Vacantes / Desarrollador Backend"). items: [['label' => …, 'href' => …|null], …] --}}
@props(['items' => []])

<nav aria-label="Ruta" {{ $attributes->class('mb-2 text-13 text-neutral-600') }}>
    @foreach ($items as $item)
        @if (! $loop->first)
            <span aria-hidden="true"> / </span>
        @endif
        @if (! empty($item['href']) && ! $loop->last)
            <a href="{{ $item['href'] }}" wire:navigate>{{ $item['label'] }}</a>
        @else
            <span class="text-ink" @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
