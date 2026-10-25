{{-- "Vacantes" del postulante: ofertas abiertas de todas las empresas, con filtros y compatibilidad con su CV (ampliación sobre el prototipo). --}}
@use('App\Enums\EstadoProcesamientoCv')
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header title="Vacantes" subtitle="Ofertas abiertas de las empresas en TalentCloud. Postula con tu CV en un clic." compact />

    {{-- Sin CV listo no hay compatibilidad que mostrar: se explica por qué y dónde subirlo. --}}
    @if (! $conCv)
        <p class="card mb-5 !flex-row items-start gap-2 text-14" role="note">
            <x-ui.icon name="file-text" :size="18" class="mt-0.5 shrink-0 text-accent-700" />
            <span>
                @if ($cv?->estado_procesamiento === EstadoProcesamientoCv::Pendiente || $cv?->estado_procesamiento === EstadoProcesamientoCv::Procesando)
                    Tu CV se está procesando. En unos segundos verás qué vacantes se ajustan a él.
                @else
                    <strong>Sube tu CV</strong> en <a href="{{ route('postulante.postulaciones') }}" wire:navigate>«Mis postulaciones»</a> para ver qué vacantes se ajustan a tu perfil y ordenarlas por compatibilidad.
                @endif
            </span>
        </p>
    @elseif ($recomendada)
        @php($c = $coberturas[$recomendada->id])
        <section class="mb-6" aria-labelledby="recomendada-titulo">
            <h2 id="recomendada-titulo" class="mb-1 text-16">Recomendada para ti</h2>
            <p class="mb-3 mt-0 text-13 text-neutral-700">La vacante a la que aún no postulaste donde tu CV menciona más requisitos.</p>
            <x-ui.card elev="sm" class="!bg-white md:!flex-row md:items-center md:justify-between" wire:key="recomendada-{{ $recomendada->id }}">
                <div class="grid gap-1">
                    <div class="card-kicker">{{ $recomendada->empresa?->nombre }}</div>
                    <h3 class="card-title m-0 text-18">{{ $recomendada->titulo }}</h3>
                    <p class="m-0 text-14 text-neutral-700">{{ $recomendada->lugarYModalidad() }}@if ($recomendada->sueldoEnTexto()) · {{ $recomendada->sueldoEnTexto() }}@endif</p>
                    <p class="m-0 flex flex-wrap items-center gap-2 text-14">
                        @if ($c->obligatoriosFaltantes() === [])
                            <x-ui.tag tone="accent">Tu CV menciona los obligatorios</x-ui.tag>
                        @endif
                        <span class="font-semibold text-neutral-800">Tu CV menciona {{ $c->cubiertos() }} de {{ $c->total() }} requisitos</span>
                        @if ($c->obligatoriosFaltantes() !== [])
                            <span class="text-neutral-700">· no menciona: {{ implode(', ', array_slice($c->obligatoriosFaltantes(), 0, 3)) }}</span>
                        @endif
                    </p>
                </div>
                <x-ui.button variant="primary" :href="route('postulante.vacantes.show', $recomendada->id)" wire:navigate class="shrink-0">Ver y postular</x-ui.button>
            </x-ui.card>
        </section>
    @endif

    <x-ui.listado-controles
        placeholder="Puesto, empresa o ciudad…"
        :estados="$modalidades"
        :estado-actual="$estado"
        etiqueta-estados="Filtrar por modalidad"
    />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="vacantes-ordenar">Ordenar por</label>
        <x-ui.select wire:model.live="ordenar" id="vacantes-ordenar" class="w-auto" :options="$conCv ? $ordenes : array_diff_key($ordenes, ['compatibles' => true])" />
        <label class="sr-only" for="vacantes-lugar">Lugar</label>
        <x-ui.select wire:model.live="lugar" id="vacantes-lugar" class="w-auto" :options="$lugares" placeholder="Cualquier lugar" />
        <label class="sr-only" for="vacantes-jornada">Jornada</label>
        <x-ui.select wire:model.live="jornada" id="vacantes-jornada" class="w-auto" :options="$jornadas" placeholder="Cualquier jornada" />
        @if ($conCv)
            <x-ui.checkbox wire:model.live="soloCompatibles" id="vacantes-compatibles">Solo las que mi CV cumple en lo obligatorio</x-ui.checkbox>
        @endif
        @if ($this->hayFiltros())
            <button type="button" wire:click="limpiarFiltros" class="text-14 text-neutral-700 underline hover:text-accent-700">Quitar filtros</button>
        @endif
    </div>

    <p class="-mt-1 mb-4 text-13 text-neutral-700" role="status">
        {{ $filas->total() }} {{ $filas->total() === 1 ? 'vacante abierta' : 'vacantes abiertas' }}{{ $this->hayFiltros() ? ' con esos filtros' : '' }}.
        @if ($conCv)
            La compatibilidad compara los requisitos con lo que dice tu CV (el nombre de cada competencia y sus sinónimos): es una guía para elegir; la empresa revisa cada postulación y decide.
        @endif
    </p>

    <x-ui.actualizable target="busqueda,filtrarEstado,ordenar,lugar,jornada,soloCompatibles,limpiarFiltros,gotoPage,nextPage,previousPage">
        @if ($filas->isEmpty())
            <x-ui.empty-state icon="briefcase" :message="$this->hayFiltros() ? 'No hay vacantes abiertas con esos filtros.' : 'Todavía no hay vacantes abiertas. Vuelve pronto.'" />
        @else
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                @foreach ($filas as $vacante)
                    <x-vacantes.tarjeta-publica :vacante="$vacante" :href="route('postulante.vacantes.show', $vacante->id)" :cobertura="$coberturas[$vacante->id] ?? null" wire:key="vacante-{{ $vacante->id }}">
                        @if (($postuladas[$vacante->id] ?? null) === true)
                            <x-ui.tag tone="outline">Retiraste tu postulación</x-ui.tag>
                        @elseif (array_key_exists($vacante->id, $postuladas))
                            <x-ui.tag tone="accent">Ya postulaste</x-ui.tag>
                        @else
                            <x-ui.button size="sm" :href="route('postulante.vacantes.show', $vacante->id)" wire:navigate aria-label="Ver y postular a {{ $vacante->titulo }}">Ver y postular</x-ui.button>
                        @endif
                    </x-vacantes.tarjeta-publica>
                @endforeach
            </div>

            {{ $filas->links() }}
        @endif
    </x-ui.actualizable>
</div>
