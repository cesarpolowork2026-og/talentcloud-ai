{{-- Detalle de vacante: datos, requisitos y ciclo de vida, con los componentes del prototipo. --}}
@use('App\Enums\EstadoVacante')
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header :title="$vacante->titulo" compact>
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => route('vacantes.index')], ['label' => $vacante->titulo]]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @if (Route::has('seleccion.show'))
                <x-ui.button :href="route('seleccion.show', $vacante)" wire:navigate>Ver selección</x-ui.button>
            @endif
            @can('cambiarEstado', $vacante)
                @foreach ($transiciones as $destino)
                    @if ($destino === EstadoVacante::Cerrada)
                        <x-ui.button variant="danger" wire:click="$set('mostrarCierre', true)">{{ $destino->accionDesde($vacante->estado) }}</x-ui.button>
                    @else
                        <x-ui.button :variant="$destino === EstadoVacante::Abierta ? 'primary' : 'secondary'" wire:click="cambiarEstado('{{ $destino->value }}')">
                            {{ $destino->accionDesde($vacante->estado) }}
                        </x-ui.button>
                    @endif
                @endforeach
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2 text-15 text-neutral-700">
        <x-ui.estado :valor="$vacante->estado" />
        <span>{{ $vacante->empresa?->nombre }} · {{ $vacante->lugarYModalidad() }} · {{ $vacante->postulaciones_count }} {{ $vacante->postulaciones_count === 1 ? 'candidato' : 'candidatos' }}</span>
    </div>

    @error('estado')
        <p class="field-error -mt-3 mb-4 text-14" role="alert">{{ $message }}</p>
    @enderror

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        {{-- Requisitos --}}
        <section>
            <h2 class="mb-1 text-16">Requisitos</h2>
            <p class="mb-3 text-13 text-neutral-600">Lo que pide la vacante. La importancia (de 1 a 5) indica cuánto pesa cada requisito al revisar a los candidatos.</p>

            @if ($vacante->requisitos->isEmpty())
                <x-ui.empty-state icon="list-checks" message="Todavía no hay requisitos. Agrega al menos uno para publicar la vacante." class="!py-6" />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Competencia</th><th>Tipo</th><th>Importancia</th><th>Años mín.</th>
                        @if ($editable)
                            <th><span class="sr-only">Acciones</span></th>
                        @endif
                    </x-slot:head>
                    @foreach ($vacante->requisitos as $requisito)
                        <tr wire:key="requisito-{{ $requisito->id }}">
                            <td>
                                {{ $requisito->competencia->nombre }}
                                @if ($requisito->descripcion)
                                    <span class="block text-13 text-neutral-600">{{ $requisito->descripcion }}</span>
                                @endif
                            </td>
                            <td><x-ui.estado :valor="$requisito->tipo" /></td>
                            <td>{{ $requisito->peso }}/5</td>
                            <td>{{ $requisito->anios_minimos ?? '—' }}</td>
                            @if ($editable)
                                <td class="whitespace-nowrap text-right">
                                    <x-ui.button variant="ghost" size="sm" wire:click="editarRequisito({{ $requisito->id }})">Editar</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="confirmarQuitarRequisito({{ $requisito->id }})">Quitar</x-ui.button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif

            @if ($editable && ! $mostrarEdicionRequisito)
                <form wire:submit="agregarRequisito" class="mt-4 grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto_90px_auto]">
                    <x-ui.field label="Competencia" for="req-competencia" error="competencia">
                        <x-ui.input wire:model="competencia" id="req-competencia" list="catalogo-competencias" placeholder="Java" error="competencia" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.field label="Tipo" for="req-tipo">
                        <x-ui.select wire:model="tipo" id="req-tipo" :options="$tipos" />
                    </x-ui.field>
                    <x-ui.field label="Importancia" for="req-peso">
                        <x-ui.select wire:model="peso" id="req-peso" :options="$pesos" />
                    </x-ui.field>
                    <x-ui.field label="Años mín." for="req-anios" error="aniosMinimos">
                        <x-ui.input wire:model="aniosMinimos" id="req-anios" type="number" min="0" max="40" error="aniosMinimos" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled">Agregar</x-ui.button>
                </form>
                <datalist id="catalogo-competencias">
                    @foreach ($catalogo as $nombre)
                        <option value="{{ $nombre }}"></option>
                    @endforeach
                </datalist>
            @endif
        </section>

        {{-- Datos de la vacante --}}
        <x-ui.card kicker="Datos de la vacante" class="!p-6">
            <form wire:submit="guardarDatos" class="contents">
                <x-ui.field label="Título del puesto" for="vacante-titulo" error="titulo">
                    <x-ui.input wire:model="titulo" id="vacante-titulo" error="titulo" :disabled="! $editable" />
                </x-ui.field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Modalidad" for="vacante-modalidad">
                        <x-ui.select wire:model.live="modalidad" id="vacante-modalidad" :options="$modalidades" class="w-full" :disabled="! $editable" />
                    </x-ui.field>
                    @if ($modalidad === 'remoto')
                        <x-ui.field label="Cobertura (opcional)" for="vacante-ubicacion" error="ubicacion" hint="Dónde puede vivir quien postule. Vacío = desde cualquier lugar.">
                            <x-ui.input wire:model="ubicacion" id="vacante-ubicacion" placeholder="Perú, LATAM…" error="ubicacion" :disabled="! $editable" />
                        </x-ui.field>
                    @else
                        <x-ui.field label="Ubicación" for="vacante-ubicacion" error="ubicacion">
                            <x-ui.input wire:model="ubicacion" id="vacante-ubicacion" placeholder="Lima" error="ubicacion" :disabled="! $editable" />
                        </x-ui.field>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.field label="Jornada (opcional)" for="vacante-jornada" error="jornada">
                        <x-ui.select wire:model="jornada" id="vacante-jornada" :options="$jornadas" placeholder="Sin indicar" class="w-full" error="jornada" :disabled="! $editable" />
                    </x-ui.field>
                    <x-ui.field label="Sueldo mensual mínimo (S/)" for="vacante-sueldo-minimo" error="sueldoMinimo" hint="Opcional. Quien postula lo ve en la bolsa de empleos.">
                        <x-ui.input wire:model="sueldoMinimo" id="vacante-sueldo-minimo" inputmode="numeric" placeholder="1500" error="sueldoMinimo" :disabled="! $editable" />
                    </x-ui.field>
                    <x-ui.field label="Sueldo mensual máximo (S/)" for="vacante-sueldo-maximo" error="sueldoMaximo">
                        <x-ui.input wire:model="sueldoMaximo" id="vacante-sueldo-maximo" inputmode="numeric" placeholder="2200" error="sueldoMaximo" :disabled="! $editable" />
                    </x-ui.field>
                </div>
                <x-ui.field label="Descripción" for="vacante-descripcion" error="descripcion">
                    <x-ui.textarea wire:model="descripcion" id="vacante-descripcion" rows="6" placeholder="Responsabilidades, contexto del equipo, condiciones…" error="descripcion" :disabled="! $editable" />
                </x-ui.field>
                @if ($editable)
                    <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Guardar cambios</x-ui.button>
                    <x-ui.action-message on="vacante-guardada" />
                @else
                    <p class="card-body">{{ $vacante->estado->esEditable() ? 'Solo la empresa puede modificar esta vacante: tú la estás supervisando.' : 'Una vacante cerrada no se puede modificar.' }}</p>
                @endif
            </form>
        </x-ui.card>
    </div>

    {{-- Editar requisito --}}
    <x-ui.dialog wire:model="mostrarEdicionRequisito" title="Editar requisito" max-width="460px">
        <form wire:submit="guardarRequisito" id="form-requisito" class="grid gap-3">
            <x-ui.field label="Competencia" for="edit-competencia" error="competencia">
                <x-ui.input wire:model="competencia" id="edit-competencia" list="catalogo-competencias" error="competencia" autocomplete="off" />
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.field label="Tipo" for="edit-tipo">
                    <x-ui.select wire:model="tipo" id="edit-tipo" :options="$tipos" class="w-full" />
                </x-ui.field>
                <x-ui.field label="Importancia" for="edit-peso">
                    <x-ui.select wire:model="peso" id="edit-peso" :options="$pesos" class="w-full" />
                </x-ui.field>
                <x-ui.field label="Años mín." for="edit-anios" error="aniosMinimos">
                    <x-ui.input wire:model="aniosMinimos" id="edit-anios" type="number" min="0" max="40" error="aniosMinimos" />
                </x-ui.field>
            </div>
            <x-ui.field label="Detalle (opcional)" for="edit-detalle" error="detalleRequisito">
                <x-ui.input wire:model="detalleRequisito" id="edit-detalle" placeholder="APIs REST en producción" error="detalleRequisito" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button wire:click="cancelarEdicion">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-requisito" variant="primary" wire:loading.attr="disabled">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Quitar requisito --}}
    <x-ui.confirm wire:model="mostrarQuitarRequisito" title="Quitar requisito" action-label="Quitar" action="quitarRequisito">
        Se quitará {{ $requisitoSeleccionado?->competencia?->nombre ?? 'el requisito' }} de la vacante. Las postulaciones ya recibidas no cambian.
    </x-ui.confirm>

    {{-- Cerrar vacante --}}
    <x-ui.confirm wire:model="mostrarCierre" :title="'Cerrar '.$vacante->titulo" action-label="Cerrar vacante" action="cambiarEstado('cerrada')">
        La vacante dejará de recibir postulaciones y no se podrá volver a abrir. A quienes sigan en el proceso se les avisará que terminó. Las postulaciones y evaluaciones se conservan.
    </x-ui.confirm>
</div>
