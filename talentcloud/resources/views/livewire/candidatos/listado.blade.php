{{-- Pantalla "Candidatos" del prototipo (TalentCloud App.dc.html, isCandidatos). --}}
@use('App\Enums\EstadoProcesamientoCv')
@use('App\Enums\FuenteCompetencia')
<div>
    <x-ui.page-header title="Candidatos" subtitle="CVs recibidos y analizados por la IA.">
        <x-slot:actions>
            <x-ui.button wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar">Exportar a Excel</x-ui.button>
            @if ($puedeRegistrar)
                <x-ui.button variant="primary" wire:click="abrirFormulario">+ Nuevo candidato</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.listado-controles
        placeholder="Buscar candidato…"
        :ordenes="$this->opcionesOrden()"
        :direccion="$direccion"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="stack" message="No se encontraron candidatos." />
    @else
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            @foreach ($filas as $candidato)
                @php
                    $postulacion = $candidato->ultimaPostulacion;
                    $cv = $candidato->cvActual;
                    $conError = $cv?->estado_procesamiento === EstadoProcesamientoCv::Error;
                @endphp
                <x-ui.card class="cursor-pointer" wire:key="candidato-{{ $candidato->id }}" wire:click="verDetalle({{ $candidato->id }})">
                    <div class="card-kicker">{{ $candidato->titular ?? 'Candidato' }}</div>
                    <div class="card-title"><x-ui.boton-fila wire:click.stop="verDetalle({{ $candidato->id }})">{{ $candidato->nombre }}</x-ui.boton-fila></div>
                    @if ($vistaGlobal && $candidato->postulaciones->isNotEmpty())
                        {{-- Admin SaaS: todos sus procesos, agrupados por empresa (puede estar en varias). --}}
                        <x-candidatos.procesos :postulaciones="$candidato->postulaciones" />
                    @else
                        {{-- Vacante y etapa: no todos postularon por su cuenta (a muchos los registró el equipo). --}}
                        <p class="card-body">
                            {{ $postulacion?->vacante ? $postulacion->vacante->titulo.' · '.$postulacion->etapaVisible() : 'Sin postulaciones' }}
                        </p>
                    @endif

                    @if ($conError)
                        <div class="card-meta text-accent-2-700">
                            <x-ui.icon name="warning" :size="14" />
                            <span>Error al analizar el CV</span>
                        </div>
                        <x-ui.button variant="ghost" size="sm" class="mt-2 self-start" wire:click.stop="reintentar({{ $cv->id }})">Reintentar análisis</x-ui.button>
                    @else
                        <div class="card-meta flex-wrap">
                            {{-- En la vista global cada proceso ya muestra su match: un único porcentaje confundiría. --}}
                            @if (! $vistaGlobal && $postulacion?->matchPorcentaje() !== null)
                                <x-ui.tag tone="accent">{{ $postulacion->matchPorcentaje() }}% match</x-ui.tag>
                            @elseif (in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true))
                                <span>Analizando CV…</span>
                            @endif
                            @foreach ($candidato->competencias->unique('id')->take(4) as $competencia)
                                <x-ui.tag tone="outline">{{ $competencia->nombre }}</x-ui.tag>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>

        {{ $filas->links() }}
    @endif

    {{-- Diálogo del CV (prototipo: openCv) --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionado?->nombre" max-width="480px">
        @if ($seleccionado)
            @php
                $p = $seleccionado->ultimaPostulacion;
                $extraidas = $seleccionado->competencias->where('pivot.fuente', FuenteCompetencia::Extraccion->value);
                $lista = ($extraidas->isNotEmpty() ? $extraidas : $seleccionado->competencias)->unique('id')->pluck('nombre')->join(', ');
                $texto = $seleccionado->cvActual?->texto_extraido;
            @endphp
            <p class="m-0 whitespace-pre-line">{{ $seleccionado->titular ?? 'Candidato' }}
@unless ($vistaGlobal){{ $p?->vacante ? $p->vacante->titulo.' · '.$p->etapaVisible() : 'Sin postulaciones' }}
@if ($p?->matchPorcentaje() !== null)Coincidencia: {{ $p->matchPorcentaje() }}%
@endif
@endunless
{{ $extraidas->isNotEmpty() ? 'Competencias extraídas por IA' : 'Competencias' }}: {{ $lista !== '' ? $lista : 'sin registrar' }}
@if ($texto)

Extracto del CV: "{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($texto), 180) }}"
@endif</p>
            @if ($vistaGlobal && $seleccionado->postulaciones->isNotEmpty())
                <x-candidatos.procesos :postulaciones="$seleccionado->postulaciones" class="mt-3 border-t border-divider pt-3" />
            @endif
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($seleccionado?->cvActual)
                <x-ui.button :href="route('cvs.descargar', $seleccionado->cvActual)">Descargar CV</x-ui.button>
            @endif
            @if ($seleccionado?->ultimaPostulacion?->match_detalle)
                <x-ui.button :href="route('matching.show', $seleccionado->ultimaPostulacion)" wire:navigate>Ver matching</x-ui.button>
            @endif
            @if ($seleccionado)
                <x-ui.button variant="primary" :href="route('candidatos.show', $seleccionado)" wire:navigate>Ver candidato</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.dialog>

    {{-- "+ Nuevo candidato" --}}
    <x-ui.dialog wire:model="mostrarFormulario" title="Nuevo candidato" max-width="480px">
        <form wire:submit="registrar" id="form-nuevo-candidato" class="grid gap-3">
            <x-ui.field label="Nombre" for="candidato-nombre" error="nombre">
                <x-ui.input wire:model="nombre" id="candidato-nombre" placeholder="Elena Ruiz" error="nombre" />
            </x-ui.field>
            <x-ui.field label="Titular profesional" for="candidato-titular" error="titular">
                <x-ui.input wire:model="titular" id="candidato-titular" placeholder="Desarrolladora Backend" error="titular" />
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Correo" for="candidato-email" error="email">
                    <x-ui.input wire:model="email" id="candidato-email" type="email" error="email" />
                </x-ui.field>
                <x-ui.field label="Teléfono" for="candidato-telefono" error="telefono">
                    <x-ui.input wire:model="telefono" id="candidato-telefono" type="tel" error="telefono" />
                </x-ui.field>
            </div>
            <x-ui.field label="Postular a (opcional)" for="candidato-vacante" error="vacanteId">
                <x-ui.select wire:model="vacanteId" id="candidato-vacante" :options="$vacantesAbiertas" placeholder="Sin postular por ahora" class="w-full" error="vacanteId" />
            </x-ui.field>
            <x-ui.field label="CV (PDF o Word, hasta 5 MB)" for="candidato-cv" error="archivoCv">
                <input type="file" wire:model="archivoCv" id="candidato-cv" accept=".pdf,.docx" class="input">
                <p class="field-hint" wire:loading wire:target="archivoCv">Subiendo archivo…</p>
            </x-ui.field>
            <x-ui.field error="autorizado">
                <x-ui.checkbox wire:model="autorizado" id="candidato-autorizado">El candidato autorizó el tratamiento de sus datos y el análisis de su CV con IA.</x-ui.checkbox>
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-nuevo-candidato" variant="primary" wire:loading.attr="disabled" wire:target="registrar,archivoCv">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
