{{-- Opción de control segmentado (.seg-opt). Recibe los atributos del <input type="radio">. --}}
<label class="seg-opt">
    <input type="radio" {{ $attributes }}>{{ $slot }}
</label>
