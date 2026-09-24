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
