{{-- "Matching inteligente" del prototipo (TalentCloud App.dc.html, isMatching). --}}
@use('App\Enums\MotorMatching')
@use('App\Enums\NivelCoincidencia')
<div @if ($recalculando) wire:poll.2s="esperar" @elseif ($procesando) wire:poll.5s="esperar" @endif>
    @php($candidato = $postulacion->candidato)
    @php($vacante = $postulacion->vacante)
    @php($subtitulo = $candidato?->nombre.' — '.$vacante?->titulo.' @ '.$vacante?->empresa?->nombre.($resultado ? ' · '.$resultado->scoreRedondeado().'% de coincidencia global.' : '.'))

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
        <x-ui.empty-state icon="sparkle" :message="$procesando || $recalculando ? 'Estamos analizando el CV y calculando la coincidencia…' : ($aviso['texto'] ?? 'Todavía no hay coincidencia calculada: falta un CV con texto o la vacante no tiene requisitos.')">
            @if ($puedeEditar && ! $procesando && ! $recalculando)
                <x-ui.button wire:click="recalcular" class="mt-3">Calcular ahora</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        {{-- Resumen: el puntaje, cuánto del puesto cubre el CV y qué requiere atención. --}}
        <x-ui.card class="mb-6 max-w-[980px] !p-5">
            <div class="flex flex-wrap items-center gap-6">
                <x-matching.indicador :valor="$resultado->scoreRedondeado()" />
                <div class="min-w-[240px] flex-1">
                    <h2 class="m-0 text-18">{{ $explicacion->conteo(NivelCoincidencia::Coincide) }} de {{ $explicacion->total() }} {{ $explicacion->total() === 1 ? 'requisito respaldado' : 'requisitos respaldados' }} por el CV</h2>
                    <ul class="m-0 mt-2 flex list-none flex-wrap gap-2 p-0">
                        <li><x-ui.tag tone="accent">{{ $explicacion->conteo(NivelCoincidencia::Coincide) }} coinciden</x-ui.tag></li>
                        <li><x-ui.tag tone="neutral">{{ $explicacion->conteo(NivelCoincidencia::Revisar) }} por revisar</x-ui.tag></li>
                        <li><x-ui.tag tone="accent-2">{{ $explicacion->conteo(NivelCoincidencia::NoEncontrado) }} no encontrados</x-ui.tag></li>
                    </ul>
                    @if ($explicacion->obligatoriosFaltantes() !== [])
                        <p class="m-0 mt-3 text-14 font-semibold text-accent-2-700">
                            Falta {{ count($explicacion->obligatoriosFaltantes()) === 1 ? 'un requisito obligatorio' : count($explicacion->obligatoriosFaltantes()).' requisitos obligatorios' }}: {{ implode(', ', $explicacion->obligatoriosFaltantes()) }}.
                        </p>
                    @else
                        <p class="m-0 mt-3 text-14 text-neutral-800">El CV no deja sin mencionar ningún requisito obligatorio.</p>
                    @endif
                    @if ($posicion !== null)
                        <p class="m-0 mt-2 text-13 text-neutral-700">
                            Puesto {{ $posicion }} de {{ $totalDeLaVacante }} {{ $totalDeLaVacante === 1 ? 'candidato calculado' : 'candidatos calculados' }} en esta vacante.
                            <a href="{{ route('seleccion.show', ['vacante' => $postulacion->vacante_id, 'vista' => 'ranking']) }}" wire:navigate>Ver el ranking</a>
                        </p>
                    @endif
                    {{-- Estado del último cálculo; es el único aviso de «Recalcular», así que se anuncia a lectores de pantalla. --}}
                    <p class="m-0 mt-2 text-13 text-neutral-700" role="status">
                        @if ($recalculando)
                            <span class="font-semibold text-accent-700">Recalculando con la IA…</span> Puede tardar unos segundos; esta pantalla se actualiza sola.
                        @elseif ($aviso)
                            <span @class(['font-semibold', 'text-accent-700' => $aviso['tono'] === 'ok', 'text-accent-2-700' => $aviso['tono'] !== 'ok'])>{{ $aviso['texto'] }}</span>
                        @endif
                        @if ($postulacion->match_calculado_at)
                            Último cálculo: {{ App\Support\Formato::fechaHora($postulacion->match_calculado_at) }} ·
                            {{ $resultado->motor === MotorMatching::Http ? 'análisis semántico con IA' : 'análisis básico por palabras clave' }}.
                        @endif
                    </p>
                </div>
            </div>
        </x-ui.card>

        {{-- Cómo se llega al porcentaje: el puntaje es el promedio de los requisitos ponderado por su peso. --}}
        <section class="mb-6 grid max-w-[980px] items-start gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" aria-labelledby="como-titulo">
            <div>
                <h2 id="como-titulo" class="mb-1 text-16">Cómo se llega al {{ $resultado->scoreRedondeado() }} %</h2>
                <p class="mb-3 text-13 text-neutral-700">Cada requisito pesa según la importancia que le diste en la vacante. «Aporta» son los puntos que suma al total sobre el máximo que podía sumar.</p>
                <x-ui.table>
                    <x-slot:head>
                        <th>Requisito</th><th>Peso</th><th>Coincidencia</th><th>Aporta</th>
                    </x-slot:head>
                    @foreach ($explicacion->filas as $fila)
                        <tr wire:key="aporte-{{ $fila['id'] }}">
                            <td>
                                {{ $fila['competencia'] }}
                                @if ($fila['obligatorio'])<span class="text-12 text-neutral-700">· obligatorio</span>@endif
                                @if ($fila['anios_minimos'])<span class="block text-12 text-neutral-700">mín. {{ $fila['anios_minimos'] }} {{ $fila['anios_minimos'] === 1 ? 'año' : 'años' }}</span>@endif
                            </td>
                            <td>{{ $fila['peso'] }}</td>
                            <td class="min-w-[110px]">
                                <span class="mr-1 text-13">{{ $fila['porcentaje'] }}%</span>
                                <x-ui.estado :valor="$fila['nivel']" />
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold">{{ str_replace('.', ',', (string) $fila['aporta']) }}</span>
                                <span class="text-13 text-neutral-700">de {{ str_replace('.', ',', (string) $fila['maximo']) }}</span>
                                <x-ui.progress :value="$fila['maximo'] > 0 ? $fila['aporta'] * 100 / $fila['maximo'] : 0" :label="$fila['competencia'].': aporta '.$fila['aporta'].' de '.$fila['maximo'].' puntos'" class="mt-1" />
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="3" class="font-semibold">Total</td>
                        <td class="font-semibold">{{ str_replace('.', ',', (string) round($resultado->score, 1)) }} de 100</td>
                    </tr>
                </x-ui.table>
            </div>
            <x-ui.card class="items-center">
                <div class="card-kicker self-start">Coincidencia por requisito</div>
                <x-matching.radar :puntos="collect($explicacion->filas)->map(fn ($f) => ['etiqueta' => $f['competencia'], 'valor' => $f['porcentaje']])->all()" />
                @if ($explicacion->total() < 3)
                    <p class="m-0 text-13 text-neutral-700">Con menos de 3 requisitos no hay radar: mira la tabla.</p>
                @endif
            </x-ui.card>
        </section>

        {{-- Fortalezas y brechas, cada una con su porqué. --}}
        <section class="mb-6 grid max-w-[980px] items-start gap-6 md:grid-cols-2" aria-label="Fortalezas y brechas">
            <x-ui.card>
                <div class="card-kicker">Lo que respalda el CV</div>
                @forelse ($explicacion->fortalezas() as $fila)
                    <div class="flex items-baseline justify-between gap-3 text-14" wire:key="fortaleza-{{ $fila['id'] }}">
                        <span>{{ $fila['competencia'] }}@if ($fila['obligatorio']) <span class="text-12 text-neutral-700">· obligatorio</span>@endif</span>
                        <span class="shrink-0 text-13 font-semibold text-accent-700">+{{ str_replace('.', ',', (string) $fila['aporta']) }} pts</span>
                    </div>
                @empty
                    <p class="m-0 text-14 text-neutral-700">Ningún requisito está respaldado con claridad todavía.</p>
                @endforelse
            </x-ui.card>
            <x-ui.card>
                <div class="card-kicker">Lo que falta o conviene comprobar</div>
                @forelse ($explicacion->brechas() as $fila)
                    <div class="flex items-baseline justify-between gap-3 text-14" wire:key="brecha-{{ $fila['id'] }}">
                        <span>{{ $fila['competencia'] }}@if ($fila['obligatorio']) <span class="text-12 font-semibold text-accent-2-700">· obligatorio</span>@endif</span>
                        <x-ui.estado :valor="$fila['nivel']" />
                    </div>
                @empty
                    <p class="m-0 text-14 text-neutral-700">El CV respalda todos los requisitos de la vacante.</p>
                @endforelse
            </x-ui.card>
        </section>

        {{-- Preguntas para la entrevista: reglas fijas sobre las brechas (no usa la IA, por eso son explicables). --}}
        @if ($explicacion->preguntasSugeridas() !== [])
            <x-ui.card class="mb-6 max-w-[980px]">
                <div class="card-kicker">Preguntas sugeridas para la entrevista</div>
                <ol class="m-0 grid list-decimal gap-2 pl-5 text-14 text-neutral-800">
                    @foreach ($explicacion->preguntasSugeridas() as $pregunta)
                        <li wire:key="pregunta-{{ $loop->index }}">{{ $pregunta }}</li>
                    @endforeach
                </ol>
                <p class="m-0 text-12 text-neutral-700">Se arman a partir de lo que el CV no respalda o respalda a medias. Son un punto de partida; adáptalas a la conversación.</p>
            </x-ui.card>
        @endif

        <h2 class="mb-3 text-16">Evidencia por requisito</h2>
        <div class="grid max-w-[980px] gap-3">
            @foreach ($resultado->requisitos as $requisito)
                @php($marcado = in_array($requisito->requisitoId, $marcados, true))
                @php($obligatorioFaltante = $requisito->nivel === NivelCoincidencia::NoEncontrado && in_array($requisito->requisitoId, $obligatorios, true))
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
                            <x-ui.button variant="ghost" size="sm" class="mt-2 self-start" wire:click="marcarIncorrecto({{ $requisito->requisitoId }}, {{ $marcado ? 'false' : 'true' }})">
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
        @elseif ($postulacion->ia_sin_cupo)
            {{-- Lo evaluó CalcularMatch sin lugar en el cupo mensual de IA del plan (CupoDeIa). --}}
            <p class="mt-4 max-w-[60ch] text-13 text-neutral-600">Análisis básico: cuando se calculó, el cupo mensual de matching con IA del plan de {{ $vacante?->empresa?->nombre ?? 'la empresa' }} estaba agotado. Los porcentajes se calculan por coincidencia de palabras clave entre el CV y los requisitos; «Recalcular coincidencia» usa la IA si vuelve a haber cupo. Cada resultado muestra su evidencia textual; la decisión final permanece bajo control humano.</p>
        @else
            <p class="mt-4 max-w-[60ch] text-13 text-neutral-600">Análisis básico: los porcentajes se calculan por coincidencia de palabras clave entre el CV y los requisitos, mientras el análisis semántico con IA no está disponible. Cada resultado muestra su evidencia textual; la decisión final permanece bajo control humano.</p>
        @endif

        @if ($puedeEditar)
            <x-ui.button variant="ghost" size="sm" class="mt-2" wire:click="recalcular" :disabled="$recalculando" wire:loading.attr="disabled" wire:target="recalcular">
                {{ $recalculando ? 'Recalculando…' : 'Recalcular coincidencia' }}
            </x-ui.button>
        @endif
    @endif

    @can('gestionarNotas', $postulacion)
        <section id="notas" class="mt-8 max-w-[640px]">
            <h2 class="mb-1 text-16">Notas del equipo</h2>
            <p class="mb-3 text-13 text-neutral-700">Solo las ve tu equipo: nunca el candidato ni otras empresas.</p>
            <livewire:postulaciones.notas :postulacion-id="$postulacion->id" :key="'notas-'.$postulacion->id" />
        </section>
    @endcan
</div>
