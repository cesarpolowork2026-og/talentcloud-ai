{{-- Control segmentado (.seg). Contiene <x-ui.seg-option>. label: id del texto que lo nombra. --}}
@props(['labelledby' => null])

<div {{ $attributes->class('seg') }} role="radiogroup" @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif>
    {{ $slot }}
</div>
