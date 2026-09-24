{{-- Pantalla "Vacantes" del prototipo (TalentCloud App.dc.html, isVacantes). --}}
@use('App\Support\Formato')
<div>
    <x-ui.page-header title="Vacantes" :subtitle="$subtitulo" compact>
        <x-slot:actions>
            <x-ui.button wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar">Exportar a Excel</x-ui.button>
            @can('create', App\Models\Vacante::class)
                <x-ui.button variant="primary" wire:click="abrirFormulario">+ Nueva vacante</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.listado-controles
        placeholder="Buscar vacante o empresa…"
        :estados="$this->opcionesEstado()"
        :estado-actual="$estado"
        :ordenes="$this->opcionesOrden()"
        :direccion="$direccion"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state icon="stack" message="No se encontraron vacantes con estos filtros." />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Vacante</th><th>Empresa</th><th>Ubicación</th><th>Candidatos</th><th>Estado</th><th>Creada</th>
            </x-slot:head>
            @foreach ($filas as $vacante)
                <tr class="tc-row" wire:key="vacante-{{ $vacante->id }}" wire:click="verVistaPrevia({{ $vacante->id }})">
                    <td>{{ $vacante->titulo }}</td>
                    <td>{{ $vacante->empresa?->nombre }}</td>
                    <td>{{ $vacante->ubicacion ?? $vacante->modalidad->etiqueta() }}</td>
                    <td>{{ $vacante->postulaciones_count }}</td>
                    <td><x-ui.estado :valor="$vacante->estado" /></td>
                    <td class="text-neutral-600">{{ Formato::fechaCorta($vacante->created_at) }}</td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}
    @endif

    {{-- Vista previa (diálogo del prototipo) --}}
    <x-vacantes.vista-previa wire:model="mostrarVistaPrevia" :vacante="$seleccionada" />

    {{-- "Nueva vacante" (campos del prototipo + ubicación y modalidad) --}}
    <x-ui.dialog wire:model="mostrarFormulario" title="Nueva vacante" max-width="460px">
        <form wire:submit="crear" id="form-nueva-vacante" class="grid gap-3">
            <x-ui.field label="Título del puesto" for="vacante-titulo" error="titulo">
                <x-ui.input wire:model="titulo" id="vacante-titulo" placeholder="Desarrollador Backend" error="titulo" />
            </x-ui.field>
            @if ($esAdmin)
                <x-ui.field label="Empresa" for="vacante-empresa" error="empresaId">
                    <x-ui.select wire:model="empresaId" id="vacante-empresa" :options="$empresas" placeholder="Elige una empresa" class="w-full" error="empresaId" />
                </x-ui.field>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Ubicación" for="vacante-ubicacion" error="ubicacion">
                    <x-ui.input wire:model="ubicacion" id="vacante-ubicacion" placeholder="Remoto" error="ubicacion" />
                </x-ui.field>
                <x-ui.field label="Modalidad" for="vacante-modalidad" error="modalidad">
                    <x-ui.select wire:model="modalidad" id="vacante-modalidad" :options="$modalidades" class="w-full" />
                </x-ui.field>
            </div>
            <x-ui.field label="Competencias requeridas" for="vacante-competencias" error="competencias" hint="Separadas por comas. Podrás ajustar tipo y peso de cada una antes de publicar.">
                <x-ui.input wire:model="competencias" id="vacante-competencias" placeholder="Java, Spring Boot, PostgreSQL" error="competencias" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-nueva-vacante" variant="primary" wire:loading.attr="disabled">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
