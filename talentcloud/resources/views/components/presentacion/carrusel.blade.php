{{--
    Carrusel de pantallas de la presentación. Las maquetas son ilustrativas
    (aria-hidden); lo que se anuncia es el texto de cada pantalla, en vivo solo
    cuando el carrusel ya no avanza solo (patrón WAI de carrusel).
    Avance automático sin botón de pausa: solo se detiene, para siempre, al pulsar una flecha o un
    punto (decisión del usuario). No arranca si el sistema pide reducir el movimiento y no avanza
    mientras la pestaña está oculta.
    diapositivas: list<array{maqueta: string, etiqueta: string, titulo: string, descripcion: string}>
--}}
@props(['diapositivas'])

@php($total = count($diapositivas))

<section
    {{ $attributes->class('min-w-0') }}
    aria-roledescription="carrusel"
    aria-label="Pantallas de TalentCloud AI"
    x-data="carruselPresentacion({{ $total }}, 3000)"
>
    {{-- Maquetas: dibujadas a 880×520 (con margen para la sombra) y escaladas al ancho disponible. --}}
    <div class="relative -m-[14px] overflow-hidden" style="aspect-ratio: 880 / 520" aria-hidden="true" x-data="maquetaEscalada(880, 520)">
        <div class="carrusel-pista p-[14px]" :style="estiloMaqueta" style="width: 880px; height: 520px">
            @foreach ($diapositivas as $indice => $diapositiva)
                <div
                    class="h-full min-h-0"
                    x-show="actual === {{ $indice }}"
                    x-transition:enter="transition-opacity duration-500 motion-reduce:transition-none"
                    x-transition:enter-start="opacity-0"
                    x-transition:leave="transition-opacity duration-500 motion-reduce:transition-none"
                    x-transition:leave-end="opacity-0"
                    @if ($indice > 0) style="display: none" @endif
                >
                    <x-dynamic-component :component="'presentacion.maqueta-'.$diapositiva['maqueta']" />
                </div>
            @endforeach
        </div>
    </div>

    {{-- Controles: anterior, una marca por pantalla, siguiente y posición. --}}
    <div class="mt-4 flex flex-wrap items-center gap-1">
        <x-ui.button variant="ghost" icon x-on:click="anterior()" aria-label="Pantalla anterior">
            <x-ui.icon name="caret-left" />
        </x-ui.button>
        @foreach ($diapositivas as $indice => $diapositiva)
            <button
                type="button"
                class="grid h-7 w-7 place-items-center"
                x-on:click="ir({{ $indice }})"
                :aria-current="actual === {{ $indice }} ? 'true' : 'false'"
                aria-label="Ver pantalla {{ $indice + 1 }}: {{ $diapositiva['etiqueta'] }}"
            >
                {{-- Solo Alpine pone las clases de estado: una clase estática no se quitaría al cambiar. --}}
                <span
                    class="block h-2 rounded-full transition-all motion-reduce:transition-none"
                    :class="actual === {{ $indice }} ? 'w-6 bg-accent-700' : 'w-2 bg-neutral-400'"
                ></span>
            </button>
        @endforeach
        <x-ui.button variant="ghost" icon x-on:click="siguiente()" aria-label="Pantalla siguiente">
            <x-ui.icon name="caret-right" />
        </x-ui.button>
        <span class="ml-auto text-13 tabular-nums text-neutral-700" x-text="`${actual + 1} / ${total}`">1 / {{ $total }}</span>
    </div>

    {{-- Texto de la pantalla visible. --}}
    <div class="mt-2 min-h-[72px]" :aria-live="reproduciendo ? 'off' : 'polite'">
        @foreach ($diapositivas as $indice => $diapositiva)
            <div x-show="actual === {{ $indice }}" @if ($indice > 0) style="display: none" @endif>
                <p class="mb-1 text-11 uppercase tracking-[0.1em] text-accent-700">{{ $diapositiva['etiqueta'] }}</p>
                <p class="m-0 text-15 text-neutral-800"><strong class="font-semibold text-ink">{{ $diapositiva['titulo'] }}</strong> {{ $diapositiva['descripcion'] }}</p>
            </div>
        @endforeach
        <x-presentacion.ejemplo class="mt-2" />
    </div>
</section>
