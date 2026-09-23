{{--
    Botón Broadsheet (.btn).
    variant: primary | secondary | ghost | danger
    size:    sm (acciones compactas de tablas y tarjetas)
    Con href se renderiza como enlace.
--}}
@props([
    'variant' => 'secondary',
    'size' => null,
    'icon' => false,
    'block' => false,
    'href' => null,
    'type' => 'button',
])

@php
    // Nombres completos: Tailwind solo genera las clases que encuentra escritas literalmente.
    $classes = [
        'btn',
        match ($variant) {
            'primary' => 'btn-primary',
            'ghost' => 'btn-ghost',
            'danger' => 'btn-danger',
            default => 'btn-secondary',
        },
        'btn-sm' => $size === 'sm',
        'btn-icon' => $icon,
        'btn-block' => $block,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
