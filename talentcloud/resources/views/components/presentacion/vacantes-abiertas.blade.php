{{-- Vacantes abiertas en la presentación (datos reales, solo los públicos de VacanteRepository::publicadas()). --}}
<section id="empleos" class="py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'empleos'">
    <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
        <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Empleos</p>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <h2 class="m-0 max-w-[30ch] text-[clamp(26px,3vw,34px)] leading-[1.15]">Vacantes abiertas ahora</h2>
            <x-ui.button :href="route('empleos.index')">Ver todas las vacantes</x-ui.button>
        </div>
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            @foreach ($vacantes as $vacante)
                <x-vacantes.tarjeta-publica :vacante="$vacante" :href="route('empleos.show', $vacante->id)" titulo="h3" class="!bg-white">
                    <x-ui.button size="sm" :href="route('empleos.show', $vacante->id)" aria-label="Ver vacante: {{ $vacante->titulo }}">Ver vacante</x-ui.button>
                </x-vacantes.tarjeta-publica>
            @endforeach
        </div>
    </div>
</section>
