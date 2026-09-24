{{-- Maqueta: agenda de entrevistas y evaluación por criterios. --}}
<x-presentacion.marco activo="Entrevistas">
    <p class="mb-1 font-heading text-22 font-semibold">Entrevistas y evaluaciones</p>
    <p class="mb-4 text-12 text-neutral-700">Agenda del equipo y resultados por criterio.</p>

    <div class="grid grid-cols-[1fr_250px] gap-4">
        <table class="table !text-12">
            <thead><tr><th>Candidato</th><th>Entrevistador</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
                @foreach ([
                    ['Elena Ruiz', 'Marta Solís', '23 sep · 10:00', 'Confirmada', 'accent'],
                    ['Sofía Nieto', 'Marta Solís', '23 sep · 15:00', 'Confirmada', 'accent'],
                    ['Iván Torres', 'Diego Ferro', '24 sep · 09:30', 'Pendiente', 'neutral'],
                    ['Lucía Prat', 'Marta Solís', '25 sep · 11:00', 'Completada', 'outline'],
                ] as [$candidato, $entrevistador, $fecha, $estado, $tono])
                    <tr>
                        <td>{{ $candidato }}</td>
                        <td>{{ $entrevistador }}</td>
                        <td class="text-neutral-700">{{ $fecha }}</td>
                        <td><x-ui.tag :tone="$tono" class="!text-10">{{ $estado }}</x-ui.tag></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="card elev-md bg-white">
            <div class="card-kicker">Evaluación · Elena Ruiz</div>
            @foreach (['Conocimiento técnico' => 9.2, 'Comunicación' => 8.5, 'Resolución de problemas' => 8.8, 'Ajuste cultural' => 8.0] as $criterio => $puntaje)
                <div>
                    <div class="mb-1 flex justify-between text-11"><span>{{ $criterio }}</span><span class="text-neutral-700">{{ $puntaje }}/10</span></div>
                    <x-ui.progress :value="$puntaje * 10" />
                </div>
            @endforeach
            <div class="mt-1 flex items-center justify-between border-t border-divider pt-2">
                <span class="font-heading text-15 font-semibold">8.6/10</span>
                <x-ui.tag tone="accent" class="!text-10">Aprobado</x-ui.tag>
            </div>
        </div>
    </div>
</x-presentacion.marco>
