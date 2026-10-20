{{-- «Selección»: Kanban del prototipo (TalentCloud App.dc.html, isKanban) de las postulaciones de una vacante. --}}
@use('App\Enums\EtapaPostulacion')
<div>
    @if (! $vacante)
        <x-ui.page-header title="Selección" subtitle="Seguimiento de postulaciones por vacante." />
        <x-ui.empty-state ilustracion="primera-vacante" titulo="Aún no hay nada que seleccionar" message="Cuando tengas una vacante abierta, aquí verás a sus postulantes en el tablero, de «Postulado» a «Contratado».">
            @can('create', App\Models\Vacante::class)
                <x-ui.button variant="primary" :href="route('vacantes.index', ['nueva' => 1])" wire:navigate class="mt-4">Crear una vacante</x-ui.button>
            @else
                <x-ui.button :href="route('vacantes.index')" wire:navigate class="mt-4">Ir a Vacantes</x-ui.button>
            @endcan
        </x-ui.empty-state>
    @else
        <x-ui.page-header :title="'Selección · '.$vacante->titulo" :subtitle="$vacante->empresa?->nombre.' — '.$activas.' '.($activas === 1 ? 'postulación activa' : 'postulaciones activas').'.'">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => route('vacantes.index')], ['label' => $vacante->titulo, 'href' => route('vacantes.show', $vacante)], ['label' => 'Selección']]" />
            </x-slot:breadcrumbs>
            @if ($hayOtrasVacantes)
                <x-slot:actions>
                    <label for="seleccion-vacante" class="sr-only">Cambiar de vacante</label>
                    <x-ui.buscador
                        id="seleccion-vacante"
                        metodo="buscarVacantes"
                        :etiqueta="App\Support\Formato::vacante($vacante)"
                        placeholder="Buscar otra vacante…"
                        class="w-[280px] max-w-full"
                        x-on:elegido="Livewire.navigate('{{ url('seleccion') }}/' + $event.detail.id)"
                    />

                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @error('tablero')
            <p class="field-error -mt-3 mb-4 text-14" role="alert">{{ $message }}</p>
        @enderror

        {{-- Cerrada = nadie la mueve; abierta pero sin edición (admin SaaS) ya lo dice el aviso de supervisión. --}}
        @if ($vacanteCerrada)
            <p class="-mt-3 mb-4 text-13 text-neutral-600">La vacante está cerrada: la selección es de solo lectura.</p>
        @endif

        <x-ui.actualizable target="verMas,mover">
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach ($columnas as $columna)
                @php($etapa = $columna['etapa'])
                <section class="flex min-w-[190px] flex-1 basis-0 flex-col gap-3" aria-label="{{ $etapa->etiqueta() }}" wire:key="columna-{{ $etapa->value }}">
                    <div class="flex justify-between text-12 uppercase tracking-[0.06em] text-neutral-600">
                        <span>{{ $etapa->etiqueta() }}</span><span>{{ number_format($columna['total'], 0, ',', ' ') }}</span>
                    </div>

                    <div
                        class="flex min-h-[90px] flex-col gap-3"
                        data-etapa="{{ $etapa->value }}"
                        x-data="columnaKanban(@js($editable))"
                    >
                        @foreach ($columna['postulaciones'] as $postulacion)
                            @php($arrastrable = $editable && ! $postulacion->estaRetirada())
                            @php($enCurso = ! in_array($etapa, [EtapaPostulacion::Contratado, EtapaPostulacion::Descartado], true))
                            @php($dias = (int) ($postulacion->etapa_cambiada_at ?? $postulacion->created_at)->diffInDays(now()))
                            @php($proxima = $postulacion->proxima_entrevista ? \Illuminate\Support\Carbon::parse($postulacion->proxima_entrevista) : null)
                            <div
                                class="card elev-sm tarjeta-clicable relative !gap-1 !p-3 {{ $arrastrable ? 'tarjeta-arrastrable' : '' }}"
                                data-postulacion="{{ $postulacion->id }}"
                                @unless ($arrastrable) data-fija @endunless
                                wire:key="postulacion-{{ $postulacion->id }}"
                                wire:click="abrir({{ $postulacion->id }})"
                            >
                                @if ($editable && $etapa !== EtapaPostulacion::Descartado)
                                    <button
                                        type="button"
                                        aria-label="Descartar a {{ $postulacion->candidato?->nombre }}"
                                        wire:click.stop="confirmarDescarte({{ $postulacion->id }})"
                                        class="absolute right-[2px] top-[2px] grid h-6 w-6 place-items-center text-16 leading-none text-neutral-600 hover:text-accent-2-700"
                                    >×</button>
                                @endif
                                {{-- El nombre es el botón de la tarjeta (teclado y lectores de pantalla); la tarjeta
                                     también se abre con un clic en cualquier parte. Sin botones anidados. --}}
                                <div class="flex items-center gap-2 pr-4">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent-100 text-12 font-semibold text-accent-800" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $postulacion->candidato?->nombre, 0, 1)) }}</span>
                                    <button
                                        type="button"
                                        wire:click.stop="abrir({{ $postulacion->id }})"
                                        aria-label="{{ $postulacion->candidato?->nombre }}, {{ $etapa->etiqueta() }}"
                                        class="block min-w-0 text-left text-14 font-semibold leading-tight text-ink"
                                    >{{ $postulacion->candidato?->nombre }}</button>
                                </div>
                                <div class="text-13 text-neutral-700">{{ $postulacion->candidato?->titular }}</div>
                                @if ($postulacion->estaRetirada())
                                    <div class="mt-1"><x-ui.tag tone="outline">Retirada</x-ui.tag></div>
                                @endif
                                @if (! $postulacion->estaRetirada())
                                    @if ($proxima)
                                        <div class="text-12 text-neutral-700">Entrevista: {{ App\Support\Formato::fechaHora($proxima) }}</div>
                                    @elseif ($enCurso && $dias !== null)
                                        <div class="text-12 text-neutral-600">{{ $dias === 0 ? 'En esta etapa desde hoy' : 'En esta etapa hace '.$dias.($dias === 1 ? ' día' : ' días') }}</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                        @if ($columna['postulaciones']->isEmpty())
                            {{-- Se pinta solo sin tarjetas: no estorba al arrastrar (Sortable solo mueve las que llevan data-postulacion). --}}
                            <div class="columna-vacia">{{ $editable ? 'Arrastra una tarjeta aquí' : 'Sin postulaciones' }}</div>
                        @endif
                    </div>
                    @if ($columna['restantes'] > 0 && $columna['enElTope'])
                        <p class="m-0 text-13 text-neutral-700">Se muestran las primeras {{ number_format(App\Livewire\Seleccion\Central::MAXIMO_POR_COLUMNA, 0, ',', ' ') }}. Para ver el resto, abre la ficha de cada candidato desde Candidatos.</p>
                    @elseif ($columna['restantes'] > 0)
                        {{-- Una vacante masiva tiene miles por columna: se cargan de a 25. --}}
                        <x-ui.button size="sm" wire:click="verMas('{{ $etapa->value }}')" wire:loading.attr="disabled" wire:target="verMas('{{ $etapa->value }}')">
                            Ver más ({{ number_format(min($columna['restantes'], App\Livewire\Seleccion\Central::TARJETAS_POR_COLUMNA), 0, ',', ' ') }} de {{ number_format($columna['restantes'], 0, ',', ' ') }} restantes)
                        </x-ui.button>
                    @endif
                </section>
            @endforeach
        </div>
        </x-ui.actualizable>

        {{-- Detalle de la tarjeta (prototipo: openCard) + "Mover a…" --}}
        <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->candidato?->nombre" max-width="480px">
            @if ($seleccionada)
                <p class="m-0 whitespace-pre-line">{{ $seleccionada->candidato?->titular ?? 'Candidato' }}.
Columna: {{ $seleccionada->etapa->etiqueta() }}.@if ($seleccionada->estaRetirada())

Retiró su postulación el {{ App\Support\Formato::fechaCorta($seleccionada->retirada_at) }}.@endif</p>
                <p class="m-0 mt-3 text-14">
                    <a href="{{ route('postulaciones.show', $seleccionada) }}#notas" wire:navigate>Notas del equipo ({{ $seleccionada->notas_count }})</a>
                </p>

                @if ($editable && ! $seleccionada->estaRetirada())
                    <form wire:submit="moverA" id="form-mover" class="mt-4">
                        <x-ui.field label="Mover a" for="mover-etapa" error="etapaDestino">
                            <x-ui.select wire:model="etapaDestino" id="mover-etapa" :options="$etapas" class="w-full" />
                        </x-ui.field>
                    </form>
                @endif
            @endif
            <x-slot:actions>
                <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
                @if ($seleccionada)
                    <x-ui.button :href="route('candidatos.show', $seleccionada->candidato_id)" wire:navigate>Ver candidato</x-ui.button>
                    <x-ui.button :href="route('postulaciones.show', $seleccionada)" wire:navigate>Ver postulación</x-ui.button>
                    @if ($editable && $seleccionada->etapa !== EtapaPostulacion::Descartado)
                        @can('create', App\Models\Entrevista::class)
                            <x-ui.button :href="route('entrevistas.index', ['programar' => $seleccionada->id])" wire:navigate>Programar entrevista</x-ui.button>
                        @endcan
                    @endif
                    @if ($puedeEvaluar)
                        <x-ui.button :href="route('evaluaciones.index', ['evaluar' => $seleccionada->id])" wire:navigate>Evaluar</x-ui.button>
                    @endif
                    @if ($editable && ! $seleccionada->estaRetirada())
                        <x-ui.button type="submit" form="form-mover" variant="primary" wire:loading.attr="disabled" wire:target="moverA">Mover</x-ui.button>
                    @endif
                @endif
            </x-slot:actions>
        </x-ui.dialog>

        {{-- Descartar (textos del prototipo) --}}
        @php($aDescartar = $seleccionada?->candidato?->nombre ?? '')
        <x-ui.confirm wire:model="mostrarDescarte" :title="'Descartar a '.$aDescartar" action-label="Descartar" action="descartar">
            El candidato se moverá fuera del proceso de esta vacante. Esta acción no elimina su perfil ni su CV.
        </x-ui.confirm>
    @endif
</div>
