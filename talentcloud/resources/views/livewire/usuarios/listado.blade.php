{{-- «Usuarios» del admin SaaS: todas las cuentas de la plataforma, para dar soporte. --}}
<div>
    <x-ui.se-actualiza />
    <x-ui.page-header title="Usuarios" subtitle="Cuentas de todas las empresas y postulantes de la plataforma." compact />

    @error('usuario')
        <p class="field-error mb-3 text-14" role="alert">{{ $message }}</p>
    @enderror

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.input type="search" class="max-w-[240px]" placeholder="Buscar nombre, correo o empresa…" aria-label="Buscar nombre, correo o empresa" wire:model.live.debounce.300ms="busqueda" />
        <label for="usuarios-rol" class="sr-only">Rol</label>
        <x-ui.select id="usuarios-rol" :options="$roles" placeholder="Todos los roles" wire:model.live="rol" />
        <label for="usuarios-situacion" class="sr-only">Estado de la cuenta</label>
        <x-ui.select id="usuarios-situacion" :options="['activos' => 'Activos', 'desactivados' => 'Desactivados']" placeholder="Cualquier estado" wire:model.live="situacion" />
        <div class="ml-auto flex items-center gap-2">
            <label for="usuarios-orden" class="text-13 text-neutral-600">Ordenar por</label>
            <x-ui.select id="usuarios-orden" :options="$this->opcionesOrden()" wire:model.live="orden" />
        </div>
    </div>

    @if ($filas->isEmpty())
        <x-ui.empty-state :message="$this->hayFiltros() ? 'No hay usuarios con esos filtros.' : 'Todavía no hay usuarios.'" />
    @else
        <x-ui.actualizable><x-ui.table>
            <x-slot:head>
                <th>Usuario</th><th>Rol</th><th>Empresa</th><th>Alta</th><th>Estado</th><th>Acciones</th>
            </x-slot:head>
            @foreach ($filas as $usuario)
                <tr wire:key="usuario-{{ $usuario->id }}">
                    <td>
                        {{ $usuario->name }}
                        <span class="block text-13 text-neutral-700">{{ $usuario->email }}</span>
                    </td>
                    <td>{{ $usuario->rol()?->etiqueta() ?? '—' }}</td>
                    <td>{{ $usuario->empresa?->nombre ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Formato::fechaCorta($usuario->created_at) }}</td>
                    <td>
                        @if ($usuario->estaActivo())
                            <x-ui.tag tone="accent">Activo</x-ui.tag>
                        @else
                            <x-ui.tag tone="outline">Desactivado</x-ui.tag>
                        @endif
                    </td>
                    <td>
                        @can('gestionarDesdeLaPlataforma', $usuario)
                            <x-ui.button variant="ghost" size="sm" wire:click="confirmar({{ $usuario->id }})">
                                {{ $usuario->estaActivo() ? 'Desactivar' : 'Reactivar' }}
                            </x-ui.button>
                        @else
                            <span class="text-neutral-600">—</span>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}</x-ui.actualizable>
    @endif

    {{-- El texto sigue a lo que se confirmó al abrir, no al estado que se relee en cada render. --}}
    @php($desactivar = $desactivarSeleccionado)
    <x-ui.confirm
        wire:model="mostrarConfirmacion"
        :title="($desactivar ? 'Desactivar a ' : 'Reactivar a ').($seleccionado?->name ?? '')"
        :action-label="$desactivar ? 'Desactivar' : 'Reactivar'"
        action="cambiarEstado"
    >
        {{ $desactivar
            ? 'La persona pierde el acceso en su siguiente petición y conserva su historial. No se puede desactivar a quien tiene entrevistas por realizar.'
            : 'La persona recupera el acceso, si el plan de su empresa tiene lugar para otro usuario.' }}
    </x-ui.confirm>
</div>
