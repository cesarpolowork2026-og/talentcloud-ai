{{-- «Selección»: Kanban del prototipo (TalentCloud App.dc.html, isKanban) + ranking por coincidencia de la misma vacante. --}}
@use('App\Enums\EtapaPostulacion')
@use('App\Enums\NivelCoincidencia')
<div>
    @if (! $vacante)
        <x-ui.page-header title="Selección" subtitle="Seguimiento de postulaciones por vacante." />
        <x-ui.empty-state icon="kanban" message="No hay vacantes abiertas para mostrar su selección.">
            <x-ui.button :href="route('vacantes.index')" wire:navigate class="mt-3">Ir a Vacantes</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.page-header :title="'Selección · '.$vacante->titulo" :subtitle="$vacante->empresa?->nombre.' — '.$activas.' '.($activas === 1 ? 'postulación activa' : 'postulaciones activas').'.'">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => route('vacantes.index')], ['label' => $vacante->titulo, 'href' => route('vacantes.show', $vacante)], ['label' => 'Selección']]" />
            </x-slot:breadcrumbs>
            @if ($hayOtrasVacantes)
                <x-slot:actions>
                    <label for="seleccion-vacante" class="sr-only">Cambiar de vacante</label>
                    <x-ui.select
                        id="seleccion-vacante"
                        :options="$otrasVacantes"
                        :selected="$vacante->id"
                        x-data
                        x-on:change="Livewire.navigate('{{ url('seleccion') }}/' + $event.target.value + '?vista={{ $vista }}')"
                    />

                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- Vista y filtros: dos formas de ver las mismas postulaciones. --}}
        <div class="-mt-2 mb-5 flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="flex items-center gap-3">
                <span id="seleccion-vista" class="text-13 text-neutral-700">Ver como</span>
                <x-ui.seg labelledby="seleccion-vista">
                    <x-ui.seg-option name="vista" value="tablero" wire:model.live="vista">Tablero</x-ui.seg-option>
                    <x-ui.seg-option name="vista" value="ranking" wire:model.live="vista">Ranking por coincidencia</x-ui.seg-option>
                </x-ui.seg>
            </div>
            <x-ui.checkbox wire:model.live="soloObligatorios" id="seleccion-obligatorios" name="soloObligatorios">Solo quienes cubren los obligatorios</x-ui.checkbox>
            @if ($vista === 'ranking')
                <x-ui.checkbox wire:model.live="verDescartados" id="seleccion-descartados" name="verDescartados">Incluir descartados</x-ui.checkbox>
            @endif
            @if ($ocultas > 0)
                <span class="text-13 text-neutral-700" role="status">{{ $ocultas }} {{ $ocultas === 1 ? 'postulación oculta' : 'postulaciones ocultas' }} por el filtro.</span>
            @endif
        </div>

        @error('tablero')
            <p class="field-error -mt-3 mb-4 text-14" role="alert">{{ $message }}</p>
        @enderror

        @if (! $editable)
            <p class="-mt-3 mb-4 text-13 text-neutral-600">La vacante está cerrada: la selección es de solo lectura.</p>
        @endif

        @if ($vista === 'ranking')
            {{-- Ranking: el porcentaje ordena y explica; no mueve ni descarta a nadie. --}}
            @if ($ranking->isEmpty())
                <x-ui.empty-state icon="funnel" :message="$soloObligatorios ? 'Nadie cubre todos los requisitos obligatorios todavía.' : 'Esta vacante aún no tiene postulaciones.'" />
            @else
                <p class="mb-3 max-w-[80ch] text-13 text-neutral-700">
                    Ordenado por coincidencia con lo que pide la vacante. Cada competencia indica si el CV la respalda
                    (<span aria-hidden="true">✓</span><span class="sr-only">respaldada</span>), conviene revisarla (<span aria-hidden="true">?</span><span class="sr-only">por revisar</span>)
                    o no aparece (<span aria-hidden="true">✗</span><span class="sr-only">no encontrada</span>); lo obligatorio va marcado con <span aria-hidden="true">*</span><span class="sr-only">asterisco</span>.
                    Es una ayuda para decidir a quién entrevistar primero: la decisión es tuya.
                </p>
                <x-ui.table>
                    <x-slot:head>
                        <th>#</th><th>Candidato</th><th>Coincidencia</th><th>Requisitos de la vacante</th><th>Etapa</th><th><span class="sr-only">Acciones</span></th>
                    </x-slot:head>
                    @foreach ($ranking as $posicion => $postulacion)
                        @php($cumple = $cumplimiento[$postulacion->id])
                        <tr wire:key="ranking-{{ $postulacion->id }}">
                            <td class="text-neutral-700">{{ $posicion + 1 }}</td>
                            <td>
                                <a href="{{ $postulacion->match_detalle ? route('matching.show', $postulacion) : route('candidatos.show', $postulacion->candidato_id) }}" wire:navigate class="font-semibold">{{ $postulacion->candidato?->nombre }}</a>
                                <div class="text-13 text-neutral-700">{{ $postulacion->candidato?->titular }}</div>
                            </td>
                            <td class="min-w-[130px]">
                                @if ($postulacion->matchPorcentaje() !== null)
                                    <div class="mb-1 text-14 font-semibold">{{ $postulacion->matchPorcentaje() }}%</div>
                                    <x-ui.progress :value="$postulacion->matchPorcentaje()" :label="'Coincidencia de '.$postulacion->candidato?->nombre.': '.$postulacion->matchPorcentaje().'%'" />
                                @else
                                    <span class="text-13 text-neutral-700">Sin calcular</span>
                                @endif
                            </td>
                            <td>
                                @if ($cumple->hayCalculo())
                                    <div class="mb-1 text-13 text-neutral-700">
                                        {{ $cumple->resumen() }}
                                        @if ($cumple->obligatoriosFaltantes() > 0)
                                            · <span class="font-semibold text-accent-2-700">{{ $cumple->obligatoriosFaltantes() === 1 ? 'falta 1 obligatorio' : 'faltan '.$cumple->obligatoriosFaltantes().' obligatorios' }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($cumple->requisitos as $requisito)
                                            @php($nivel = $requisito['nivel'])
                                            <x-ui.tag :tone="$nivel?->tono() ?? 'outline'">
                                                <span aria-hidden="true">{{ match ($nivel) { NivelCoincidencia::Coincide => '✓', NivelCoincidencia::Revisar => '?', NivelCoincidencia::NoEncontrado => '✗', default => '·' } }}</span>
                                                <span class="ml-1">{{ $requisito['competencia'] }}{{ $requisito['obligatorio'] ? '*' : '' }}</span>
                                                <span class="sr-only">: {{ $nivel?->etiqueta() ?? 'sin calcular' }}{{ $requisito['obligatorio'] ? ', obligatorio' : '' }}</span>
                                            </x-ui.tag>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-13 text-neutral-700">Aún sin análisis del CV.</span>
                                @endif
                            </td>
                            <td>
                                @if ($postulacion->estaRetirada())
                                    <x-ui.tag tone="outline">Retirada</x-ui.tag>
                                @else
                                    <x-ui.tag tone="neutral">{{ $postulacion->etapa->etiqueta() }}</x-ui.tag>
                                @endif
                            </td>
                            <td class="text-right">
                                <x-ui.button size="sm" wire:click="abrir({{ $postulacion->id }})">Abrir</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        @else
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach ($columnas as $columna)
                @php($etapa = $columna['etapa'])
                <section class="flex min-w-[190px] flex-1 basis-0 flex-col gap-3" aria-label="{{ $etapa->etiqueta() }}" wire:key="columna-{{ $etapa->value }}">
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
                            @php($cumple = $cumplimiento[$postulacion->id])
                            @php($enCurso = ! in_array($etapa, [EtapaPostulacion::Contratado, EtapaPostulacion::Descartado], true))
                            @php($dias = (int) ($postulacion->etapa_cambiada_at ?? $postulacion->created_at)->diffInDays(now()))
                            @php($proxima = $postulacion->proxima_entrevista ? \Illuminate\Support\Carbon::parse($postulacion->proxima_entrevista) : null)
                            <div
                                class="card elev-sm relative !gap-1 !p-3 {{ $arrastrable ? 'cursor-grab' : 'cursor-pointer' }}"
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
                                @elseif ($postulacion->matchPorcentaje() !== null)
                                    <div class="mt-1 flex items-center gap-2">
                                        <x-ui.progress :value="$postulacion->matchPorcentaje()" :label="'Coincidencia: '.$postulacion->matchPorcentaje().'%'" class="flex-1" />
                                        <span class="text-13 font-semibold">{{ $postulacion->matchPorcentaje() }}%</span>
                                    </div>
                                    @if ($cumple->hayCalculo())
                                        <div class="text-12 text-neutral-700">
                                            {{ $cumple->resumen() }}
                                            @if ($cumple->obligatoriosFaltantes() > 0)
                                                · <span class="font-semibold text-accent-2-700">falta{{ $cumple->obligatoriosFaltantes() === 1 ? '' : 'n' }} {{ $cumple->obligatoriosFaltantes() }} obligatorio{{ $cumple->obligatoriosFaltantes() === 1 ? '' : 's' }}</span>
                                            @endif
                                        </div>
                                    @endif
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
                    </div>
                </section>
            @endforeach
        </div>
        @endif

        {{-- Detalle de la tarjeta (prototipo: openCard) + "Mover a…" --}}
        <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->candidato?->nombre" max-width="480px">
            @if ($seleccionada)
                <p class="m-0 whitespace-pre-line">{{ $seleccionada->candidato?->titular ?? 'Candidato' }}{{ $seleccionada->matchPorcentaje() !== null ? ' · '.$seleccionada->matchPorcentaje().'% de coincidencia con la vacante.' : '.' }}
Columna: {{ $seleccionada->etapa->etiqueta() }}.@if ($seleccionada->estaRetirada())

Retiró su postulación el {{ App\Support\Formato::fechaCorta($seleccionada->retirada_at) }}.@endif</p>
                @php($cumpleSeleccionada = $cumplimiento[$seleccionada->id])
                @if ($cumpleSeleccionada->hayCalculo())
                    {{-- Todos los requisitos de la vacante, no solo los primeros: lo que el CV respalda y lo que no. --}}
                    <h3 class="mb-1 mt-4 text-14">Requisitos de la vacante · {{ $cumpleSeleccionada->resumen() }}</h3>
                    <ul class="m-0 grid list-none gap-1 p-0 text-14">
                        @foreach ($cumpleSeleccionada->requisitos as $requisito)
                            <li class="flex items-center justify-between gap-2" wire:key="req-{{ $seleccionada->id }}-{{ $requisito['id'] }}">
                                <span>{{ $requisito['competencia'] }}@if ($requisito['obligatorio']) <span class="text-12 text-neutral-700">· obligatorio</span>@endif</span>
                                @if ($requisito['nivel'])
                                    <x-ui.estado :valor="$requisito['nivel']" />
                                @else
                                    <x-ui.tag tone="outline">Sin calcular</x-ui.tag>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="m-0 mt-3 text-14">
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
                    @if ($puedeEvaluar)
                        <x-ui.button :href="route('evaluaciones.index', ['evaluar' => $seleccionada->id])" wire:navigate>Evaluar</x-ui.button>
                    @endif
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
