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
            {{-- Postulaciones --}}
            <section>
                <h2 class="mb-3 text-16">Postulaciones</h2>
                @if ($candidato->postulaciones->isEmpty())
                    <p class="text-14 text-neutral-600">Todavía no postuló a vacantes de tu empresa.</p>
                @else
                    <x-ui.table>
                        <x-slot:head>
                            <th>Vacante</th><th>Etapa</th><th>Match</th><th>Fecha</th>
                        </x-slot:head>
                        @foreach ($candidato->postulaciones as $postulacion)
                            <tr wire:key="postulacion-{{ $postulacion->id }}">
                                <td><a href="{{ route('vacantes.show', $postulacion->vacante_id) }}" wire:navigate>{{ Formato::vacante($postulacion->vacante) }}</a></td>
                                <td><x-ui.estado :valor="$postulacion->etapa" /></td>
                                <td>
                                    @if ($postulacion->matchPorcentaje() !== null)
                                        <a href="{{ route('matching.show', $postulacion) }}" wire:navigate>{{ $postulacion->matchPorcentaje() }}%</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-neutral-600">{{ Formato::fechaCorta($postulacion->created_at) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif

                @if ($puedeEditar && $vacantesDisponibles)
                    <form wire:submit="postular" class="mt-4 flex flex-wrap items-end gap-3">
                        <x-ui.field label="Postular a una vacante" for="postular-vacante" error="vacanteId" class="min-w-[240px] flex-1">
                            <x-ui.select wire:model="vacanteId" id="postular-vacante" :options="$vacantesDisponibles" placeholder="Elige una vacante abierta" class="w-full" error="vacanteId" />
                        </x-ui.field>
                        <x-ui.button type="submit">Postular</x-ui.button>
                    </form>
                @endif
            </section>

            {{-- Notas internas del equipo, por postulación de esta empresa --}}
            @if ($conNotas->isNotEmpty())
                <section>
                    <h2 class="mb-1 text-16">Notas del equipo</h2>
                    <p class="mb-3 text-13 text-neutral-700">Solo las ve tu equipo: nunca el candidato ni otras empresas.</p>
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

                @if ($puedeEditar)
                    <form wire:submit="agregarCompetencia" class="mt-3 flex flex-wrap items-start gap-2">
                        <x-ui.field error="nuevaCompetencia" class="min-w-[200px] flex-1">
                            <x-ui.input wire:model="nuevaCompetencia" list="catalogo-competencias" placeholder="Agregar competencia" aria-label="Agregar competencia" error="nuevaCompetencia" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.button type="submit">Agregar</x-ui.button>
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
