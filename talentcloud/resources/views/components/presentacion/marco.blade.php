{{--
    Ventana de la app para las maquetas de la presentación: la misma barra de
    navegación que layouts.app, a tamaño reducido. activo: enlace resaltado.
--}}
@props(['activo' => 'Kanban'])

<div class="flex h-full flex-col overflow-hidden rounded-lg border border-divider bg-bg shadow-md">
    <div class="flex shrink-0 items-center gap-3 border-b border-divider bg-white px-5 py-3">
        <x-marca tamano="pequeno" />
        <div class="mx-auto flex gap-3.5 text-11">
            @foreach (['Dashboard', 'Vacantes', 'Kanban', 'Candidatos', 'Matching', 'Entrevistas', 'Evaluaciones'] as $enlace)
                <span @class(['text-accent-700 underline decoration-2 underline-offset-[6px]' => $enlace === $activo])>{{ $enlace }}</span>
            @endforeach
        </div>
        <x-ui.icon name="bell" :size="14" class="text-neutral-700" />
        <div class="flex items-center gap-1.5 text-11 text-neutral-700">
            <span class="grid h-5 w-5 place-items-center rounded-full bg-accent-100 text-10 font-semibold text-accent-800">R</span>
            Reclutador
        </div>
    </div>
    <div class="min-h-0 flex-1 px-6 py-5">
        {{ $slot }}
    </div>
</div>
