{{-- Maqueta: matching explicable, requisito por requisito y con la evidencia del CV. --}}
<x-presentacion.marco activo="Matching">
    <p class="mb-1 font-heading text-22 font-semibold">Matching inteligente</p>
    <p class="mb-4 text-12 text-neutral-700">Rosa Paredes — Desarrollador Backend @ Nimbus Corp · 85% de coincidencia global.</p>

    <div class="grid grid-cols-[1fr_250px] gap-4">
        <div class="grid gap-2">
            @foreach ([['Java', 96, true], ['Spring Boot', 94, true], ['PostgreSQL', 90, true], ['Docker', 88, true], ['Certificación AWS', 0, false]] as [$requisito, $valor, $coincide])
                <div @class(['flex items-center gap-3 rounded bg-surface px-4 py-2.5', 'outline outline-2 outline-accent-500' => $requisito === 'Spring Boot'])>
                    <span class="w-[120px] text-13 font-semibold">{{ $requisito }}</span>
                    <x-ui.progress :value="$valor" class="flex-1" />
                    @if ($coincide)
                        <x-ui.tag tone="accent" class="!text-10">Coincide</x-ui.tag>
                    @else
                        <x-ui.tag tone="accent-2" class="!text-10">No encontrado</x-ui.tag>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card elev-md bg-white">
            <div class="card-kicker">Evidencia · Spring Boot</div>
            <p class="m-0 font-heading text-13 italic leading-[1.5]">“Diseñé APIs REST con Java y Spring Boot para una pasarela de pagos de alto tráfico.”</p>
            <p class="m-0 text-11 text-neutral-700">Sección Experiencia · coincidencia exacta.</p>
            <div class="mt-1 flex items-start gap-1.5 border-t border-divider pt-2 text-11 text-neutral-800">
                <x-ui.icon name="scales" :size="13" class="mt-0.5 text-accent-700" />
                La IA sugiere y explica; la decisión es tuya.
            </div>
        </div>
    </div>
</x-presentacion.marco>
