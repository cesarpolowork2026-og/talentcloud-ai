{{-- Ficha del candidato, con los componentes del prototipo. --}}
@use('App\Enums\EstadoProcesamientoCv')
@use('App\Enums\FuenteCompetencia')
@use('App\Support\Formato')
<div>
    <x-ui.page-header :title="$candidato->nombre" :subtitle="collect([$candidato->titular, $candidato->email, $candidato->telefono])->filter()->join(' · ')">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Candidatos', 'href' => route('candidatos.index')], ['label' => $candidato->nombre]]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="grid gap-8">
            {{-- Postulaciones (admin SaaS: procesos agrupados por empresa) --}}
            <section>
                @if ($vistaGlobal)
                    <h2 class="mb-1 text-16">Procesos por empresa</h2>
                    @if ($candidato->postulaciones->isEmpty())
                        <p class="text-14 text-neutral-600">Todavía no postuló a ninguna vacante.</p>
                    @else
                        @php
                            $nProcesos = $candidato->postulaciones->count();
                            $nEmpresas = $procesosPorEmpresa->count();
                        @endphp
                        <p class="mb-4 text-13 text-neutral-700">{{ $nProcesos }} {{ $nProcesos === 1 ? 'proceso' : 'procesos' }} en {{ $nEmpresas }} {{ $nEmpresas === 1 ? 'empresa' : 'empresas' }}. Cada empresa solo ve sus propios procesos.</p>
                        <div class="grid gap-5">
                            @foreach ($procesosPorEmpresa as $empresa => $procesos)
                                <div wire:key="empresa-{{ \Illuminate\Support\Str::slug($empresa) }}">
                                    <h3 class="mb-2 text-15">{{ $empresa }}</h3>
                                    <x-ui.table>
                                        <x-slot:head>
                                            <th>Vacante</th><th>Etapa</th><th>Match</th><th>Fecha</th>
                                        </x-slot:head>
                                        @foreach ($procesos as $postulacion)
                                            <x-candidatos.fila-postulacion :postulacion="$postulacion" wire:key="postulacion-{{ $postulacion->id }}" />
                                        @endforeach
                                    </x-ui.table>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                <h2 class="mb-3 text-16">Postulaciones</h2>
                @if ($candidato->postulaciones->isEmpty())
                    <p class="text-14 text-neutral-600">Todavía no postuló a vacantes de tu empresa.</p>
                @else
                    <x-ui.table>
                        <x-slot:head>
                            <th>Vacante</th><th>Etapa</th><th>Match</th><th>Fecha</th>
                        </x-slot:head>
                        @foreach ($candidato->postulaciones as $postulacion)
                            <x-candidatos.fila-postulacion :postulacion="$postulacion" wire:key="postulacion-{{ $postulacion->id }}" />
                        @endforeach
                    </x-ui.table>
                @endif
                @endif

                @if ($puedeEditar && $vacantesDisponibles)
                    <form wire:submit="postular" class="mt-4 flex flex-wrap items-end gap-3">
                        <x-ui.field label="Postular a una vacante" for="postular-vacante" error="vacanteId" class="min-w-[240px] flex-1">
                            <x-ui.select wire:model="vacanteId" id="postular-vacante" :options="$vacantesDisponibles" placeholder="Elige una vacante abierta" class="w-full" error="vacanteId" />
                        </x-ui.field>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="postular">Postular</x-ui.button>
                    </form>
                @endif
            </section>

            {{-- Notas internas del equipo, por postulación de esta empresa --}}
            @if ($conNotas->isNotEmpty())
                <section>
                    <h2 class="mb-1 text-16">Notas del equipo</h2>
                    <p class="mb-3 text-13 text-neutral-700">{{ $vistaGlobal ? 'Notas internas de cada empresa sobre su proceso: nunca las ve el candidato ni las demás empresas.' : 'Solo las ve tu equipo: nunca el candidato ni otras empresas.' }}</p>
                    <div class="grid gap-6">
                        @foreach ($conNotas as $postulacion)
                            <div>
                                <h3 class="mb-2 text-15">{{ Formato::vacante($postulacion->vacante) }}</h3>
                                <livewire:postulaciones.notas :postulacion-id="$postulacion->id" :key="'notas-'.$postulacion->id" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Competencias --}}
            <section>
                <h2 class="mb-1 text-16">Competencias</h2>
                @if ($vistaGlobal)
                    {{-- Admin SaaS: las de la IA (comunes) y las que anotó cada empresa, que solo ve esa empresa. --}}
                    <p class="mb-3 text-13 text-neutral-700">Las extraídas por la IA son comunes a todas las empresas; las anotadas a mano son de cada empresa y solo ella las ve.</p>
                    @php
                        $extraidas = $candidato->competencias->where('pivot.fuente', FuenteCompetencia::Extraccion->value);
                    @endphp
                    <div class="grid gap-3">
                        <div>
                            <p class="mb-1 text-12 uppercase tracking-[0.06em] text-accent-700">Extraídas del CV por la IA</p>
                            @if ($extraidas->isEmpty())
                                <p class="m-0 text-14 text-neutral-700">Ninguna todavía.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($extraidas as $competencia)
                                        <x-ui.tag tone="accent" title="{{ $competencia->pivot->evidencia }}" wire:key="ia-{{ $competencia->id }}">{{ $competencia->nombre }} · IA</x-ui.tag>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @foreach ($manualesPorEmpresa as $grupo)
                            <div wire:key="manuales-{{ $grupo['empresaId'] ?? 'plataforma' }}">
                                <p class="mb-1 text-12 uppercase tracking-[0.06em] text-accent-700">Anotadas por {{ $grupo['empresa'] }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($grupo['competencias'] as $competencia)
                                        <span class="tag tag-outline gap-1" wire:key="manual-{{ $grupo['empresaId'] ?? 'p' }}-{{ $competencia->id }}">
                                            {{ $competencia->nombre }}
                                            @if ($puedeEditar)
                                                <button type="button" wire:click="quitarCompetencia({{ $competencia->id }}, {{ $grupo['empresaId'] ?? 'null' }})" class="leading-none" aria-label="Quitar {{ $competencia->nombre }} de {{ $grupo['empresa'] }}">×</button>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                <p class="mb-3 text-13 text-neutral-600">Las competencias extraídas del CV por la IA se actualizan con cada análisis; las manuales las registra tu equipo.</p>

                @if ($candidato->competencias->isEmpty())
                    <p class="text-14 text-neutral-600">Sin competencias registradas.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        {{-- Una etiqueta por competencia: si tu equipo también la anotó, se muestra la manual (se puede quitar). --}}
                        @foreach ($candidato->competencias->groupBy('id') as $filas)
                            @php
                                $competencia = $filas->firstWhere('pivot.fuente', FuenteCompetencia::Manual->value) ?? $filas->first();
                                $extraida = $filas->firstWhere('pivot.fuente', FuenteCompetencia::Extraccion->value);
                            @endphp
                            @if ($competencia->pivot->fuente === FuenteCompetencia::Extraccion->value)
                                <x-ui.tag tone="accent" title="{{ $competencia->pivot->evidencia }}" wire:key="comp-{{ $competencia->id }}">{{ $competencia->nombre }} · IA</x-ui.tag>
                            @else
                                <span class="tag tag-outline gap-1" wire:key="comp-{{ $competencia->id }}" @if ($extraida) title="{{ $extraida->pivot->evidencia }}" @endif>
                                    {{ $competencia->nombre }}{{ $extraida ? ' · IA' : '' }}
                                    @if ($puedeEditar)
                                        <button type="button" wire:click="quitarCompetencia({{ $competencia->id }})" class="leading-none" aria-label="Quitar {{ $competencia->nombre }}">×</button>
                                    @endif
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
                @endif

                @if ($puedeEditar && (! $vistaGlobal || $empresasDelCandidato))
                    <form wire:submit="agregarCompetencia" class="mt-3 flex flex-wrap items-start gap-2">
                        <x-ui.field error="nuevaCompetencia" class="min-w-[200px] flex-1">
                            <x-ui.input wire:model="nuevaCompetencia" list="catalogo-competencias" placeholder="Agregar competencia" aria-label="Agregar competencia" error="nuevaCompetencia" autocomplete="off" />
                        </x-ui.field>
                        @if ($vistaGlobal)
                            {{-- El admin anota para una empresa concreta: solo las que tienen un proceso del candidato. --}}
                            <x-ui.field error="empresaCompetencia" class="min-w-[180px]">
                                <x-ui.select wire:model="empresaCompetencia" :options="$empresasDelCandidato" placeholder="¿Para qué empresa?" aria-label="Empresa para la que se anota" error="empresaCompetencia" class="w-full" />
                            </x-ui.field>
                        @endif
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="agregarCompetencia">Agregar</x-ui.button>
                    </form>
                    <datalist id="catalogo-competencias">
                        @foreach ($catalogo as $nombre)
                            <option value="{{ $nombre }}"></option>
                        @endforeach
                    </datalist>
                @endif
            </section>
        </div>

        {{-- Currículo --}}
        @php($cv = $candidato->cvActual)
        {{-- Mientras el CV se procesa en la cola, se consulta su estado cada 4 s. --}}
        <div @if (in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true)) wire:poll.4s @endif>
        <x-ui.card kicker="Currículo" :title="$cv?->nombre_original ?? 'Sin CV'" elev="sm">
            @if ($cv)
                <div class="card-meta">
                    <x-ui.estado :valor="$cv->estado_procesamiento" />
                    <span>Subido el {{ Formato::fechaCorta($cv->created_at) }}</span>
                </div>
                @if ($cv->estado_procesamiento === EstadoProcesamientoCv::Error)
                    <p class="card-body text-accent-2-700">{{ $cv->error }}</p>
                    @if ($puedeEditar)
                        <x-ui.button variant="ghost" size="sm" class="self-start" wire:click="reintentar">Reintentar análisis</x-ui.button>
                    @endif
                @elseif ($cv->texto_extraido)
                    <p class="card-body">"{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($cv->texto_extraido), 220) }}"</p>
                @else
                    <p class="card-body">Analizando el CV…</p>
                @endif
                <x-ui.button :href="route('cvs.descargar', $cv)" class="self-start" size="sm" variant="ghost">Descargar CV</x-ui.button>
            @else
                <p class="card-body">Este candidato todavía no tiene CV.</p>
            @endif

            @if ($puedeEditar)
                <form wire:submit="subirCv" class="contents">
                    <x-ui.field label="{{ $cv ? 'Subir nuevo CV' : 'Subir CV' }} (PDF o Word)" for="detalle-cv" error="archivoCv">
                        <input type="file" wire:model="archivoCv" id="detalle-cv" accept=".pdf,.docx" class="input">
                    </x-ui.field>
                    <x-ui.button type="submit" block wire:loading.attr="disabled" wire:target="subirCv,archivoCv">Subir CV</x-ui.button>
                </form>
            @endif
        </x-ui.card>
        </div>
    </div>
</div>
