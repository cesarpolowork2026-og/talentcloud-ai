{{--
    Marco de teléfono para las maquetas del postulante. Ancho fijo de 300 px (cabe en 320 px de
    pantalla, sin escalar). Es ilustrativo: va oculto a los lectores de pantalla y el texto de al
    lado dice lo mismo.
--}}
<div {{ $attributes->class('mx-auto w-[300px] max-w-full') }} aria-hidden="true">
    <div class="rounded-[40px] bg-accent-900 p-[10px] shadow-lg">
        <div class="relative overflow-hidden rounded-[30px] bg-bg">
            {{-- Cámara --}}
            <span class="absolute left-1/2 top-2 z-10 h-[18px] w-[86px] -translate-x-1/2 rounded-full bg-accent-900"></span>
            {{ $slot }}
        </div>
    </div>
    <x-presentacion.ejemplo class="mt-3 text-center" />
</div>
