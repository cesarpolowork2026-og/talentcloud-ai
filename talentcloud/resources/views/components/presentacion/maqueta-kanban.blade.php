{{-- Maqueta: Kanban de una vacante (datos de ejemplo de la demo). --}}
<x-presentacion.marco activo="Kanban">
    <p class="mb-1 text-11 text-neutral-700">Vacantes / Desarrollador Backend / Kanban</p>
    <p class="mb-1 font-heading text-22 font-semibold">Kanban · Desarrollador Backend</p>
    <p class="mb-4 text-12 text-neutral-700">Nimbus Corp — 6 postulaciones activas.</p>

    <div class="grid grid-cols-4 gap-3">
        @foreach ([
            'Postulado' => [['Elena Ruiz', 'Desarrolladora Backend', 78], ['Rosa Paredes', 'Desarrolladora Backend', 85]],
            'En revisión' => [['Sofía Nieto', 'Desarrolladora Backend', 93]],
            'Entrevista' => [['Iván Torres', 'Diseñador de Producto', 41]],
            'Oferta' => [['Lucía Prat', 'Analista de Datos', 96]],
        ] as $etapa => $tarjetas)
            <div>
                <div class="mb-2 flex justify-between text-10 uppercase tracking-[0.08em] text-neutral-700">
                    <span>{{ $etapa }}</span><span>{{ $etapa === 'Postulado' ? 3 : count($tarjetas) }}</span>
                </div>
                <div class="grid gap-2">
                    @foreach ($tarjetas as [$nombre, $titular, $match])
                        <div class="rounded bg-surface p-3 shadow-sm">
                            <p class="text-13 font-semibold">{{ $nombre }}</p>
                            <p class="mb-2 text-11 text-neutral-700">{{ $titular }}</p>
                            <x-ui.tag tone="accent" class="!text-10">{{ $match }}% match</x-ui.tag>
                        </div>
                    @endforeach
                    @if ($etapa === 'Postulado')
                        <p class="m-0 px-1 text-11 text-accent-700">+ 1 postulación más</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-presentacion.marco>
