{{-- Etiqueta Broadsheet (.tag). tone: accent | accent-2 | neutral | outline --}}
@props(['tone' => 'neutral'])

@php
    // Nombres completos: Tailwind solo genera las clases que encuentra escritas literalmente.
    $toneClass = match ($tone) {
        'accent' => 'tag-accent',
        'accent-2' => 'tag-accent-2',
        'outline' => 'tag-outline',
        default => 'tag-neutral',
    };
@endphp

<span {{ $attributes->class(['tag', $toneClass]) }}>{{ $slot }}</span>
