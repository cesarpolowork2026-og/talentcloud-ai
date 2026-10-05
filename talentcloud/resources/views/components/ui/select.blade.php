{{--
    Selector (.input). options: [valor => etiqueta]; o pasar <option> en el slot.
    Una entrada cuyo valor es un arreglo es un grupo: [grupo => [valor => etiqueta]]
    se pinta como <optgroup label="grupo"> (p. ej. OpcionesPorEmpresa).
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
        @if (is_array($label))
            <optgroup label="{{ $value }}">
                @foreach ($label as $valorDelGrupo => $etiquetaDelGrupo)
                    <option value="{{ $valorDelGrupo }}" @selected($selected !== null && (string) $valorDelGrupo === (string) $selected)>{{ $etiquetaDelGrupo }}</option>
                @endforeach
            </optgroup>
        @else
            <option value="{{ $value }}" @selected($selected !== null && (string) $value === (string) $selected)>{{ $label }}</option>
        @endif
    @endforeach
    {{ $slot }}
</select>
