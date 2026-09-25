{{-- Detalle público de una vacante abierta (ampliación sobre el prototipo). --}}
<div>
    @if ($vacante === null)
        <x-ui.empty-state icon="briefcase" message="Esta vacante ya no recibe postulaciones.">
            <x-ui.button class="mt-4" :href="route('empleos.index')" wire:navigate>Ver otras vacantes</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.page-header :title="$vacante->titulo" :subtitle="$vacante->empresa?->nombre.' · '.$vacante->lugarYModalidad()">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Empleos', 'href' => route('empleos.index')], ['label' => $vacante->titulo]]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <x-vacantes.ficha-publica :vacante="$vacante" />

            <x-ui.card elev="sm" class="!bg-white">
                <div class="card-kicker">Postular</div>

                @if (session('aviso'))
                    <p class="m-0 text-14 text-accent-2-700" role="status">{{ session('aviso') }}</p>
                @endif

                @guest
                    <p class="m-0 text-14 text-neutral-800">Postula con tu CV: la IA lo compara con cada requisito y tú sigues el estado del proceso.</p>
                    <x-ui.button variant="primary" block :href="route('empleos.postular', $vacante->id)">Iniciar sesión para postular</x-ui.button>
                    <x-ui.button block :href="route('empleos.postular', ['id' => $vacante->id, 'cuenta' => 'nueva'])">Crear mi cuenta de postulante</x-ui.button>
                @else
                    @if (auth()->user()->tieneRol(App\Enums\Rol::Postulante))
                        <p class="m-0 text-14 text-neutral-800">Postula con el CV de tu cuenta.</p>
                        <x-ui.button variant="primary" block :href="route('empleos.postular', $vacante->id)">Postular con mi CV</x-ui.button>
                    @else
                        <p class="m-0 text-14 text-neutral-800">Para postular, inicia sesión con una cuenta de postulante.</p>
                    @endif
                @endguest
            </x-ui.card>
        </div>
    @endif
</div>
