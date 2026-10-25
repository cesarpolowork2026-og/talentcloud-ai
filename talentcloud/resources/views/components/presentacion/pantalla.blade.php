{{--
    Una maqueta de la app (components/presentacion/maqueta-*) escalada al ancho disponible,
    como en el carrusel: dibujada a 880×520 y reducida con maquetaEscalada. Es ilustrativa
    (aria-hidden): el texto que la acompaña dice lo mismo. maqueta: kanban | candidatos | …
--}}
@props(['maqueta'])

<figure {{ $attributes->class('m-0') }}>
    <div class="relative -m-[14px] overflow-hidden" style="aspect-ratio: 880 / 520" aria-hidden="true" x-data="maquetaEscalada(880, 520)">
        <div class="p-[14px]" :style="estiloMaqueta" style="width: 880px; height: 520px">
            <x-dynamic-component :component="'presentacion.maqueta-'.$maqueta" />
        </div>
    </div>
    <figcaption class="mt-5"><x-presentacion.ejemplo /></figcaption>
</figure>
