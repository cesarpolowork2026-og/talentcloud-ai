{{-- Pantalla "Evaluaciones" del prototipo (TalentCloud App.dc.html, isEvaluaciones). --}}
<div>
    <x-ui.page-header title="Evaluaciones" subtitle="Resultados de las evaluaciones registradas por los reclutadores." compact>
        <x-slot:actions>
            @can('create', App\Models\Evaluacion::class)
                <x-ui.button variant="primary" wire:click="abrirFormulario">+ Nueva evaluación</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.listado-controles placeholder="Buscar candidato o vacante…" />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="clipboard-text" message="No se encontraron evaluaciones." />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Candidato</th><th>Vacante</th><th>Evaluador</th><th>Puntaje</th><th>Estado</th>
            </x-slot:head>
            @foreach ($filas as $evaluacion)
                <tr class="tc-row" wire:key="evaluacion-{{ $evaluacion->id }}" wire:click="verDetalle({{ $evaluacion->id }})">
                    <td>{{ $evaluacion->postulacion?->candidato?->nombre }}</td>
                    <td>{{ $evaluacion->postulacion?->vacante?->titulo }}</td>
                    <td>{{ $evaluacion->evaluador?->name }}</td>
                    <td>{{ $evaluacion->puntaje ?? '—' }}/10</td>
                    <td><x-ui.estado :valor="$evaluacion->estado" /></td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}
    @endif

    {{-- Detalle (prototipo: "Evaluación · {candidato}") + criterios y comentario --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="'Evaluación · '.$seleccionada?->postulacion?->candidato?->nombre" max-width="480px">
        @if ($seleccionada)
            <p class="m-0 whitespace-pre-line">Vacante: {{ $seleccionada->postulacion?->vacante?->titulo }}
Evaluador: {{ $seleccionada->evaluador?->name }}
Puntaje: {{ $seleccionada->puntaje ?? '—' }}/10
Estado: {{ $seleccionada->estado->etiqueta() }}</p>
            @if ($seleccionada->criterios)
                <ul class="m-0 mt-3 grid gap-1 pl-0 text-14" aria-label="Criterios">
                    @foreach ($seleccionada->criterios as $criterio)
                        <li class="flex justify-between gap-3 border-b border-divider pb-1">
                            <span>{{ $criterio['nombre'] }}</span><span class="text-neutral-700">{{ $criterio['puntaje'] }}/10</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($seleccionada->comentario)
                <p class="m-0 mt-3 whitespace-pre-line text-14 italic text-neutral-800">"{{ $seleccionada->comentario }}"</p>
            @endif
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($seleccionada)
                <x-ui.button :href="route('candidatos.show', $seleccionada->postulacion?->candidato_id)" wire:navigate>Ver candidato</x-ui.button>
                @can('update', $seleccionada)
                    <x-ui.button variant="primary" wire:click="editar({{ $seleccionada->id }})">Editar</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.dialog>

    {{-- "+ Nueva evaluación" / "Editar" --}}
    <x-ui.dialog wire:model="mostrarFormulario" :title="$editando ? 'Editar evaluación · '.$enEdicion?->postulacion?->candidato?->nombre : 'Nueva evaluación'" max-width="480px">
        <form wire:submit="guardar" id="form-evaluacion" class="grid gap-3">
            @if (! $editando)
                <x-ui.field label="Candidato y vacante" for="evaluacion-postulacion" error="postulacionId">
                    <x-ui.select wire:model="postulacionId" id="evaluacion-postulacion" :options="$postulaciones" placeholder="Elige un candidato" class="w-full" error="postulacionId" />
                </x-ui.field>
            @endif

            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="mb-1 text-13 text-neutral-700">Criterios (de 0 a 10)</legend>
                @foreach ($criterios as $criterio)
                    <x-ui.field :label="$criterio->etiqueta()" :for="'criterio-'.$criterio->value" :error="'puntajes.'.$criterio->value">
                        <x-ui.input
                            type="number" min="0" max="10" step="0.1" inputmode="decimal"
                            wire:model.blur="puntajes.{{ $criterio->value }}"
                            :id="'criterio-'.$criterio->value"
                            :error="'puntajes.'.$criterio->value"
                        />
                    </x-ui.field>
                @endforeach
            </fieldset>
            @error('puntajes')
                <p class="field-error -mt-2" role="alert">{{ $message }}</p>
            @enderror
            <p class="m-0 text-14" aria-live="polite">Promedio: <strong>{{ $this->promedio ?? '—' }}/10</strong></p>

            <x-ui.field label="Comentario" for="evaluacion-comentario" error="comentario">
                <x-ui.textarea wire:model="comentario" id="evaluacion-comentario" rows="3" error="comentario" />
            </x-ui.field>
            <x-ui.field label="Estado" for="evaluacion-estado" error="estadoEvaluacion">
                <x-ui.select wire:model="estadoEvaluacion" id="evaluacion-estado" :options="$estados" class="w-full" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-evaluacion" variant="primary" wire:loading.attr="disabled" wire:target="guardar">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
