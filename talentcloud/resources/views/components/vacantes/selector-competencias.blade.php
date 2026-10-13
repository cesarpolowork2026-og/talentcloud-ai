{{--
    Selector de competencias con etiquetas (alta de vacante): se busca en el catálogo por
    nombre o sinónimo, se sugiere ante un error de tipeo y se puede crear una nueva.
    modelo: propiedad de Livewire con la lista de nombres. Lógica: selectorCompetencias (app.js).
--}}
@props(['modelo', 'catalogo', 'id' => 'vacante-competencias', 'error' => null, 'maximo' => 20])

<div
    x-data="selectorCompetencias({ catalogo: @js($catalogo), modelo: @js($modelo), maximo: {{ $maximo }} })"
    class="grid gap-2"
>
    <div class="flex flex-wrap gap-2" x-show="seleccion.length > 0" x-cloak>
        <template x-for="(nombre, posicion) in seleccion" :key="nombre">
            <span class="tag inline-flex items-center gap-1 pr-1" :class="esNueva(nombre) ? 'tag-accent-2' : 'tag-accent'">
                <span x-text="nombre"></span>
                <span x-show="esNueva(nombre)" class="text-11 uppercase tracking-wide">nueva</span>
                <button
                    type="button"
                    class="grid h-6 w-6 place-items-center rounded text-14 leading-none hover:bg-neutral-200"
                    x-on:click="quitar(posicion)"
                    :aria-label="'Quitar ' + nombre"
                >×</button>
            </span>
        </template>
    </div>

    <div class="relative" x-on:click.outside="abierto = false">
        <input
            id="{{ $id }}"
            x-ref="campo"
            x-model="texto"
            type="text"
            class="input w-full"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-lista"
            :aria-expanded="(abierto && opciones.length > 0).toString()"
            :aria-activedescendant="activa >= 0 ? '{{ $id }}-opcion-' + activa : null"
            :disabled="lleno"
            :placeholder="seleccion.length === 0 ? 'Escribe una competencia: Java, Figma, Atención al cliente…' : (lleno ? 'Máximo {{ $maximo }} competencias' : 'Agregar otra competencia')"
            @if ($error && $errors->has($error)) aria-invalid="true" @endif
            x-on:input="abierto = true; activa = -1"
            x-on:focus="abierto = true"
            x-on:keydown.arrow-down.prevent="mover(1)"
            x-on:keydown.arrow-up.prevent="mover(-1)"
            x-on:keydown.enter.prevent="confirmar()"
            x-on:keydown.comma.prevent="confirmar()"
            x-on:keydown.escape="if (abierto) { $event.stopPropagation(); abierto = false }"
            x-on:keydown.backspace="retroceso()"
        >

        <ul
            id="{{ $id }}-lista"
            role="listbox"
            aria-label="Competencias sugeridas"
            class="absolute left-0 right-0 z-20 m-0 mt-1 max-h-64 list-none overflow-auto rounded border border-divider bg-surface p-1 shadow-md"
            x-show="abierto && opciones.length > 0"
            x-cloak
        >
            <template x-for="(opcion, posicion) in opciones" :key="opcion.tipo + opcion.nombre">
                <li
                    :id="'{{ $id }}-opcion-' + posicion"
                    role="option"
                    :aria-selected="(activa === posicion).toString()"
                    class="flex cursor-pointer items-baseline justify-between gap-3 rounded px-3 py-2 text-14"
                    :class="activa === posicion ? 'bg-accent-100' : 'hover:bg-neutral-100'"
                    x-on:mousedown.prevent="agregar(opcion)"
                    x-on:mousemove="activa = posicion"
                >
                    <span>
                        <span x-show="opcion.tipo === 'nueva'">Crear «</span><span class="font-semibold" x-text="opcion.nombre"></span><span x-show="opcion.tipo === 'nueva'">»</span>
                    </span>
                    <span class="text-12 text-neutral-700" x-text="opcion.detalle"></span>
                </li>
            </template>
        </ul>
    </div>

    <p class="field-hint m-0">
        Elige del catálogo para que el matching reconozca la competencia aunque el CV use otro nombre (por ejemplo «PowerBI» para Power BI).
        Enter agrega, × quita. Podrás ajustar tipo, peso y años de cada una antes de publicar.
    </p>
</div>
