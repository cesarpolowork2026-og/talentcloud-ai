{{-- "Vacantes" del postulante: ofertas abiertas de todas las empresas (ampliación sobre el prototipo). --}}
@use('App\Enums\TipoRequisito')
@use('App\Support\Formato')
<div>
    <x-ui.page-header title="Vacantes" subtitle="Ofertas abiertas de las empresas en TalentCloud. Postula con tu CV en un clic." compact />

    <x-ui.listado-controles
        placeholder="Puesto, empresa o ciudad…"
        :estados="$modalidades"
        :estado-actual="$estado"
        etiqueta-estados="Filtrar por modalidad"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="briefcase" message="No hay vacantes abiertas con esos filtros." />
    @else
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            @foreach ($filas as $vacante)
                @php($yaPostulo = in_array($vacante->id, $postuladas, true))
                <x-ui.card wire:key="vacante-{{ $vacante->id }}">
                    <div class="card-kicker">{{ $vacante->empresa?->nombre }}</div>
                    <h2 class="card-title m-0">
                        <a href="{{ route('postulante.vacantes.show', $vacante->id) }}" wire:navigate class="text-ink hover:text-accent-700">{{ $vacante->titulo }}</a>
                    </h2>
                    <p class="card-body">
                        {{ $vacante->lugarYModalidad() }}
                        @if ($vacante->publicada_at)
                            · Publicada el {{ Formato::fechaCorta($vacante->publicada_at) }}
                        @endif
                    </p>

                    @php($obligatorios = $vacante->requisitos->where('tipo', TipoRequisito::Obligatorio))
                    @if ($obligatorios->isNotEmpty())
                        <div class="card-meta flex-wrap" aria-label="Requisitos obligatorios">
                            @foreach ($obligatorios->take(4) as $requisito)
                                <x-ui.tag tone="outline">{{ $requisito->competencia?->nombre }}</x-ui.tag>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-1">
                        @if ($yaPostulo)
                            <x-ui.tag tone="accent">Ya postulaste</x-ui.tag>
                        @else
                            <x-ui.button size="sm" :href="route('postulante.vacantes.show', $vacante->id)" wire:navigate aria-label="Ver y postular a {{ $vacante->titulo }}">Ver y postular</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        {{ $filas->links() }}
    @endif
</div>
