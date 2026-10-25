{{-- «Competencias»: el catálogo compartido por todas las empresas, que administra el admin SaaS. --}}
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header title="Competencias" :subtitle="'Catálogo compartido por todas las empresas ('.$total.' competencias).'" compact>
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="abrirFormulario">+ Nueva competencia</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <p class="-mt-2 mb-4 max-w-[80ch] text-13 text-neutral-700">
        Las empresas eligen estas competencias al definir los requisitos de una vacante. Un sinónimo («PowerBI» para Power BI) hace que se reconozca
        la competencia aunque se escriba distinto. Si dos entradas son lo mismo, fusiónalas: los requisitos y las anotaciones pasan a la que conservas.
    </p>

    @error('competencia')
        <p class="field-error mb-3 text-14" role="alert">{{ $message }}</p>
    @enderror

    <x-ui.listado-controles
        placeholder="Buscar competencia…"
        :ordenes="$this->opcionesOrden()"
        :direccion="$direccion"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state :message="$this->hayFiltros() ? 'No hay competencias con esa búsqueda.' : 'El catálogo todavía no tiene competencias.'" />
    @else
        <x-ui.actualizable><x-ui.table>
            <x-slot:head>
                <th>Competencia</th><th>Categoría</th><th>Sinónimos</th><th>En uso</th><th>Acciones</th>
            </x-slot:head>
            @foreach ($filas as $competencia)
                <tr wire:key="competencia-{{ $competencia->id }}">
                    <td class="tc-row" wire:click="abrirFormulario({{ $competencia->id }})"><x-ui.boton-fila wire:click.stop="abrirFormulario({{ $competencia->id }})">{{ $competencia->nombre }}</x-ui.boton-fila></td>
                    <td>{{ $competencia->categoria ?? '—' }}</td>
                    <td>
                        @forelse ($competencia->sinonimos as $sinonimo)
                            <x-ui.tag tone="outline" class="mb-1 mr-1">{{ $sinonimo }}</x-ui.tag>
                        @empty
                            <span class="text-neutral-600">—</span>
                        @endforelse
                    </td>
                    <td class="whitespace-nowrap text-13">
                        {{ $competencia->requisitos_count }} {{ (int) $competencia->requisitos_count === 1 ? 'requisito' : 'requisitos' }}
                        <span class="block text-neutral-700">{{ $competencia->candidatos_count }} {{ (int) $competencia->candidatos_count === 1 ? 'anotación' : 'anotaciones' }}</span>
                    </td>
                    <td class="whitespace-nowrap">
                        <x-ui.button variant="ghost" size="sm" wire:click="abrirFusion({{ $competencia->id }})">Fusionar</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="confirmarEliminar({{ $competencia->id }})">Eliminar</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}</x-ui.actualizable>
    @endif

    {{-- Crear o editar --}}
    <x-ui.dialog wire:model="mostrarFormulario" :title="$competenciaId ? 'Editar competencia' : 'Nueva competencia'" max-width="480px">
        <form wire:submit="guardar" id="form-competencia" class="grid gap-3">
            <x-ui.field label="Nombre" for="competencia-nombre" error="nombre">
                <x-ui.input wire:model="nombre" id="competencia-nombre" placeholder="Power BI" error="nombre" />
            </x-ui.field>
            <x-ui.field label="Categoría (opcional)" for="competencia-categoria" error="categoria">
                <x-ui.input wire:model="categoria" id="competencia-categoria" placeholder="Datos" error="categoria" />
            </x-ui.field>
            <x-ui.field label="Sinónimos (opcional)" for="competencia-sinonimos" error="sinonimos" hint="Sepáralos con comas. Por ejemplo: PowerBI, Microsoft Power BI.">
                <x-ui.input wire:model="sinonimos" id="competencia-sinonimos" error="sinonimos" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-competencia" variant="primary" wire:loading.attr="disabled" wire:target="guardar">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Fusionar duplicados --}}
    <x-ui.dialog wire:model="mostrarFusion" :title="'Fusionar '.($seleccionada?->nombre ?? '')" ancho="540px">
        <form wire:submit="fusionar" id="form-fusion" class="grid gap-3">
            <p class="m-0 text-14">«{{ $seleccionada?->nombre }}» se eliminará y su nombre quedará como sinónimo de la competencia que conserves.</p>
            @if ($usosDeLaSeleccionada)
                <p class="m-0 text-14">
                    Pasarán a la que elijas:
                    <strong>{{ $usosDeLaSeleccionada['requisitos'] }} {{ $usosDeLaSeleccionada['requisitos'] === 1 ? 'requisito' : 'requisitos' }}</strong> de vacantes y
                    <strong>{{ $usosDeLaSeleccionada['candidatos'] }} {{ $usosDeLaSeleccionada['candidatos'] === 1 ? 'anotación' : 'anotaciones' }}</strong> de candidatos.
                    Esta acción no se puede deshacer.
                </p>
            @endif
            <x-ui.field label="Competencia que se conserva" for="fusion-destino" error="destinoId" hint="Escribe para buscar por nombre o sinónimo. Las parecidas aparecen primero.">
                <x-ui.buscador id="fusion-destino" metodo="buscarDestinos" modelo="destinoId" placeholder="Busca la competencia que se conserva…" error="destinoId" :en-linea="true" wire:key="buscador-fusion-{{ $seleccionada?->id }}" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-fusion" variant="primary" wire:loading.attr="disabled" wire:target="fusionar">Fusionar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Eliminar --}}
    <x-ui.confirm wire:model="mostrarEliminar" :title="'Eliminar '.($seleccionada?->nombre ?? '')" action-label="Eliminar" action="eliminar">
        Se eliminará del catálogo. Solo se pueden eliminar las competencias que nadie usa; si está en uso, se fusiona con otra.
    </x-ui.confirm>
</div>
