{{--
    Selector (.input). options: [valor => etiqueta]; o pasar <option> en el slot.
--}}
@props(['options' => [], 'placeholder' => null, 'error' => null])

<select
    {{ $attributes->class('input') }}
    @if ($error && $errors->has($error)) aria-invalid="true" @endif
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
