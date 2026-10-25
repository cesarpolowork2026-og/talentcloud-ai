{{-- Una postulación: etapa, CV, requisitos de la vacante, entrevistas, evaluaciones y notas internas del equipo. --}}
<div>
    <x-ui.se-actualiza />
    @php($candidato = $postulacion->candidato)
    @php($vacante = $postulacion->vacante)

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
                @if ($cvMasReciente)
                    <p class="m-0 text-13 text-neutral-700">Es el CV con el que postuló. Después subió otro ({{ $cvMasReciente->nombre_original }}, {{ \App\Support\Formato::fechaCorta($cvMasReciente->created_at) }}): está en su ficha de candidato.</p>
                @endif
            @else
                <p class="m-0 text-14 text-neutral-700">Este candidato todavía no tiene un CV cargado.</p>
            @endif
        </x-ui.card>
    </div>

    @if ($cobertura->total() > 0)
        <x-ui.card class="mb-6 max-w-[980px]" kicker="Requisitos de la vacante frente al CV">
            <p class="m-0 text-14">
                <strong>Cubre {{ $cobertura->cubiertos() }} de {{ $cobertura->total() }} requisitos</strong>
                @if ($cobertura->obligatoriosFaltantes() !== [])
                    · falta{{ count($cobertura->obligatoriosFaltantes()) === 1 ? '' : 'n' }} {{ implode(', ', $cobertura->obligatoriosFaltantes()) }} (obligatorio{{ count($cobertura->obligatoriosFaltantes()) === 1 ? '' : 's' }})
                @endif
            </p>
            @unless ($cobertura->conCv)
                <p class="m-0 text-13 text-neutral-700">Este candidato no tiene un CV con texto que revisar: solo cuentan las competencias que anotó tu equipo.</p>
            @endunless
            <ul class="m-0 grid list-none gap-3 p-0">
                @foreach ($cobertura->items as $item)
                    <li class="grid gap-1" wire:key="requisito-{{ $item['requisito']->id }}">
                        <div class="flex flex-wrap items-center gap-2 text-14">
                            <span aria-hidden="true" class="{{ $item['cubierto'] ? 'text-accent-700' : 'text-accent-2-700' }}">{{ $item['cubierto'] ? '✓' : '✗' }}</span>
                            <span class="font-semibold">{{ $item['requisito']->competencia?->nombre }}</span>
                            <x-ui.tag :tone="$item['requisito']->tipo->tono()">{{ $item['requisito']->tipo->etiqueta() }}</x-ui.tag>
                            <span class="text-13 text-neutral-700">
                                @if ($item['via'] === 'cv') Aparece en el CV
                                @elseif ($item['via'] === 'anotada') Anotada por tu equipo
                                @else <span class="sr-only">Resultado: </span>No se encontró @endif
                            </span>
                        </div>
                        @if ($item['fragmento'])
                            <blockquote class="m-0 border-l-2 border-divider pl-3 text-13 text-neutral-800">{{ $item['fragmento'] }}</blockquote>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="m-0 text-13 text-neutral-700">Análisis básico: busca el nombre de la competencia (o sus sinónimos del catálogo) como palabra completa en el CV. Que no aparezca no significa que la persona no la tenga: revisa el CV y decide tú.</p>
        </x-ui.card>
    @endif

    @if ($cv && $textoDelCv !== '')
        <x-ui.card class="mb-6 max-w-[980px]" kicker="Texto del CV">
            <p class="m-0 text-13 text-neutral-700">Texto extraído de «{{ $cv->nombre_original }}». Se resaltan las competencias que pide la vacante. El archivo original es el que vale: descárgalo si algo no se ve bien.</p>
            <div class="max-h-[420px] overflow-y-auto whitespace-pre-line rounded border border-divider bg-white p-3 text-14 leading-relaxed" tabindex="0" role="region" aria-label="Texto del CV de {{ $candidato?->nombre }}">@foreach ($tramosDelCv as $tramo)@if ($tramo['marcado'])<mark class="rounded-sm bg-accent-100 px-[2px] text-ink">{{ $tramo['texto'] }}</mark>@else{{ $tramo['texto'] }}@endif@endforeach</div>
        </x-ui.card>
    @elseif ($cv)
        <x-ui.card class="mb-6 max-w-[980px]" kicker="Texto del CV">
            <p class="m-0 text-14 text-neutral-700">No se pudo leer el texto de este CV (por ejemplo, un PDF escaneado como imagen). Descárgalo para revisarlo.</p>
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
