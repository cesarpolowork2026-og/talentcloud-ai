{{-- Vacantes abiertas en la presentación (datos reales, solo los públicos de VacanteRepository::publicadas()). --}}
<section id="empleos" class="bg-surface py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'empleos'">
    <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
        <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Empleos</p>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <h2 class="m-0 max-w-[30ch] text-[clamp(26px,3vw,34px)] leading-[1.15]">Vacantes abiertas ahora</h2>
            <x-ui.button :href="route('empleos.index')">Ver todas las vacantes</x-ui.button>
        </div>
        {{-- Tres columnas: las 6 vacantes (VacantesAbiertas::CANTIDAD) forman dos filas completas. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vacantes as $vacante)
                <x-vacantes.tarjeta-publica :vacante="$vacante" :href="route('empleos.show', $vacante->id)" titulo="h3" class="!bg-white">
                    <x-ui.button size="sm" :href="route('empleos.show', $vacante->id)" aria-label="Ver vacante: {{ $vacante->titulo }}">Ver vacante</x-ui.button>
                </x-vacantes.tarjeta-publica>
            @endforeach

            {{-- Con pocas vacantes la fila queda a medias: el hueco invita a publicar la tuya. --}}
            @if ($vacantes->count() < 3)
                <div class="card flex flex-col justify-center gap-3 border-2 border-dashed border-divider !bg-transparent">
                    <h3 class="m-0 text-17">¿Tu empresa está contratando?</h3>
                    <p class="m-0 text-14 text-neutral-800">Publica tu vacante gratis y aparecerá aquí, en la bolsa de empleos.</p>
                    <div><x-ui.button size="sm" variant="primary" :href="route('register')">Crear empresa gratis</x-ui.button></div>
                </div>
            @endif
        </div>
    </div>
</section>
