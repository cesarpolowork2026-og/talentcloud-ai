{{-- "Mis postulaciones" del prototipo (TalentCloud App.dc.html, isPostulante). --}}
@use('App\Enums\EstadoDeMiPostulacion')
@use('App\Livewire\Postulante\MisPostulaciones')
@use('App\Support\Formato')
<div wire:poll.15s.visible="revisarCambios">
    <x-ui.page-header title="Mis postulaciones" subtitle="Sigue el estado de tus procesos de selección." />

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        @if ($postulaciones->isEmpty())
            <x-ui.empty-state ilustracion="sin-postulaciones" titulo="Todavía no tienes postulaciones" message="Sube tu CV una sola vez y postula en un clic a las vacantes que te interesen.">
                <x-ui.button variant="primary" class="mt-4" :href="route('postulante.vacantes')" wire:navigate>Ver vacantes abiertas</x-ui.button>
            </x-ui.empty-state>
        @else
            {{-- Desde 768 px, la tabla; en el teléfono una tarjeta por postulación (la tabla recortaba la columna Fecha). --}}
            <ul class="m-0 grid list-none gap-3 p-0 md:hidden" aria-label="Mis postulaciones">
                @foreach ($postulaciones as $postulacion)
                    <li wire:key="mi-postulacion-tarjeta-{{ $postulacion->id }}">
                        <x-ui.card class="tarjeta-clicable" wire:click="verDetalle({{ $postulacion->id }})">
                            <div class="card-kicker">{{ $postulacion->vacante?->empresa?->nombre }}</div>
                            <div class="card-title m-0">
                                <x-ui.boton-fila wire:click.stop="verDetalle({{ $postulacion->id }})">{{ $postulacion->vacante?->titulo }}</x-ui.boton-fila>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <x-ui.estado :valor="EstadoDeMiPostulacion::de($postulacion)" />
                                <span class="text-13 text-neutral-600">Postulaste el {{ Formato::fechaCorta($postulacion->created_at) }}</span>
                            </div>
                            <x-postulante.progreso :estado="EstadoDeMiPostulacion::de($postulacion)" compacto />
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>

            <div class="hidden md:block">
            <x-ui.table>
                <x-slot:head>
                    <th>Vacante</th><th>Empresa</th><th>Estado</th><th>Fecha</th>
                </x-slot:head>
                @foreach ($postulaciones as $postulacion)
                    <tr class="tc-row" wire:key="mi-postulacion-{{ $postulacion->id }}" wire:click="verDetalle({{ $postulacion->id }})">
                        <td><x-ui.boton-fila wire:click.stop="verDetalle({{ $postulacion->id }})">{{ $postulacion->vacante?->titulo }}</x-ui.boton-fila></td>
                        <td>{{ $postulacion->vacante?->empresa?->nombre }}</td>
                        <td>
                            <x-ui.estado :valor="EstadoDeMiPostulacion::de($postulacion)" />
                            <x-postulante.progreso :estado="EstadoDeMiPostulacion::de($postulacion)" compacto class="mt-2" />
                        </td>
                        <td class="text-neutral-600">{{ Formato::fechaCorta($postulacion->created_at) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            </div>
        @endif

        <div class="grid gap-3">
            @if ($proximas->isNotEmpty())
                <x-ui.card elev="sm" class="!bg-white">
                    <div class="card-kicker">Próximas entrevistas</div>
                    <ul class="m-0 grid list-none gap-2 p-0">
                        @foreach ($proximas as $entrevista)
                            @php($suPostulacion = $postulaciones->firstWhere('id', $entrevista->postulacion_id))
                            <x-postulante.entrevista
                                :entrevista="$entrevista"
                                :vacante="$suPostulacion?->vacante?->titulo.' · '.$suPostulacion?->vacante?->empresa?->nombre"
                                wire:key="proxima-{{ $entrevista->id }}"
                            />
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
            <livewire:postulante.mi-curriculo />
            <p class="m-0 flex flex-wrap gap-x-4 gap-y-1 text-13">
                <a href="{{ route('postulante.mis-datos') }}" download class="text-neutral-700 underline hover:text-accent-700">Descargar mis datos</a>
                <a href="{{ route('profile') }}" wire:navigate class="text-neutral-700 underline hover:text-accent-700">Eliminar mi cuenta y mis datos</a>
            </p>
        </div>
    </div>

    {{-- Detalle (prototipo: título = vacante) --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->vacante?->titulo" max-width="460px">
        @if ($seleccionada)
            <x-postulante.progreso :estado="EstadoDeMiPostulacion::de($seleccionada)" class="mb-4" />
            <p class="m-0 whitespace-pre-line">{{ $seleccionada->vacante?->empresa?->nombre }}
Estado: {{ EstadoDeMiPostulacion::de($seleccionada)->etiqueta() }} — {{ EstadoDeMiPostulacion::de($seleccionada)->explicacion() }}
Postulación enviada el {{ Formato::fechaCorta($seleccionada->created_at) }}.</p>
            @php($susEntrevistas = $entrevistasPorPostulacion->get($seleccionada->id, collect()))
            @if ($susEntrevistas->isNotEmpty())
                <h3 class="mb-0 mt-4 text-15">Entrevistas</h3>
                <ul class="m-0 mt-2 grid list-none gap-2 p-0">
                    @foreach ($susEntrevistas as $entrevista)
                        <x-postulante.entrevista :entrevista="$entrevista" wire:key="entrevista-{{ $entrevista->id }}" />
                    @endforeach
                </ul>
            @endif

            @error('retiro')
                <p class="field-error m-0 mt-3" role="alert">{{ $message }}</p>
            @enderror
            <x-ui.action-message on="postulacion-retirada" class="mt-3">Retiraste tu postulación. Avisamos a la empresa.</x-ui.action-message>
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($seleccionada && EstadoDeMiPostulacion::de($seleccionada)->puedeRetirarse())
                <x-ui.button variant="ghost" wire:click="confirmarRetiro">Retirar postulación</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Retirar postulación --}}
    <x-ui.confirm
        wire:model="mostrarRetiro"
        :title="'Retirar tu postulación a '.($seleccionada?->vacante?->titulo ?? '')"
        action-label="Retirar postulación"
        action="retirar"
    >
        {{ $seleccionada?->vacante?->empresa?->nombre }} dejará de considerarte para esta vacante y se cancelarán tus entrevistas pendientes. No se puede deshacer.
    </x-ui.confirm>
</div>
