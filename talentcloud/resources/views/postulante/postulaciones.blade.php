{{-- "Mis postulaciones" del prototipo. La tabla de postulaciones llega el día 17 del plan. --}}
<x-app-layout>
    <x-slot:title>Mis postulaciones</x-slot:title>

    <x-ui.page-header title="Mis postulaciones" subtitle="Sigue el estado de tus procesos de selección." />

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-ui.empty-state icon="briefcase" message="Todavía no tienes postulaciones." />

        <livewire:postulante.mi-curriculo />
    </div>
</x-app-layout>
