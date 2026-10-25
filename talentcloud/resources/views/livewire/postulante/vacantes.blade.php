{{-- "Vacantes" del postulante: ofertas abiertas de todas las empresas (ampliación sobre el prototipo). --}}
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header title="Vacantes" subtitle="Ofertas abiertas de las empresas en TalentCloud. Postula con tu CV en un clic." compact />

    <x-ui.listado-controles
        placeholder="Puesto, empresa o ciudad…"
        :estados="$modalidades"
        :estado-actual="$estado"
        etiqueta-estados="Filtrar por modalidad"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="briefcase" :message="$this->hayFiltros() ? 'No hay vacantes abiertas con esos filtros.' : 'Todavía no hay vacantes abiertas. Vuelve pronto.'" />
    @else
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            @foreach ($filas as $vacante)
                <x-vacantes.tarjeta-publica :vacante="$vacante" :href="route('postulante.vacantes.show', $vacante->id)" wire:key="vacante-{{ $vacante->id }}">
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
</div>
