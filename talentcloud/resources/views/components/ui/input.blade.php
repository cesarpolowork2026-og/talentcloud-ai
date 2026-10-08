{{--
    Campo de texto (.input). error: nombre del campo para marcar aria-invalid.
    Con type="password" lleva un botón para mostrar u ocultar lo escrito.
--}}
@props(['error' => null])

@if ($attributes->get('type') === 'password')
    <div class="relative" x-data="{ visible: false }">
        <input
            {{ $attributes->class('input w-full pr-11') }}
            x-bind:type="visible ? 'text' : 'password'"
            @if ($error && $errors->has($error)) aria-invalid="true" @endif
        >
        <button
            type="button"
            class="absolute right-1 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded text-neutral-700 hover:text-accent-700"
            x-on:click="visible = ! visible"
            x-bind:aria-pressed="visible.toString()"
            x-bind:aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            aria-label="Mostrar contraseña"
            aria-pressed="false"
        >
            <span x-show="! visible"><x-ui.icon name="eye" :size="18" /></span>
            <span x-show="visible" style="display: none"><x-ui.icon name="eye-slash" :size="18" /></span>
        </button>
    </div>
@else
    <input
        {{ $attributes->merge(['type' => 'text'])->class('input') }}
        @if ($error && $errors->has($error)) aria-invalid="true" @endif
    >
@endif
