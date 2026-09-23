{{--
    Selector (.input). options: [valor => etiqueta]; o pasar <option> en el slot.
    selected: valor inicial cuando no se usa wire:model.
--}}
@props(['options' => [], 'placeholder' => null, 'error' => null, 'selected' => null])

<select
    {{ $attributes->class('input') }}
    @if ($error && $errors->has($error)) aria-invalid="true" @endif
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected($selected !== null && (string) $value === (string) $selected)>{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
