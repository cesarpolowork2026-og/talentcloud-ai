{{-- "Reclamaciones" del admin SaaS (ampliación sobre el prototipo): hojas del Libro de Reclamaciones. --}}
@use('App\Support\Formato')
<div>
    <x-ui.page-header title="Reclamaciones" subtitle="Hojas del Libro de Reclamaciones. Cada una debe responderse dentro de su fecha límite; la respuesta llega al consumidor por correo." compact />

    <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <x-ui.stat label="Pendientes" :value="$pendientes" highlight />
        <x-ui.stat label="Vencidas" :value="$vencidas" />
    </div>

    <x-ui.listado-controles
        placeholder="Buscar por código (LR-…), nombre, correo o documento…"
        :estados="$this->opcionesEstado()"
        :estado-actual="$estado"
        :ordenes="$this->opcionesOrden()"
        :direccion="$direccion"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="book-open-text" :message="$this->hayFiltros() ? 'No hay hojas con esos filtros.' : 'Aún no se registró ninguna hoja del Libro de Reclamaciones.'" />
    @else
        <x-ui.actualizable><x-ui.table>
            <x-slot:head>
                <th>Hoja</th><th>Tipo</th><th>Consumidor</th><th>Presentada</th><th>Fecha límite</th><th>Estado</th>
            </x-slot:head>
            @foreach ($filas as $fila)
                <tr wire:key="reclamacion-{{ $fila->id }}">
                    <td class="tc-row" wire:click="verDetalle({{ $fila->id }})"><x-ui.boton-fila wire:click.stop="verDetalle({{ $fila->id }})">{{ $fila->codigo() }}</x-ui.boton-fila></td>
                    <td>{{ $fila->tipo->etiqueta() }}</td>
                    <td>{{ $fila->nombre }}<span class="block text-13 text-neutral-700">{{ $fila->servicio }}</span></td>
                    <td class="whitespace-nowrap">{{ Formato::fechaCorta($fila->created_at) }}</td>
                    <td class="whitespace-nowrap">
                        {{ Formato::fechaCorta($fila->fecha_limite) }}
                        @if ($fila->vencida())
                            <span class="block text-13 font-semibold text-accent-2-700">Vencida</span>
                        @elseif ($fila->estado === App\Enums\EstadoReclamacion::Pendiente)
                            @php($quedan = $plazo->diasHabilesHasta($fila->fecha_limite))
                            <span class="block text-13 text-neutral-700">{{ $quedan === 0 ? 'Vence hoy' : ($quedan === 1 ? 'Queda 1 día hábil' : 'Quedan '.$quedan.' días hábiles') }}</span>
                        @endif
                    </td>
                    <td><x-ui.estado :valor="$fila->estado" /></td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}</x-ui.actualizable>
    @endif

    {{-- Hoja completa y respuesta --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="$detalle ? 'Hoja '.$detalle->codigo() : 'Hoja'" ancho="680px" max-width="680px">
        @if ($detalle)
            <dl class="m-0 grid gap-x-4 gap-y-2 text-14 sm:grid-cols-[minmax(0,180px)_minmax(0,1fr)]">
                @foreach ($detalle->hoja() as $etiqueta => $valor)
                    <dt class="font-semibold text-neutral-800">{{ $etiqueta }}</dt>
                    <dd class="m-0 whitespace-pre-line break-words">{{ $valor }}</dd>
                @endforeach
            </dl>

            <section class="mt-5 grid gap-2 border-t border-divider pt-4" aria-labelledby="respuesta-titulo">
                <h2 id="respuesta-titulo" class="m-0 text-16">Respuesta</h2>
                @if ($detalle->respuesta !== null)
                    <p class="m-0 text-13 text-neutral-700">Enviada al consumidor el {{ Formato::fechaHora($detalle->respondida_at) }}{{ $detalle->respondidaPor ? ' por '.$detalle->respondidaPor->name : '' }}.</p>
                    <p class="m-0 whitespace-pre-line break-words text-14">{{ $detalle->respuesta }}</p>
                @else
                    <form wire:submit="responder" id="form-respuesta" class="grid gap-2">
                        <x-ui.field label="Respuesta al consumidor" for="respuesta" error="respuesta" hint="Se envía a su correo y queda guardada. Una hoja se responde una sola vez.">
                            <x-ui.textarea wire:model="respuesta" id="respuesta" rows="6" maxlength="5000" error="respuesta" />
                        </x-ui.field>
                    </form>
                @endif
            </section>
        @endif
        <x-slot:actions>
            <x-ui.action-message on="reclamacion-respondida">Respuesta enviada.</x-ui.action-message>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($detalle && $detalle->respuesta === null)
                @can('responder', $detalle)
                    <x-ui.button type="submit" form="form-respuesta" variant="primary" wire:loading.attr="disabled" wire:target="responder">Enviar respuesta</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.dialog>
</div>
