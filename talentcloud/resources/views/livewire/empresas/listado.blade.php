{{-- Pantalla "Empresas" del prototipo (TalentCloud App.dc.html, isEmpresas). --}}
<div>
    <x-ui.page-header title="Empresas" subtitle="Empresas registradas en TalentCloud AI." compact>
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="abrirFormulario">+ Nueva empresa</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.listado-controles
        placeholder="Buscar empresa…"
        :estados="$this->opcionesEstado()"
        :estado-actual="$estado"
        :ordenes="$this->opcionesOrden()"
        :direccion="$direccion"
    />

    @if ($filas->isEmpty())
        <x-ui.empty-state message="No se encontraron empresas." />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Empresa</th><th>Plan</th><th>Usuarios</th><th>Vacantes activas</th><th>Estado</th><th>Acciones</th>
            </x-slot:head>
            @foreach ($filas as $empresa)
                <tr wire:key="empresa-{{ $empresa->id }}">
                    <td class="tc-row" wire:click="verDetalle({{ $empresa->id }})">{{ $empresa->nombre }}</td>
                    <td>{{ $empresa->plan->etiqueta() }}</td>
                    <td>{{ $empresa->usuarios_count }}</td>
                    <td>{{ $empresa->vacantes_activas_count }}</td>
                    <td><x-ui.estado :valor="$empresa->estado" /></td>
                    <td>
                        <x-ui.button variant="ghost" size="sm" wire:click="confirmarCambioEstado({{ $empresa->id }})">
                            {{ $empresa->estaActiva() ? 'Suspender' : 'Activar' }}
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}
    @endif

    {{-- Detalle (diálogo informativo del prototipo) --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->nombre" max-width="480px">
        @if ($seleccionada)
            <p class="m-0 whitespace-pre-line">Plan: {{ $seleccionada->plan->etiqueta() }}
Usuarios: {{ $seleccionada->usuarios_count }}
Vacantes activas: {{ $seleccionada->vacantes_activas_count }}
Estado: {{ $seleccionada->estado->etiqueta() }}</p>
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Confirmación de Suspender / Activar con los textos del prototipo --}}
    @php($activa = $seleccionada?->estaActiva() ?? true)
    <x-ui.confirm
        wire:model="mostrarConfirmacion"
        :title="($activa ? 'Suspender ' : 'Activar ').($seleccionada?->nombre ?? '')"
        :action-label="$activa ? 'Suspender' : 'Activar'"
        action="cambiarEstado"
    >
        {{ $activa
            ? 'La empresa y sus usuarios perderán acceso a la plataforma de inmediato.'
            : 'La empresa recuperará acceso a la plataforma.' }}
    </x-ui.confirm>

    {{-- Formulario "Nueva empresa" --}}
    <x-ui.dialog wire:model="mostrarFormulario" title="Nueva empresa" max-width="460px">
        <form wire:submit="crear" id="form-nueva-empresa" class="grid gap-3">
            <x-ui.field label="Nombre de la empresa" for="nueva-empresa-nombre" error="nombre">
                <x-ui.input wire:model="nombre" id="nueva-empresa-nombre" placeholder="Acme S.A." error="nombre" />
            </x-ui.field>
            <x-ui.field label="Plan" for="nueva-empresa-plan" error="plan">
                <x-ui.select wire:model="plan" id="nueva-empresa-plan" :options="$planes" class="w-full" />
            </x-ui.field>
            <x-ui.field label="Correo del administrador" for="nueva-empresa-email" error="emailAdministrador" hint="Le enviaremos una invitación para crear su cuenta.">
                <x-ui.input wire:model="emailAdministrador" id="nueva-empresa-email" type="email" placeholder="admin@empresa.com" error="emailAdministrador" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-nueva-empresa" variant="primary" wire:loading.attr="disabled">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
