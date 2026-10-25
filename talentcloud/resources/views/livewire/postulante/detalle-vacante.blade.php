{{-- Detalle de una vacante publicada y "Postular con mi CV" (ampliación sobre el prototipo). --}}
@use('App\Enums\EstadoProcesamientoCv')
@php($cvListo = $cv?->estado_procesamiento === EstadoProcesamientoCv::Procesado)
@php($cvProcesandose = in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true))
<div @if ($cvProcesandose) wire:poll.4s="esperarCv" @endif>
    @if ($vacante === null)
        {{-- Se cerró (o su empresa se suspendió) mientras el postulante la miraba. --}}
        <x-ui.empty-state icon="briefcase" message="Esta vacante ya no recibe postulaciones.">
            @if ($yaPostulo)
                <p class="m-0 mt-2 text-14 text-neutral-800">Tu postulación sigue en «Mis postulaciones», con su estado.</p>
                <x-ui.button class="mt-4" :href="route('postulante.postulaciones')" wire:navigate>Ver mis postulaciones</x-ui.button>
            @else
                <x-ui.button class="mt-4" :href="route('postulante.vacantes')" wire:navigate>Ver otras vacantes</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        <x-ui.page-header
            :title="$vacante->titulo"
            :subtitle="$vacante->empresa?->nombre.' · '.$vacante->lugarYModalidad()"
        >
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => route('postulante.vacantes')], ['label' => $vacante->titulo]]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <x-vacantes.ficha-publica :vacante="$vacante" />

            {{-- Postulación --}}
            <x-ui.card elev="sm" class="!bg-white">
                <div class="card-kicker">Tu postulación</div>

                @if ($postulado)
                    <p class="m-0 text-15" role="status">
                        <strong class="font-semibold">¡Listo! Te postulaste a {{ $vacante->titulo }}.</strong>
                        {{ $vacante->empresa?->nombre }} revisará tu CV con los requisitos de la vacante.
                    </p>
                    <x-ui.button variant="primary" :href="route('postulante.postulaciones')" wire:navigate>Ver mis postulaciones</x-ui.button>
                @elseif ($laRetiro)
                    <div><x-ui.tag tone="outline">Retiraste tu postulación</x-ui.tag></div>
                    <p class="m-0 text-14 text-neutral-800">Ya no participas en este proceso.</p>
                    <x-ui.button :href="route('postulante.vacantes')" wire:navigate>Ver otras vacantes</x-ui.button>
                @elseif ($yaPostulo)
                    <div><x-ui.tag tone="accent">Ya postulaste</x-ui.tag></div>
                    <p class="m-0 text-14 text-neutral-800">Sigue el estado de este proceso en tus postulaciones.</p>
                    <x-ui.button :href="route('postulante.postulaciones')" wire:navigate>Ver mis postulaciones</x-ui.button>
                @else
                    @if ($cvListo && $cvSubidoAqui)
                        <p class="m-0 text-14 text-neutral-800" role="status"><strong class="font-semibold">Tu CV ya está listo, pero aún no te postulaste.</strong> Pulsa «Postular con mi CV» para enviar <strong class="font-semibold">{{ $cv->nombre_original }}</strong> a {{ $vacante->empresa?->nombre }}.</p>
                    @elseif ($cvListo)
                        <p class="m-0 text-14 text-neutral-800">Se enviará tu CV <strong class="font-semibold">{{ $cv->nombre_original }}</strong>. La empresa lo verá junto con tu perfil.</p>
                    @elseif ($cvProcesandose)
                        <p class="m-0 text-14 text-neutral-800" role="status">Tu CV se está procesando. En unos segundos te pediremos confirmar la postulación.</p>
                    @elseif ($cv === null)
                        <p class="m-0 text-14 text-neutral-800">Para postular, primero sube tu CV; después confirmas la postulación.</p>
                    @else
                        <p class="m-0 text-14 text-accent-2-700">Tu CV no se pudo leer. Sube una versión en PDF con texto o en Word para postular.</p>
                    @endif

                    @error('postulacion')
                        <p class="field-error m-0" role="alert">{{ $message }}</p>
                    @enderror

                    @if ($cvListo)
                        <x-ui.button variant="primary" block wire:click="confirmar">Postular con mi CV</x-ui.button>
                    @elseif (! $cvProcesandose)
                        {{-- Sube el CV aquí mismo: al terminar el análisis aparece "Postular con mi CV". --}}
                        <livewire:postulante.mi-curriculo solo-subida />
                    @endif
                @endif
            </x-ui.card>
        </div>

        <x-ui.dialog wire:model="mostrarConfirmacion" title="Postular a {{ $vacante->titulo }}" max-width="440px">
            <p class="m-0">Enviaremos tu CV {{ $cv?->nombre_original }} a {{ $vacante->empresa?->nombre }}. La empresa verá tu CV; tú podrás seguir el estado en «Mis postulaciones».</p>
            <x-slot:actions>
                <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
                <x-ui.button variant="primary" wire:click="postular" wire:loading.attr="disabled" wire:target="postular">Postular</x-ui.button>
            </x-slot:actions>
        </x-ui.dialog>
    @endif
</div>
