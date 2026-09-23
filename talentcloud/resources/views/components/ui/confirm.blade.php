{{--
    Confirmación de acción destructiva del prototipo (Descartar, Cancelar entrevista, Suspender…).
    action: expresión Livewire que se ejecuta al confirmar, p. ej. "descartar(12)".
    Acepta wire:model o name, igual que <x-ui.dialog>.
--}}
@props(['title', 'actionLabel', 'action'])

<x-ui.dialog {{ $attributes }} :title="$title" max-width="420px">
    {{ $slot }}

    <x-slot:actions>
        <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
        <x-ui.button variant="danger" wire:click="{{ $action }}" x-on:click="open = false">{{ $actionLabel }}</x-ui.button>
    </x-slot:actions>
</x-ui.dialog>
