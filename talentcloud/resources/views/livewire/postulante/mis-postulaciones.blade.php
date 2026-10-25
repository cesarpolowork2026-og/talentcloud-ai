{{-- "Mis postulaciones" del prototipo (TalentCloud App.dc.html, isPostulante). --}}
@use('App\Enums\EstadoDeMiPostulacion')
@use('App\Livewire\Postulante\MisPostulaciones')
@use('App\Support\Formato')
<div wire:poll.15s.visible="revisarCambios">
    <x-ui.page-header title="Mis postulaciones" subtitle="Sigue el estado de tus procesos de selección." />

    {{-- Quitar de la lista (solo se oculta aquí: la empresa conserva su historial) y volver a verlas. --}}
    @if ($verQuitadas || $finalizadas > 0 || $quitadas > 0)
        <div class="-mt-3 mb-4 flex flex-wrap items-center gap-3 text-14">
            @if ($verQuitadas)
                <span class="text-neutral-700">Estas son las postulaciones que quitaste de tu lista. Puedes volver a mostrar cualquiera.</span>
                <x-ui.button size="sm" wire:click="alternarQuitadas">Volver a mis postulaciones</x-ui.button>
            @else
                @if ($finalizadas > 0)
                    <x-ui.button size="sm" wire:click="$set('mostrarQuitarFinalizadas', true)">Quitar las finalizadas ({{ $finalizadas }})</x-ui.button>
                @endif
                @if ($quitadas > 0)
                    <button type="button" wire:click="alternarQuitadas" class="text-neutral-700 underline hover:text-accent-700">Ver las que quitaste ({{ $quitadas }})</button>
                @endif
            @endif
            <x-ui.action-message on="postulaciones-quitadas">Listo: las quitamos de tu lista. Puedes volver a verlas en «Ver las que quitaste».</x-ui.action-message>
        </div>
    @endif

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        @if ($postulaciones->isEmpty() && $verQuitadas)
            <x-ui.empty-state icon="eye" message="No quitaste ninguna postulación de tu lista." />
        @elseif ($postulaciones->isEmpty() && $quitadas > 0)
            <x-ui.empty-state icon="check-circle" message="No tienes postulaciones en tu lista: quitaste todas.">
                <x-ui.button variant="primary" class="mt-4" :href="route('postulante.vacantes')" wire:navigate>Ver vacantes abiertas</x-ui.button>
            </x-ui.empty-state>
        @elseif ($postulaciones->isEmpty())
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
            @if ($seleccionada?->oculta_para_postulante_at)
                <x-ui.button variant="ghost" wire:click="volverAMostrar">Volver a mostrar en mi lista</x-ui.button>
            @elseif ($seleccionada)
                <x-ui.button variant="ghost" wire:click="confirmarQuitar">Quitar de mi lista</x-ui.button>
                @if (EstadoDeMiPostulacion::de($seleccionada)->puedeRetirarse())
                    <x-ui.button variant="ghost" wire:click="confirmarRetiro">Retirar postulación</x-ui.button>
                @endif
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

    {{-- Quitar de mi lista: si sigue en curso, antes se retira. --}}
    @php($enCurso = $seleccionada && EstadoDeMiPostulacion::de($seleccionada)->puedeRetirarse())
    <x-ui.confirm
        wire:model="mostrarQuitar"
        :title="'Quitar '.($seleccionada?->vacante?->titulo ?? 'la postulación').' de tu lista'"
        :action-label="$enCurso ? 'Retirar y quitar' : 'Quitar de mi lista'"
        action="quitar"
    >
        @if ($enCurso)
            Esta postulación sigue en curso: para quitarla, primero se retira. {{ $seleccionada?->vacante?->empresa?->nombre }} dejará de considerarte y se cancelarán tus entrevistas pendientes. El retiro no se puede deshacer.
        @else
            Dejará de aparecer en «Mis postulaciones». No se borra nada: podrás volver a verla en «Ver las que quitaste».
        @endif
    </x-ui.confirm>

    <x-ui.confirm
        wire:model="mostrarQuitarFinalizadas"
        title="Quitar las postulaciones finalizadas"
        action-label="Quitar de mi lista"
        action="quitarFinalizadas"
    >
        Se quitarán de tu lista las {{ $finalizadas }} postulaciones que ya terminaron (contratado, no seleccionado, cerrada o retirada). Las que siguen en curso no cambian. No se borra nada: podrás volver a verlas en «Ver las que quitaste».
    </x-ui.confirm>
</div>
