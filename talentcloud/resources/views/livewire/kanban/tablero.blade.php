{{-- Kanban del prototipo (TalentCloud App.dc.html, isKanban). --}}
@use('App\Enums\EtapaPostulacion')
<div>
    @if (! $vacante)
        <x-ui.page-header title="Kanban" subtitle="Seguimiento de postulaciones por vacante." />
        <x-ui.empty-state icon="kanban" message="No hay vacantes abiertas para mostrar su Kanban.">
            <x-ui.button :href="route('vacantes.index')" wire:navigate class="mt-3">Ir a Vacantes</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.page-header :title="'Kanban · '.$vacante->titulo" :subtitle="$vacante->empresa?->nombre.' — '.$activas.' '.($activas === 1 ? 'postulación activa' : 'postulaciones activas').'.'">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => route('vacantes.index')], ['label' => $vacante->titulo, 'href' => route('vacantes.show', $vacante)], ['label' => 'Kanban']]" />
            </x-slot:breadcrumbs>
            @if ($hayOtrasVacantes)
                <x-slot:actions>
                    <label for="kanban-vacante" class="sr-only">Cambiar de vacante</label>
                    <x-ui.select
                        id="kanban-vacante"
                        :options="$otrasVacantes"
                        :selected="$vacante->id"
                        x-data
                        x-on:change="Livewire.navigate('{{ url('vacantes') }}/' + $event.target.value + '/kanban')"
                    />

                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @error('tablero')
            <p class="field-error -mt-3 mb-4 text-14" role="alert">{{ $message }}</p>
        @enderror

        @if (! $editable)
            <p class="-mt-3 mb-4 text-13 text-neutral-600">La vacante está cerrada: el Kanban es de solo lectura.</p>
        @endif

        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach ($columnas as $columna)
                @php($etapa = $columna['etapa'])
                <section class="flex flex-[0_0_220px] flex-col gap-3" aria-label="{{ $etapa->etiqueta() }}" wire:key="columna-{{ $etapa->value }}">
                    <div class="flex justify-between text-12 uppercase tracking-[0.06em] text-neutral-600">
                        <span>{{ $etapa->etiqueta() }}</span><span>{{ $columna['postulaciones']->count() }}</span>
                    </div>

                    <div
                        class="flex min-h-[90px] flex-col gap-3"
                        data-etapa="{{ $etapa->value }}"
                        x-data="columnaKanban(@js($editable))"
                    >
                        @foreach ($columna['postulaciones'] as $postulacion)
                            @php($arrastrable = $editable && ! $postulacion->estaRetirada())
                            <div
                                class="card elev-sm relative !p-3 {{ $arrastrable ? 'cursor-grab' : 'cursor-pointer' }}"
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
                                <button
                                    type="button"
                                    wire:click.stop="abrir({{ $postulacion->id }})"
                                    aria-label="{{ $postulacion->candidato?->nombre }}, {{ $etapa->etiqueta() }}"
                                    class="block pr-4 text-left text-14 font-semibold text-ink"
                                >{{ $postulacion->candidato?->nombre }}</button>
                                <div class="mt-0.5 text-13 text-neutral-700">{{ $postulacion->candidato?->titular }}</div>
                                @if ($postulacion->estaRetirada())
                                    <div class="mt-2"><x-ui.tag tone="outline">Retirada</x-ui.tag></div>
                                @elseif ($postulacion->matchPorcentaje() !== null)
                                    <div class="mt-2"><x-ui.tag tone="accent">{{ $postulacion->matchPorcentaje() }}% match</x-ui.tag></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Detalle de la tarjeta (prototipo: openCard) + "Mover a…" --}}
        <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->candidato?->nombre" max-width="480px">
            @if ($seleccionada)
                <p class="m-0 whitespace-pre-line">{{ $seleccionada->candidato?->titular ?? 'Candidato' }}{{ $seleccionada->matchPorcentaje() !== null ? ' · '.$seleccionada->matchPorcentaje().'% de coincidencia con la vacante.' : '.' }}
Columna: {{ $seleccionada->etapa->etiqueta() }}.@if ($seleccionada->estaRetirada())

Retiró su postulación el {{ App\Support\Formato::fechaCorta($seleccionada->retirada_at) }}.@endif</p>
                <p class="m-0 mt-2 text-14">
                    <a href="{{ route('matching.show', $seleccionada) }}#notas" wire:navigate>Notas del equipo ({{ $seleccionada->notas_count }})</a>
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
                    @if ($seleccionada->match_detalle)
                        <x-ui.button :href="route('matching.show', $seleccionada)" wire:navigate>Ver matching</x-ui.button>
                    @endif
                    @if ($editable && $seleccionada->etapa !== EtapaPostulacion::Descartado)
                        @can('create', App\Models\Entrevista::class)
                            <x-ui.button :href="route('entrevistas.index', ['programar' => $seleccionada->id])" wire:navigate>Programar entrevista</x-ui.button>
                        @endcan
                    @endif
                    @can('create', App\Models\Evaluacion::class)
                        <x-ui.button :href="route('evaluaciones.index', ['evaluar' => $seleccionada->id])" wire:navigate>Evaluar</x-ui.button>
                    @endcan
                    @if ($editable && ! $seleccionada->estaRetirada())
                        <x-ui.button type="submit" form="form-mover" variant="primary">Mover</x-ui.button>
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
