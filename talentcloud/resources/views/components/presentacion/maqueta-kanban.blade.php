{{-- Maqueta: tablero de selección de una vacante (datos de ejemplo de la demo). --}}
<x-presentacion.marco activo="Selección">
    <p class="mb-1 text-11 text-neutral-700">Vacantes / Desarrollador Backend / Selección</p>
    <p class="mb-1 font-heading text-22 font-semibold">Selección · Desarrollador Backend</p>
    <p class="mb-4 text-12 text-neutral-700">Nimbus Corp — 6 postulaciones activas.</p>

    <div class="grid grid-cols-4 gap-3">
        @foreach ([
            'Postulado' => [['Elena Ruiz', 'Desarrolladora Backend', 'hace 1 día'], ['Rosa Paredes', 'Desarrolladora Backend', 'hoy']],
            'En revisión' => [['Sofía Nieto', 'Desarrolladora Backend', 'hace 2 días']],
            'Entrevista' => [['Iván Torres', 'Diseñador de Producto', 'jueves 10:00']],
            'Oferta' => [['Lucía Prat', 'Analista de Datos', 'hace 3 días']],
        ] as $etapa => $tarjetas)
            <div>
                <div class="mb-2 flex justify-between text-10 uppercase tracking-[0.08em] text-neutral-700">
                    <span>{{ $etapa }}</span><span>{{ $etapa === 'Postulado' ? 3 : count($tarjetas) }}</span>
                </div>
                <div class="grid gap-2">
                    @foreach ($tarjetas as [$nombre, $titular, $detalle])
                        <div class="rounded bg-surface p-3 shadow-sm">
                            <p class="text-13 font-semibold">{{ $nombre }}</p>
                            <p class="mb-2 text-11 text-neutral-700">{{ $titular }}</p>
                            <span class="text-10 text-neutral-600">{{ $etapa === 'Entrevista' ? 'Entrevista: ' : 'En esta etapa: ' }}{{ $detalle }}</span>
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
