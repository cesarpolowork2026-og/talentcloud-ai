{{--
    Diálogo modal Broadsheet (.dialog-backdrop + .dialog).
    Se abre de dos formas:
      - wire:model="propiedadBooleana" (Livewire, entrelazado con Alpine)
      - name="x" + $dispatch('open-dialog', 'x') / $dispatch('close-dialog', 'x')
    Cierra con Escape, clic en el fondo o open = false dentro del slot.
    aspa: el diálogo se cierra solo con una ✕ arriba a la derecha (y con Escape); un clic en el fondo no lo cierra. Para
    los que no deben cerrarse por un descuido, como el de iniciar sesión. La ✕ va al final del DOM para que el primer
    campo, y no ella, reciba el foco al abrirse.
    Slot "actions": botones alineados a la derecha.
    max-width solo lo estrecha (el DS lo fija en 440 px); ancho="1100px" lo ensancha
    hasta ese valor sin pasar del de la pantalla.
--}}
@props(['title' => null, 'name' => null, 'maxWidth' => '440px', 'ancho' => null, 'aspa' => false])

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
    @unless ($aspa) x-on:click.self="open = false" @endunless
    {{ $attributes->whereDoesntStartWith('wire:model')->class('dialog-backdrop') }}
>
    <div
        @class(['dialog', 'relative' => $aspa])
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

        @if ($aspa)
            <button type="button" class="dialog-aspa" x-on:click="open = false" aria-label="Cerrar">
                <x-ui.icon name="x" :size="18" />
            </button>
        @endif
    </div>
</div>
