{{-- "Dashboard" del prototipo (TalentCloud App.dc.html, isDashboard). --}}
<div>
    <x-ui.page-header title="Dashboard" :subtitle="$subtitulo" />

    <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <x-ui.stat label="Vacantes activas" :value="$resumen->vacantesActivas" />
        <x-ui.stat label="Candidatos evaluados" :value="$resumen->candidatosEvaluados" />
        <x-ui.stat
            label="Match promedio"
            :value="$resumen->matchPromedio === null ? '—' : $resumen->matchPromedio.'%'"
            highlight
            wire:click="$set('mostrarDetalleDelMatch', true)"
        />
        <x-ui.stat label="Entrevistas hoy" :value="$resumen->entrevistasHoy" />
    </div>

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section>
            <h2 class="mb-3 text-16">Vacantes recientes</h2>
            @if ($resumen->vacantesRecientes->isEmpty())
                <x-ui.empty-state icon="briefcase" message="Todavía no hay vacantes." />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Vacante</th><th>Empresa</th><th>Candidatos</th><th>Estado</th>
                    </x-slot:head>
                    @foreach ($resumen->vacantesRecientes as $vacante)
                        <tr class="tc-row" wire:key="reciente-{{ $vacante->id }}" wire:click="verVistaPrevia({{ $vacante->id }})">
                            <td>{{ $vacante->titulo }}</td>
                            <td>{{ $vacante->empresa?->nombre }}</td>
                            <td>{{ $vacante->postulaciones_count }}</td>
                            <td><x-ui.estado :valor="$vacante->estado" /></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-16">Actividad reciente</h2>
            @forelse ($resumen->actividad as $actividad)
                <div class="mb-3 flex gap-2 text-14" wire:key="actividad-{{ $actividad->id }}">
                    <x-ui.icon name="star-four" :size="15" class="mt-[3px] text-accent" />
                    <span class="text-neutral-800">{{ $actividad->descripcion }}</span>
                </div>
            @empty
                <p class="m-0 text-14 text-neutral-600">Aún no hay actividad.</p>
            @endforelse
        </section>
    </div>

    {{-- Embudo de selección (ampliación: no está en el prototipo) --}}
    <section class="mt-8" aria-labelledby="embudo-titulo">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="embudo-titulo" class="m-0 text-16">Embudo de selección</h2>
                <p class="m-0 mt-1 text-13 text-neutral-700">Postulaciones de los últimos {{ $embudo->dias }} días y la etapa más avanzada a la que llegaron.</p>
            </div>
            <div>
                <label for="embudo-vacante" class="sr-only">Filtrar el embudo por vacante</label>
                <x-ui.select wire:model.live="vacanteDelEmbudo" id="embudo-vacante" :options="$vacantesDelEmbudo" placeholder="Todas las vacantes" class="max-w-full" />
            </div>
        </div>

        @if ($embudo->total === 0)
            <x-ui.empty-state icon="funnel" :message="'No hay postulaciones en los últimos '.$embudo->dias.' días.'" />
        @else
            <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-ui.card>
                    <ol class="m-0 grid list-none gap-3 p-0" aria-label="Postulaciones que alcanzaron cada etapa">
                        @foreach ($embudo->etapas as $fila)
                            <li class="grid gap-1" wire:key="embudo-{{ $fila['etapa']->value }}">
                                <div class="flex items-baseline justify-between gap-2 text-14">
                                    <span class="text-neutral-800">{{ $fila['etapa']->etiqueta() }}</span>
                                    <span class="font-semibold">{{ $fila['total'] }} <span class="font-normal text-neutral-700">· {{ $fila['porcentaje'] }}%</span></span>
                                </div>
                                <x-ui.progress :value="$fila['porcentaje']" :label="$fila['etapa']->etiqueta().': '.$fila['total'].' de '.$embudo->total.' postulaciones'" />
                            </li>
                        @endforeach
                    </ol>
                    <p class="m-0 text-13 text-neutral-700">
                        {{ $embudo->descartadas }} {{ $embudo->descartadas === 1 ? 'descartada' : 'descartadas' }} por la empresa y {{ $embudo->retiradas }} {{ $embudo->retiradas === 1 ? 'retirada' : 'retiradas' }} por el candidato: cuentan en las etapas a las que llegaron antes.
                    </p>
                </x-ui.card>

                <div class="grid gap-4">
                    <x-ui.card kicker="Tiempo medio hasta contratar">
                        @if ($embudo->diasHastaContratar === null)
                            <p class="m-0 font-heading text-24 font-semibold">Sin contrataciones aún</p>
                        @else
                            <p class="m-0 font-heading text-32 font-semibold">{{ App\Support\Formato::dias($embudo->diasHastaContratar) }}</p>
                            <p class="m-0 text-13 text-neutral-700">Desde la postulación, en {{ $embudo->contrataciones }} {{ $embudo->contrataciones === 1 ? 'contratación' : 'contrataciones' }} del periodo.</p>
                        @endif
                    </x-ui.card>
                    <x-ui.card kicker="Origen de las postulaciones">
                        <ul class="m-0 grid list-none gap-1 p-0 text-14">
                            @foreach (App\Enums\OrigenPostulacion::cases() as $origen)
                                <li class="flex items-baseline justify-between gap-2">
                                    <span class="text-neutral-800">{{ $origen->etiqueta() }}</span>
                                    <span class="font-semibold">{{ $embudo->postulacionesDe($origen) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </section>

    {{-- Detalle del match promedio (prototipo: openKpiDialog) --}}
    <x-ui.dialog wire:model="mostrarDetalleDelMatch" :title="'Match promedio · '.($resumen->matchPromedio === null ? '—' : $resumen->matchPromedio.'%')" max-width="440px">
        <p class="m-0 whitespace-pre-line">@if ($resumen->postulacionesConMatch > 0)Calculado sobre {{ $resumen->postulacionesConMatch }} {{ $resumen->postulacionesConMatch === 1 ? 'postulación' : 'postulaciones' }} con matching en los últimos {{ $diasDelMatch }} días.@else Todavía no hay postulaciones con matching en los últimos {{ $diasDelMatch }} días.@endif

La IA pondera competencias técnicas, experiencia y evidencia textual del CV.</p>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            <x-ui.button variant="primary" :href="route('matching.index')" wire:navigate>Ver matching</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    <x-vacantes.vista-previa wire:model="mostrarVistaPrevia" :vacante="$seleccionada" />
</div>
