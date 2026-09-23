{{-- Área de texto (.input). --}}
@props(['error' => null])

<textarea
    {{ $attributes->class('input') }}
    @if ($error && $errors->has($error)) aria-invalid="true" @endif
>{{ $slot }}</textarea>
