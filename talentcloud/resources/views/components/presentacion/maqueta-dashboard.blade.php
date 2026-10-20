{{-- Maqueta: dashboard con indicadores, vacantes recientes y actividad. --}}
<x-presentacion.marco activo="Dashboard">
    <p class="mb-1 font-heading text-22 font-semibold">Dashboard</p>
    <p class="mb-4 text-12 text-neutral-700">Resumen de la actividad de selección de Nimbus Corp.</p>

    <div class="mb-4 grid grid-cols-4 gap-3">
        @foreach (['Vacantes activas' => '2', 'Postulantes' => '15', 'Por revisar' => '4', 'Entrevistas hoy' => '2'] as $indicador => $valor)
            <div class="card !gap-1 !p-3">
                <div class="card-kicker">{{ $indicador }}</div>
                <p @class(['m-0 font-heading text-24 font-semibold', 'text-accent-700' => $indicador === 'Por revisar'])>{{ $valor }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-[1fr_260px] gap-5">
        <table class="table !text-12">
            <thead><tr><th>Vacante</th><th>Candidatos</th><th>Estado</th></tr></thead>
            <tbody>
                <tr><td>Desarrollador Backend</td><td>7</td><td><x-ui.tag tone="accent" class="!text-10">Abierta</x-ui.tag></td></tr>
                <tr><td>Data Analyst</td><td>3</td><td><x-ui.tag class="!text-10">En revisión</x-ui.tag></td></tr>
                <tr><td>Product Designer</td><td>5</td><td><x-ui.tag tone="accent" class="!text-10">Abierta</x-ui.tag></td></tr>
            </tbody>
        </table>

        <div class="grid content-start gap-2">
            <p class="m-0 text-13 font-semibold">Actividad reciente</p>
            @foreach (['Rosa Paredes postuló a Desarrollador Backend.', 'Sofía Nieto avanzó a la etapa de entrevista.', 'Marta Solís aprobó la evaluación de Elena Ruiz.'] as $evento)
                <div class="flex items-start gap-2 text-11 leading-[1.5]">
                    <x-ui.icon name="arrows-left-right" :size="12" class="mt-0.5 text-accent" />{{ $evento }}
                </div>
            @endforeach
        </div>
    </div>
</x-presentacion.marco>
