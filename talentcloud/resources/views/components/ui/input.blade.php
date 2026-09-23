{{-- Campo de texto (.input). error: nombre del campo para marcar aria-invalid. --}}
@props(['error' => null])

<input
    {{ $attributes->merge(['type' => 'text'])->class('input') }}
    @if ($error && $errors->has($error)) aria-invalid="true" @endif
>
