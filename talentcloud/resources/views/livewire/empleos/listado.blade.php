{{-- Bolsa pública de empleos: vacantes abiertas, sin iniciar sesión (ampliación sobre el prototipo). --}}
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header title="Empleos" subtitle="Vacantes abiertas de las empresas en TalentCloud AI. Regístrate, sube tu CV una vez y postula en un clic." compact />

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
                <x-vacantes.tarjeta-publica :vacante="$vacante" :href="route('empleos.show', $vacante->id)" wire:key="empleo-{{ $vacante->id }}">
                    <x-ui.button size="sm" :href="route('empleos.show', $vacante->id)" wire:navigate aria-label="Ver vacante: {{ $vacante->titulo }}">Ver vacante</x-ui.button>
                </x-vacantes.tarjeta-publica>
            @endforeach
        </div>

        {{-- Enlaces reales a ?page=N: sin ellos, un buscador solo vería la primera página. --}}
        {{ $filas->withPath(route('empleos.index'))->links('pagination.publica') }}
    @endif
</div>
