{{-- Pantalla "Entrevistas" del prototipo (TalentCloud App.dc.html, isEntrevistas). --}}
@use('App\Support\EnlaceExterno')
@use('App\Support\Formato')
@use('App\Enums\ModalidadEntrevista')
<div>
    <x-ui.page-header title="Entrevistas" subtitle="Agenda de entrevistas programadas." compact>
        <x-slot:actions>
            @can('create', App\Models\Entrevista::class)
                <x-ui.button variant="primary" wire:click="abrirFormulario">+ Programar entrevista</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.listado-controles placeholder="Buscar candidato o vacante…" />

    @error('entrevista')
        <p class="field-error -mt-2 mb-4 text-14" role="alert">{{ $message }}</p>
    @enderror

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="calendar" message="No se encontraron entrevistas." />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Candidato</th><th>Vacante</th>@if ($vistaGlobal)<th>Empresa</th>@endif<th>Entrevistador</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
            </x-slot:head>
            @foreach ($filas as $entrevista)
                <tr wire:key="entrevista-{{ $entrevista->id }}">
                    <td class="tc-row" wire:click="verDetalle({{ $entrevista->id }})">{{ $entrevista->postulacion?->candidato?->nombre }}</td>
                    <td>{{ $entrevista->postulacion?->vacante?->titulo }}</td>
                    @if ($vistaGlobal)
                        <td>{{ $entrevista->postulacion?->vacante?->empresa?->nombre }}</td>
                    @endif
                    <td>{{ $entrevista->entrevistador?->name }}</td>
                    <td class="text-neutral-600">{{ Formato::fechaHora($entrevista->inicia_at) }}</td>
                    <td><x-ui.estado :valor="$entrevista->estado" /></td>
                    <td>
                        <x-ui.button
                            variant="ghost"
                            size="sm"
                            wire:click="confirmarCancelacion({{ $entrevista->id }})"
                            :disabled="! $entrevista->estado->puedeCancelarse()"
                        >Cancelar</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}
    @endif

    {{-- Detalle (prototipo: "Entrevista · {candidato}") + confirmar y completar --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="'Entrevista · '.$seleccionada?->postulacion?->candidato?->nombre" max-width="480px">
        @if ($seleccionada)
            <p class="m-0 whitespace-pre-line">Vacante: {{ Formato::vacante($seleccionada->postulacion?->vacante) }}
Entrevistador: {{ $seleccionada->entrevistador?->name }}
Fecha: {{ Formato::fechaHora($seleccionada->inicia_at) }} ({{ $seleccionada->duracion_min }} min · {{ $seleccionada->modalidad->etiqueta() }})
Estado: {{ $seleccionada->estado->etiqueta() }}@if ($seleccionada->notas)

Notas: {{ $seleccionada->notas }}@endif</p>
            @if ($enlace = EnlaceExterno::seguro($seleccionada->enlace))
                <p class="m-0 mt-2"><a href="{{ $enlace }}" target="_blank" rel="noopener noreferrer">Abrir enlace de la videollamada</a></p>
            @endif
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($seleccionada)
                <x-ui.button :href="route('candidatos.show', $seleccionada->postulacion?->candidato_id)" wire:navigate>Ver candidato</x-ui.button>
                @can('update', $seleccionada)
                    @if ($seleccionada->estado->puedeCompletarse() && ! $seleccionada->inicia_at->isFuture())
                        <x-ui.button wire:click="completar({{ $seleccionada->id }})">Marcar como completada</x-ui.button>
                    @endif
                    @if ($seleccionada->estado->puedeReprogramarse())
                        <x-ui.button wire:click="abrirReprogramacion({{ $seleccionada->id }})">Reprogramar</x-ui.button>
                    @endif
                    @if ($seleccionada->estado->puedeConfirmarse())
                        <x-ui.button variant="primary" wire:click="confirmar({{ $seleccionada->id }})">Confirmar</x-ui.button>
                    @endif
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Reprogramar: nueva fecha, duración, modalidad y enlace (un solo aviso al candidato y al entrevistador) --}}
    <x-ui.dialog wire:model="mostrarReprogramacion" :title="'Reprogramar entrevista de '.$seleccionada?->postulacion?->candidato?->nombre" max-width="480px">
        <form wire:submit="reprogramar" id="form-reprogramar-entrevista" class="grid gap-3">
            @error('reprogramacion')
                <p class="field-error m-0" role="alert">{{ $message }}</p>
            @enderror
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Nueva fecha y hora" for="reprogramar-inicio" error="reprogramacion.iniciaAt">
                    <x-ui.input type="datetime-local" wire:model="reprogramacion.iniciaAt" id="reprogramar-inicio" error="reprogramacion.iniciaAt" />
                </x-ui.field>
                <x-ui.field label="Duración" for="reprogramar-duracion" error="reprogramacion.duracionMin">
                    <x-ui.select wire:model="reprogramacion.duracionMin" id="reprogramar-duracion" :options="$duraciones" class="w-full" />
                </x-ui.field>
            </div>
            <x-ui.field label="Modalidad" for="reprogramar-modalidad" error="reprogramacion.modalidad">
                <x-ui.select wire:model.live="reprogramacion.modalidad" id="reprogramar-modalidad" :options="$modalidades" class="w-full" />
            </x-ui.field>
            @if ($reprogramacion->modalidad === ModalidadEntrevista::Videollamada->value)
                <x-ui.field label="Enlace de la videollamada (opcional)" for="reprogramar-enlace" error="reprogramacion.enlace">
                    <x-ui.input type="url" wire:model="reprogramacion.enlace" id="reprogramar-enlace" placeholder="https://meet.google.com/…" error="reprogramacion.enlace" />
                </x-ui.field>
            @endif
            <p class="m-0 text-13 text-neutral-700">Se avisará al candidato y al entrevistador. Si estaba confirmada, vuelve a quedar pendiente de confirmar.</p>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-reprogramar-entrevista" variant="primary" wire:loading.attr="disabled" wire:target="reprogramar">Reprogramar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Cancelar (textos del prototipo) --}}
    @php($aCancelar = $seleccionada?->postulacion?->candidato?->nombre ?? '')
    <x-ui.confirm wire:model="mostrarCancelacion" :title="'Cancelar entrevista de '.$aCancelar" action-label="Cancelar entrevista" action="cancelar">
        Se notificará al candidato y al entrevistador. Esta acción no se puede deshacer.
    </x-ui.confirm>

    {{-- "+ Programar entrevista" --}}
    <x-ui.dialog wire:model="mostrarFormulario" title="Programar entrevista" max-width="480px">
        <form wire:submit="programar" id="form-programar-entrevista" class="grid gap-3">
            <x-ui.field label="Candidato y vacante" for="entrevista-postulacion" error="postulacionId">
                <x-ui.select wire:model.live="postulacionId" id="entrevista-postulacion" :options="$postulaciones" placeholder="Elige un candidato" class="w-full" error="postulacionId" />
            </x-ui.field>
            <x-ui.field label="Entrevistador" for="entrevista-entrevistador" error="entrevistadorId"
                :hint="$this->esAdmin() && $postulacionId === '' ? 'Elige primero el candidato: el entrevistador es de su empresa.' : null">
                <x-ui.select wire:model="entrevistadorId" id="entrevista-entrevistador" :options="$entrevistadores" placeholder="Elige un entrevistador" class="w-full" error="entrevistadorId" />
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Fecha y hora" for="entrevista-inicio" error="iniciaAt">
                    <x-ui.input type="datetime-local" wire:model="iniciaAt" id="entrevista-inicio" error="iniciaAt" />
                </x-ui.field>
                <x-ui.field label="Duración" for="entrevista-duracion" error="duracionMin">
                    <x-ui.select wire:model="duracionMin" id="entrevista-duracion" :options="$duraciones" class="w-full" />
                </x-ui.field>
            </div>
            <x-ui.field label="Modalidad" for="entrevista-modalidad" error="modalidad">
                <x-ui.select wire:model.live="modalidad" id="entrevista-modalidad" :options="$modalidades" class="w-full" />
            </x-ui.field>
            @if ($modalidad === ModalidadEntrevista::Videollamada->value)
                <x-ui.field label="Enlace de la videollamada (opcional)" for="entrevista-enlace" error="enlace">
                    <x-ui.input type="url" wire:model="enlace" id="entrevista-enlace" placeholder="https://meet.google.com/…" error="enlace" />
                </x-ui.field>
            @endif
            <x-ui.field label="Notas para el entrevistador (opcional)" for="entrevista-notas" error="notas">
                <x-ui.textarea wire:model="notas" id="entrevista-notas" rows="2" error="notas" />
            </x-ui.field>
            @if ($puedeMoverAEntrevista)
                {{-- Para que el Kanban refleje la entrevista; el reclutador puede desmarcarlo. --}}
                <x-ui.checkbox wire:model="moverAEntrevista" id="entrevista-mover">Mover al candidato a la etapa «Entrevista» del Kanban</x-ui.checkbox>
            @endif
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-programar-entrevista" variant="primary" wire:loading.attr="disabled" wire:target="programar">Programar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
