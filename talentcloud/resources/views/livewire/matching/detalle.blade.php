{{-- "Matching inteligente" del prototipo (TalentCloud App.dc.html, isMatching). --}}
@use('App\Enums\MotorMatching')
@use('App\Enums\NivelCoincidencia')
<div @if ($postulacion && ($procesando ?? false)) wire:poll.5s @endif>
    @if (! $postulacion)
        <x-ui.page-header title="Matching inteligente" subtitle="Coincidencia entre los CV y los requisitos de cada vacante." />
        <x-ui.empty-state icon="sparkle" message="Todavía no hay postulaciones con matching calculado.">
            <x-ui.button :href="route('candidatos.index')" wire:navigate class="mt-3">Ir a Candidatos</x-ui.button>
        </x-ui.empty-state>
    @else
        @php
            $candidato = $postulacion->candidato;
            $vacante = $postulacion->vacante;
            $subtitulo = $candidato?->nombre.' — '.$vacante?->titulo.' @ '.$vacante?->empresa?->nombre
                .($resultado ? ' · '.$resultado->scoreRedondeado().'% de coincidencia global.' : '.');
        @endphp

        <x-ui.page-header title="Matching inteligente" :subtitle="$subtitulo">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[
                    ['label' => 'Candidatos', 'href' => route('candidatos.index')],
                    ['label' => $candidato?->nombre, 'href' => $candidato ? route('candidatos.show', $candidato) : null],
                    ['label' => 'Matching'],
                ]" />
            </x-slot:breadcrumbs>
            @if (count($otras) > 1)
                <x-slot:actions>
                    <label for="matching-postulacion" class="sr-only">Ver otro candidato de la vacante</label>
                    <x-ui.select
                        id="matching-postulacion"
                        :options="$otras"
                        :selected="$postulacion->id"
                        x-data
                        x-on:change="Livewire.navigate('{{ url('matching') }}/' + $event.target.value)"
                    />
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($otrosProcesos->isNotEmpty())
            {{-- Admin SaaS: el candidato también está en otros procesos (de esta u otras empresas). --}}
            <aside class="-mt-2 mb-6 max-w-[760px] text-13 text-neutral-800" aria-label="Otros procesos de este candidato">
                <span class="font-semibold">Otros procesos de {{ $candidato?->nombre }}:</span>
                @foreach ($otrosProcesos as $otro)
                    <a href="{{ $otro->match_detalle ? route('matching.show', $otro) : route('candidatos.show', $otro->candidato_id) }}" wire:navigate wire:key="otro-{{ $otro->id }}">{{ App\Support\Formato::vacante($otro->vacante) }}{{ $otro->matchPorcentaje() !== null ? ' ('.$otro->matchPorcentaje().' %)' : '' }}</a>@if (! $loop->last) · @endif
                @endforeach
            </aside>
        @endif

        @if ($resultado === null)
            <x-ui.empty-state icon="sparkle" :message="$procesando ? 'Estamos analizando el CV y calculando la coincidencia…' : 'Todavía no hay coincidencia calculada: falta un CV con texto o la vacante no tiene requisitos.'">
                @if ($puedeEditar && ! $procesando)
                    <x-ui.button wire:click="recalcular" class="mt-3">Calcular ahora</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="grid max-w-[760px] gap-3">
                @foreach ($resultado->requisitos as $requisito)
                    @php
                        $marcado = in_array($requisito->requisitoId, $marcados, true);
                        $obligatorioFaltante = $requisito->nivel === NivelCoincidencia::NoEncontrado && in_array($requisito->requisitoId, $obligatorios, true);
                    @endphp
                    <x-ui.card class="!p-4" x-data="{ abierto: false }" wire:key="requisito-{{ $requisito->requisitoId }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="w-[180px] text-15 font-semibold">
                                {{ $requisito->competencia }}
                                @if ($obligatorioFaltante)
                                    <span class="block text-12 font-normal text-accent-2-700">Requisito obligatorio</span>
                                @endif
                            </span>
                            <x-ui.progress :value="$requisito->porcentaje" :label="'Coincidencia con '.$requisito->competencia.': '.$requisito->porcentaje.'%'" class="min-w-[120px] flex-1" />
                            <x-ui.estado :valor="$requisito->nivel" />
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                x-on:click="abierto = ! abierto"
                                x-bind:aria-expanded="abierto"
                                x-text="abierto ? 'Ocultar evidencia' : 'Ver evidencia'"
                            >Ver evidencia</x-ui.button>
                        </div>

                        <div x-show="abierto" x-cloak class="mt-3 border-t border-divider pt-3">
                            <p class="m-0 text-14 italic text-neutral-800">"{{ $requisito->evidencia ?? $requisito->motivo }}"</p>
                            @if ($requisito->evidencia)
                                <p class="m-0 mt-2 text-13 text-neutral-600">{{ $requisito->motivo }}</p>
                            @endif
                            @if ($puedeEditar)
                                <x-ui.button variant="ghost" size="sm" class="mt-2 self-start" wire:click="marcarIncorrecto({{ $requisito->requisitoId }})">
                                    {{ $marcado ? 'Quitar marca de incorrecto' : 'Marcar como incorrecto' }}
                                </x-ui.button>
                            @endif
                        </div>

                        @if ($marcado)
                            <p class="m-0 text-12 text-accent-2-700">Marcaste este resultado como incorrecto.</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>

            @if ($resultado->motor === MotorMatching::Http)
                <p class="mt-4 max-w-[60ch] text-13 text-neutral-600">Los porcentajes se calculan por correspondencia semántica (embeddings) entre el CV y los requisitos de la vacante. La IA explica cada coincidencia con evidencia textual; la decisión final permanece bajo control humano.</p>
            @else
                <p class="mt-4 max-w-[60ch] text-13 text-neutral-600">Análisis básico: los porcentajes se calculan por coincidencia de palabras clave entre el CV y los requisitos, mientras el análisis semántico con IA no está disponible. Cada resultado muestra su evidencia textual; la decisión final permanece bajo control humano.</p>
            @endif

            @if ($puedeEditar)
                <x-ui.button variant="ghost" size="sm" class="mt-2" wire:click="recalcular">Recalcular coincidencia</x-ui.button>
            @endif
        @endif

        @can('gestionarNotas', $postulacion)
            <section id="notas" class="mt-8 max-w-[640px]">
                <h2 class="mb-1 text-16">Notas del equipo</h2>
                <p class="mb-3 text-13 text-neutral-700">Solo las ve tu equipo: nunca el candidato ni otras empresas.</p>
                <livewire:postulaciones.notas :postulacion-id="$postulacion->id" :key="'notas-'.$postulacion->id" />
            </section>
        @endcan
    @endif
</div>
