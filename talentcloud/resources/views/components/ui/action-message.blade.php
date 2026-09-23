{{-- Mensaje breve de confirmación ("Cambios guardados.") al recibir el evento Livewire indicado en "on". --}}
@props(['on'])

<span
    x-data="{ visible: false, temporizador: null }"
    x-init="$wire.on('{{ $on }}', () => { clearTimeout(temporizador); visible = true; temporizador = setTimeout(() => visible = false, 2000) })"
    x-show="visible"
    x-cloak
    x-transition.opacity.out.duration.1000ms
    role="status"
    {{ $attributes->class('text-13 text-accent-700') }}
>{{ $slot->isEmpty() ? 'Cambios guardados.' : $slot }}</span>
