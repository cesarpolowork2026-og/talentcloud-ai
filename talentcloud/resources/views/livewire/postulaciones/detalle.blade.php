{{-- Una postulación: etapa, CV, requisitos de la vacante, entrevistas, evaluaciones y notas internas del equipo. --}}
<div>
    @php($candidato = $postulacion->candidato)
    @php($vacante = $postulacion->vacante)
    @php($cv = $candidato?->cvActual)

    <x-ui.page-header title="Postulación" :subtitle="$candidato?->nombre.' — '.$vacante?->titulo.' · '.$vacante?->empresa?->nombre">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[
                ['label' => 'Candidatos', 'href' => route('candidatos.index')],
                ['label' => $candidato?->nombre, 'href' => $candidato ? route('candidatos.show', $candidato) : null],
                ['label' => 'Postulación'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button :href="route('seleccion.show', $postulacion->vacante_id)" wire:navigate>Ir a la selección</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid max-w-[980px] gap-4 md:grid-cols-2">
        <x-ui.card kicker="Estado de la postulación">
            <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-14">
                <dt class="text-neutral-700">Etapa</dt>
                <dd class="m-0">
                    @if ($postulacion->estaRetirada())
                        <x-ui.tag tone="outline">Retirada por el candidato</x-ui.tag>
                    @else
                        <x-ui.estado :valor="$postulacion->etapa" />
                    @endif
                </dd>
                <dt class="text-neutral-700">Postuló el</dt>
                <dd class="m-0">{{ \App\Support\Formato::fechaCorta($postulacion->created_at) }}</dd>
                <dt class="text-neutral-700">Origen</dt>
                <dd class="m-0">{{ $postulacion->origen->etiqueta() }}</dd>
                <dt class="text-neutral-700">Vacante</dt>
                <dd class="m-0"><a href="{{ route('vacantes.show', $postulacion->vacante_id) }}" wire:navigate>{{ $vacante?->titulo }}</a></dd>
            </dl>
        </x-ui.card>

        <x-ui.card kicker="Currículum">
            @if ($cv)
                <p class="m-0 text-14">{{ $cv->nombre_original }}</p>
                <div>
                    <x-ui.button size="sm" :href="route('cvs.descargar', $cv)">Descargar CV</x-ui.button>
                </div>
            @else
                <p class="m-0 text-14 text-neutral-700">Este candidato todavía no tiene un CV cargado.</p>
            @endif
        </x-ui.card>
    </div>

    @if ($vacante && $vacante->requisitos->isNotEmpty())
        <x-ui.card class="mb-6 max-w-[980px]" kicker="Lo que pide la vacante">
            <div class="flex flex-wrap gap-2" role="list">
                @foreach ($vacante->requisitos as $requisito)
                    <span role="listitem">
                        <x-ui.tag :tone="$requisito->tipo->tono()">{{ $requisito->competencia?->nombre }} · {{ $requisito->tipo->etiqueta() }}</x-ui.tag>
                    </span>
                @endforeach
            </div>
            <p class="m-0 text-13 text-neutral-700">Compáralos con el CV y las competencias del candidato; la decisión es del equipo de selección.</p>
        </x-ui.card>
    @endif

    <div class="mb-6 grid max-w-[980px] gap-4 md:grid-cols-2">
        <x-ui.card kicker="Entrevistas">
            @forelse ($entrevistas as $entrevista)
                <div class="flex flex-wrap items-baseline justify-between gap-2 text-14" wire:key="entrevista-{{ $entrevista->id }}">
                    <span>{{ \App\Support\Formato::fechaHora($entrevista->inicia_at) }} · {{ $entrevista->entrevistador?->name }}</span>
                    <x-ui.estado :valor="$entrevista->estado" />
                </div>
            @empty
                <p class="m-0 text-14 text-neutral-700">Aún no hay entrevistas programadas.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card kicker="Evaluaciones">
            @forelse ($evaluaciones as $evaluacion)
                <div class="flex flex-wrap items-baseline justify-between gap-2 text-14" wire:key="evaluacion-{{ $evaluacion->id }}">
                    <span>{{ $evaluacion->evaluador?->name }}@if ($evaluacion->puntaje !== null) · {{ str_replace('.', ',', (string) $evaluacion->puntaje) }}/10 @endif</span>
                    <x-ui.estado :valor="$evaluacion->estado" />
                </div>
            @empty
                <p class="m-0 text-14 text-neutral-700">Aún no hay evaluaciones registradas.</p>
            @endforelse
        </x-ui.card>
    </div>

    @if ($otrosProcesos->isNotEmpty())
        <x-ui.card class="mb-6 max-w-[980px]" kicker="Otros procesos de este candidato">
            @foreach ($otrosProcesos as $otro)
                <p class="m-0 text-14" wire:key="otro-{{ $otro->id }}">
                    <a href="{{ route('postulaciones.show', $otro) }}" wire:navigate>{{ \App\Support\Formato::vacante($otro->vacante) }}</a>
                    · {{ $otro->estaRetirada() ? 'Retirada' : $otro->etapa->etiqueta() }}
                </p>
            @endforeach
        </x-ui.card>
    @endif

    @can('gestionarNotas', $postulacion)
        <section id="notas" class="mt-8 max-w-[640px]">
            <h2 class="mb-1 text-16">Notas del equipo</h2>
            <p class="mb-3 text-13 text-neutral-700">Solo las ve tu equipo: nunca el candidato ni otras empresas.</p>
            <livewire:postulaciones.notas :postulacion-id="$postulacion->id" :key="'notas-'.$postulacion->id" />
        </section>
    @endcan
</div>
