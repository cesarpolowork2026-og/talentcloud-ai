{{--
    Diálogo modal Broadsheet (.dialog-backdrop + .dialog).
    Se abre de dos formas:
      - wire:model="propiedadBooleana" (Livewire, entrelazado con Alpine)
      - name="x" + $dispatch('open-dialog', 'x') / $dispatch('close-dialog', 'x')
    Cierra con Escape, clic en el fondo o open = false dentro del slot.
    Slot "actions": botones alineados a la derecha.
    max-width solo lo estrecha (el DS lo fija en 440 px); ancho="1100px" lo ensancha
    hasta ese valor sin pasar del de la pantalla.
--}}
@props(['title' => null, 'name' => null, 'maxWidth' => '440px', 'ancho' => null])

@php
    $wireModel = $attributes->wire('model');
    $titleId = 'dialog-title-'.($name ?? uniqid());
@endphp

<div
    @if ($wireModel->value())
        x-data="{ open: @entangle($wireModel) }"
    @else
        x-data="{ open: false }"
    @endif
    @if ($name)
        x-on:open-dialog.window="if ($event.detail === @js($name)) open = true"
        x-on:close-dialog.window="if ($event.detail === @js($name)) open = false"
    @endif
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    x-transition.opacity.duration.150ms
    x-on:click.self="open = false"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('dialog-backdrop') }}
>
    <div
        class="dialog"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-labelledby="{{ $titleId }}" @endif
        style="max-width: {{ $maxWidth }};@if ($ancho) width: min({{ $ancho }}, 100%);@endif"
        x-trap.inert.noscroll="open"
    >
        @if ($title)
            <div class="dialog-title" id="{{ $titleId }}">{{ $title }}</div>
        @endif

        <div class="dialog-body">{{ $slot }}</div>

        @isset($actions)
            <div class="dialog-actions">{{ $actions }}</div>
        @endisset
    </div>
</div>
