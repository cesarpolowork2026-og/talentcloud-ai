{{--
    Buscador con resultados del servidor (combobox accesible): reemplaza a un <select> cuando las opciones
    pueden ser miles (vacantes, postulaciones). Lógica: buscadorRemoto (app.js).
    enLinea: la lista ocupa su lugar en el flujo (para diálogos); por defecto flota sobre el contenido.
    metodo: método del componente de Livewire que recibe el texto y devuelve hasta 20 [{id, etiqueta, detalle}].
    modelo: propiedad de Livewire que guarda el id elegido (opcional).
    etiqueta: texto de la elección actual. vacio: texto de la opción «ninguna» (por ejemplo «Todas las vacantes»).
    Al elegir lanza el evento «elegido» ({ id, etiqueta }): <x-ui.buscador x-on:elegido="…" />.
--}}
@props(['id', 'metodo', 'modelo' => null, 'etiqueta' => '', 'vacio' => null, 'placeholder' => 'Escribe para buscar…', 'error' => null, 'enLinea' => false])

<div
    {{-- La etiqueta va aparte: si cambiara el atributo x-data al elegir, Livewire reiniciaría el componente y lo dejaría sin respuesta. --}}
    x-data="buscadorRemoto({ metodo: @js($metodo), modelo: @js($modelo), vacio: @js($vacio) })"
    data-etiqueta="{{ $etiqueta }}"
    x-on:click.outside="cerrar()"
    {{ $attributes->class('relative') }}
>
    <input
        id="{{ $id }}"
        x-model="texto"
        type="text"
        class="input w-full"
        role="combobox"
        autocomplete="off"
        aria-autocomplete="list"
        aria-controls="{{ $id }}-lista"
        :aria-expanded="(abierto && opciones.length > 0).toString()"
        :aria-activedescendant="activa >= 0 ? '{{ $id }}-opcion-' + activa : null"
        placeholder="{{ $placeholder }}"
        @if ($error && $errors->has($error)) aria-invalid="true" @endif
        x-on:input="escribir()"
        x-on:focus="abrir()"
        x-on:click="if (! abierto) abrir()"
        x-on:keydown.arrow-down.prevent="mover(1)"
        x-on:keydown.arrow-up.prevent="mover(-1)"
        x-on:keydown.enter.prevent="confirmar()"
        x-on:keydown.escape="if (abierto) { $event.stopPropagation(); cerrar() }"
    >

    <ul
        id="{{ $id }}-lista"
        role="listbox"
        aria-label="Resultados"
        @class([
            'm-0 mt-1 list-none overflow-auto rounded border border-divider bg-surface p-1',
            // En línea (dentro de un diálogo) la lista empuja el contenido en vez de quedar recortada por él.
            'max-h-56' => $enLinea,
            'absolute left-0 right-0 z-20 max-h-72 min-w-[260px] shadow-md' => ! $enLinea,
        ])
        x-show="abierto"
        x-cloak
    >
        <li x-show="cargando && opciones.length === 0" class="px-3 py-2 text-13 text-neutral-700" role="status">Buscando…</li>
        <li x-show="! cargando && opciones.length === 0" class="px-3 py-2 text-13 text-neutral-700" role="status">Sin resultados. Prueba con otra palabra.</li>
        <template x-for="(opcion, posicion) in opciones" :key="String(opcion.id)">
            <li
                :id="'{{ $id }}-opcion-' + posicion"
                role="option"
                :aria-selected="(activa === posicion).toString()"
                class="flex cursor-pointer items-baseline justify-between gap-3 rounded px-3 py-2 text-14"
                :class="activa === posicion ? 'bg-accent-100' : 'hover:bg-neutral-100'"
                x-on:mousedown.prevent="elegir(opcion)"
                x-on:mousemove="activa = posicion"
            >
                <span x-text="opcion.etiqueta"></span>
                <span class="text-12 text-neutral-700" x-text="opcion.detalle"></span>
            </li>
        </template>
    </ul>
</div>
