{{-- Estado vacío del prototipo: icono opcional + mensaje centrado. --}}
@props(['message', 'icon' => null])

<div {{ $attributes->class('py-8 text-center text-neutral-600') }}>
    @if ($icon)
        <x-ui.icon :name="$icon" :size="36" class="mx-auto text-neutral-400" />
    @endif
    <p @class(['text-15', 'mt-3' => $icon])>{{ $message }}</p>
    {{ $slot }}
</div>
