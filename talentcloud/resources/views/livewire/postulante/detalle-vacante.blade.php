{{-- Detalle de una vacante publicada y "Postular con mi CV" (ampliación sobre el prototipo). --}}
@use('App\Enums\EstadoProcesamientoCv')
@use('App\Enums\TipoRequisito')
@use('App\Support\EnlaceExterno')
@use('App\Support\Formato')
@php($cvListo = $cv?->estado_procesamiento === EstadoProcesamientoCv::Procesado)
@php($cvAnalizandose = in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true))
<div @if ($cvAnalizandose) wire:poll.4s @endif>
    @if ($vacante === null)
        {{-- Se cerró (o su empresa se suspendió) mientras el postulante la miraba. --}}
        <x-ui.empty-state icon="briefcase" message="Esta vacante ya no recibe postulaciones.">
            <x-ui.button class="mt-4" :href="route('postulante.vacantes')" wire:navigate>Ver otras vacantes</x-ui.button>
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
            <div class="grid gap-8">
                <section>
                    <h2 class="mb-2 text-16">Descripción del puesto</h2>
                    <p class="m-0 whitespace-pre-line text-15 text-neutral-800">{{ $vacante->descripcion ?: 'La empresa no añadió una descripción.' }}</p>
                    @if ($vacante->publicada_at)
                        <p class="mb-0 mt-3 text-13 text-neutral-700">Publicada el {{ Formato::fechaCorta($vacante->publicada_at) }}.</p>
                    @endif
                </section>

                @php($empresa = $vacante->empresa)
                @php($sitioWeb = EnlaceExterno::seguro($empresa?->sitio_web))
                @if ($empresa && ($empresa->sector || $empresa->descripcion || $sitioWeb))
                    <section>
                        <h2 class="mb-2 text-16">Sobre {{ $empresa->nombre }}</h2>
                        @if ($empresa->sector)
                            <p class="mb-2 text-13 text-neutral-700">{{ $empresa->sector }}</p>
                        @endif
                        @if ($empresa->descripcion)
                            <p class="m-0 whitespace-pre-line text-15 text-neutral-800">{{ $empresa->descripcion }}</p>
                        @endif
                        @if ($sitioWeb)
                            <p class="mb-0 mt-2 text-14"><a href="{{ $sitioWeb }}" target="_blank" rel="noopener noreferrer">{{ EnlaceExterno::dominio($sitioWeb) }}</a></p>
                        @endif
                    </section>
                @endif

                <section>
                    <h2 class="mb-1 text-16">Requisitos</h2>
                    <p class="mb-3 text-13 text-neutral-700">La IA comparará tu CV con cada requisito; la decisión final es siempre de la empresa.</p>
                    @if ($vacante->requisitos->isEmpty())
                        <p class="text-14 text-neutral-700">Sin requisitos publicados.</p>
                    @else
                        <ul class="m-0 grid list-none gap-2 p-0">
                            @foreach ($vacante->requisitos as $requisito)
                                <li class="flex flex-wrap items-center gap-2 border-b border-divider pb-2">
                                    <span class="text-15">{{ $requisito->competencia?->nombre }}</span>
                                    <x-ui.estado :valor="$requisito->tipo" />
                                    @if ($requisito->anios_minimos)
                                        <span class="text-13 text-neutral-700">{{ $requisito->anios_minimos }} {{ $requisito->anios_minimos === 1 ? 'año' : 'años' }} de experiencia</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Postulación --}}
            <x-ui.card elev="sm" class="!bg-white">
                <div class="card-kicker">Tu postulación</div>

                @if ($postulado)
                    <p class="m-0 text-15" role="status">
                        <strong class="font-semibold">¡Listo! Te postulaste a {{ $vacante->titulo }}.</strong>
                        {{ $vacante->empresa?->nombre }} revisará tu CV y la IA ya lo está comparando con los requisitos.
                    </p>
                    <x-ui.button variant="primary" :href="route('postulante.postulaciones')" wire:navigate>Ver mis postulaciones</x-ui.button>
                @elseif ($yaPostulo)
                    <div><x-ui.tag tone="accent">Ya postulaste</x-ui.tag></div>
                    <p class="m-0 text-14 text-neutral-800">Sigue el estado de este proceso en tus postulaciones.</p>
                    <x-ui.button :href="route('postulante.postulaciones')" wire:navigate>Ver mis postulaciones</x-ui.button>
                @else
                    @if ($cvListo)
                        <p class="m-0 text-14 text-neutral-800">Se enviará tu CV <strong class="font-semibold">{{ $cv->nombre_original }}</strong>. La empresa lo verá junto con el análisis de la IA.</p>
                    @elseif ($cvAnalizandose)
                        <p class="m-0 text-14 text-neutral-800" role="status">Tu CV se está analizando. Podrás postular en unos segundos.</p>
                    @elseif ($cv === null)
                        <p class="m-0 text-14 text-neutral-800">Para postular necesitas subir tu CV.</p>
                    @else
                        <p class="m-0 text-14 text-accent-2-700">Tu CV no se pudo leer. Sube una versión en PDF con texto o en Word para postular.</p>
                    @endif

                    @error('postulacion')
                        <p class="field-error m-0" role="alert">{{ $message }}</p>
                    @enderror

                    @if ($cvListo)
                        <x-ui.button variant="primary" block wire:click="confirmar">Postular con mi CV</x-ui.button>
                    @elseif (! $cvAnalizandose)
                        <x-ui.button :href="route('postulante.postulaciones')" wire:navigate>Ir a Mi currículo</x-ui.button>
                    @endif
                @endif
            </x-ui.card>
        </div>

        <x-ui.dialog wire:model="mostrarConfirmacion" title="Postular a {{ $vacante->titulo }}" max-width="440px">
            <p class="m-0">Enviaremos tu CV {{ $cv?->nombre_original }} a {{ $vacante->empresa?->nombre }}. La empresa verá tu CV y el análisis de la IA; tú podrás seguir el estado en «Mis postulaciones».</p>
            <x-slot:actions>
                <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
                <x-ui.button variant="primary" wire:click="postular" wire:loading.attr="disabled" wire:target="postular">Postular</x-ui.button>
            </x-slot:actions>
        </x-ui.dialog>
    @endif
</div>
