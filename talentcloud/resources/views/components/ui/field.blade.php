{{--
    Campo de formulario (.field): etiqueta, control (slot), ayuda y error de validación.
    error: nombre del campo en la bolsa de errores (p. ej. "form.email").
--}}
@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null])

<div {{ $attributes->class('field') }}>
    @if ($label && $for)
        <label for="{{ $for }}">{{ $label }}</label>
    @elseif ($label)
        <label>{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif

    @if ($error)
        @error($error)
            <p class="field-error" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
