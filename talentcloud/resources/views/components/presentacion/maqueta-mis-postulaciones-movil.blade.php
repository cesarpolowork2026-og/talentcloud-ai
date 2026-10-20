{{--
    Maqueta (datos ficticios fijos) de «Mis postulaciones» del postulante en el móvil. Los estados
    salen de EstadoDeMiPostulacion, igual que en la aplicación: la imagen no promete nada que no haga.
--}}
@use('App\Enums\EstadoDeMiPostulacion')
<div class="text-ink">
    <div class="barra flex items-center justify-between px-4 pb-3 pt-9">
        <x-marca tamano="pequeno" />
        <span class="grid h-6 w-6 place-items-center rounded-full bg-accent-100 text-11 font-semibold text-accent-800">A</span>
    </div>

    <div class="grid gap-3 px-4 pb-6 pt-4">
        <div>
            <p class="m-0 font-heading text-20 font-semibold">Mis postulaciones</p>
            <p class="m-0 text-12 text-neutral-700">Sigue el estado de tus procesos.</p>
        </div>

        {{-- Próxima entrevista --}}
        <div class="rounded-md border border-accent-300 bg-accent-100 p-3">
            <p class="m-0 text-10 uppercase tracking-[0.12em] text-accent-700">Próxima entrevista</p>
            <p class="m-0 mt-1 text-13 font-semibold">Desarrollador Backend</p>
            <p class="m-0 flex items-center gap-1 text-12 text-neutral-800">
                <x-ui.icon name="calendar-check" :size="14" class="text-accent-700" />
                Jueves · 10:00 · Videollamada
            </p>
        </div>

        @foreach ([
            ['Desarrollador Backend', 'Nimbus Corp', EstadoDeMiPostulacion::Entrevista],
            ['Data Analyst', 'Vela Studio', EstadoDeMiPostulacion::EnRevision],
            ['QA Engineer', 'Orbita Labs', EstadoDeMiPostulacion::Postulado],
            ['Product Designer', 'Vela Studio', EstadoDeMiPostulacion::NoSeleccionado],
        ] as [$vacante, $empresa, $estado])
            <div class="flex items-center justify-between gap-2 rounded-md bg-white p-3 shadow-sm">
                <span class="min-w-0">
                    <span class="block truncate text-13 font-semibold">{{ $vacante }}</span>
                    <span class="block text-12 text-neutral-700">{{ $empresa }}</span>
                </span>
                <x-ui.estado :valor="$estado" class="shrink-0 !text-10" />
            </div>
        @endforeach

        {{-- Su CV, una sola vez --}}
        <div class="flex items-center gap-2 rounded-md border border-divider p-3">
            <x-ui.icon name="file-text" :size="18" class="text-accent-700" />
            <span class="min-w-0 flex-1">
                <span class="block truncate text-12 font-semibold">cv_andrea.pdf</span>
                <span class="block text-11 text-neutral-700">CV cargado y listo</span>
            </span>
        </div>
    </div>
</div>
