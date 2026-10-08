{{-- Pantalla "Mi empresa" del prototipo (TalentCloud App.dc.html, isMiEmpresa). --}}
<div>
    <x-ui.page-header title="Mi empresa" :subtitle="$empresa->nombre.' — plan '.$empresa->plan->etiqueta().'.'" />

    <div class="grid items-start gap-6 md:grid-cols-2">
        <section>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="m-0 text-16">Usuarios internos</h2>
                <div class="flex items-center gap-3">
                    <x-ui.action-message on="invitacion-enviada">Invitación enviada.</x-ui.action-message>
                    <x-ui.button wire:click="abrirInvitacion">+ Invitar usuario</x-ui.button>
                </div>
            </div>

            @error('usuario')
                <p class="field-error mb-3 text-14" role="alert">{{ $message }}</p>
            @enderror

            <x-ui.table>
                <x-slot:head>
                    <th>Nombre</th><th>Rol</th><th>Estado</th><th><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($usuarios as $usuario)
                    <tr wire:key="usuario-{{ $usuario->id }}">
                        <td>
                            {{ $usuario->name }}
                            @if ($usuario->id === auth()->id())
                                <span class="text-13 text-neutral-600">(tú)</span>
                            @endif
                        </td>
                        <td>{{ $usuario->rol()?->etiquetaEnEmpresa() }}</td>
                        <td>
                            @if ($usuario->estaActivo())
                                <x-ui.tag tone="accent">Activo</x-ui.tag>
                            @else
                                <x-ui.tag tone="outline">Desactivado</x-ui.tag>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('gestionar', $usuario)
                                <x-ui.button variant="ghost" size="sm" wire:click="confirmarCambioUsuario({{ $usuario->id }})">
                                    {{ $usuario->estaActivo() ? 'Desactivar' : 'Reactivar' }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                @foreach ($invitaciones as $invitacion)
                    <tr wire:key="invitacion-{{ $invitacion->id }}">
                        <td>
                            {{ $invitacion->nombre ?? $invitacion->email }}
                            <span class="block text-13 text-neutral-600">{{ $invitacion->email }}</span>
                        </td>
                        <td>{{ $invitacion->rol->etiquetaEnEmpresa() }}</td>
                        <td><x-ui.tag>Invitado</x-ui.tag></td>
                        <td class="text-right">
                            <x-ui.button variant="ghost" size="sm" wire:click="confirmarRevocacion({{ $invitacion->id }})">Revocar</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </section>

        <div class="grid gap-4">
            <x-ui.card kicker="Plan" :title="$empresa->plan->etiqueta()">
                <x-empresas.uso-del-plan :uso="$uso" />
                <p class="card-body text-13">Para cambiar de plan, escribe al equipo de TalentCloud.</p>
            </x-ui.card>

            <x-ui.card kicker="Datos de la empresa" :title="$empresa->nombre">
                <p class="card-body">
                    {{ $empresa->sector ?? 'Sector sin indicar' }}
                    @if ($sitioWeb = \App\Support\EnlaceExterno::seguro($empresa->sitio_web))
                        · <a href="{{ $sitioWeb }}" target="_blank" rel="noopener noreferrer">{{ \App\Support\EnlaceExterno::dominio($sitioWeb) }}</a>
                    @endif
                </p>
                @if ($empresa->descripcion)
                    <p class="card-body">{{ $empresa->descripcion }}</p>
                @endif
                @can('update', $empresa)
                    <x-ui.button variant="ghost" size="sm" class="self-start" wire:click="abrirDatos">Editar datos</x-ui.button>
                @endcan
            </x-ui.card>
        </div>
    </div>

    {{-- Invitar usuario --}}
    <x-ui.dialog wire:model="mostrarInvitacion" title="Invitar usuario" max-width="460px">
        <form wire:submit="invitar" id="form-invitar" class="grid gap-3">
            <p class="m-0">Se enviará una invitación por correo para unirse a {{ $empresa->nombre }}.</p>
            @if ($uso->usuariosAlLimite())
                {{-- Reenviar a un correo con invitación pendiente sí se permite: no se bloquea el formulario. --}}
                <p class="m-0 text-14 text-accent-2-700">Tu plan {{ $uso->plan->etiqueta() }} ya tiene sus {{ $uso->limiteUsuarios() }} lugares ocupados, contando las invitaciones pendientes. Para invitar a alguien nuevo, revoca una invitación, desactiva un usuario o solicita un cambio de plan.</p>
            @endif
            <x-ui.field label="Nombre" for="invitado-nombre" error="invitadoNombre">
                <x-ui.input wire:model="invitadoNombre" id="invitado-nombre" placeholder="Nombre del reclutador" error="invitadoNombre" />
            </x-ui.field>
            <x-ui.field label="Correo" for="invitado-email" error="invitadoEmail">
                <x-ui.input wire:model="invitadoEmail" id="invitado-email" type="email" placeholder="nombre@{{ \Illuminate\Support\Str::slug($empresa->nombre, '') }}.com" error="invitadoEmail" />
            </x-ui.field>
            <x-ui.field error="invitadoRol">
                <label id="invitado-rol">Rol</label>
                <x-ui.seg labelledby="invitado-rol">
                    @foreach ($roles as $valor => $etiqueta)
                        <x-ui.seg-option wire:model="invitadoRol" name="invitadoRol" value="{{ $valor }}">{{ $etiqueta }}</x-ui.seg-option>
                    @endforeach
                </x-ui.seg>
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-invitar" variant="primary" wire:loading.attr="disabled">Enviar invitación</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Desactivar / reactivar --}}
    {{-- El texto sigue a lo que se confirmó al abrir, no al estado que se relee en cada render. --}}
    @php($activo = $desactivarSeleccionado)
    <x-ui.confirm
        wire:model="mostrarConfirmacionUsuario"
        :title="($activo ? 'Desactivar a ' : 'Reactivar a ').($seleccionado?->name ?? '')"
        :action-label="$activo ? 'Desactivar' : 'Reactivar'"
        action="cambiarEstadoUsuario"
    >
        {{ $activo
            ? 'Perderá el acceso a TalentCloud de inmediato. Sus vacantes, entrevistas y evaluaciones se conservan.'
            : 'Recuperará el acceso a TalentCloud con su rol actual.' }}
    </x-ui.confirm>

    {{-- Revocar invitación --}}
    <x-ui.confirm
        wire:model="mostrarConfirmacionInvitacion"
        title="Revocar invitación"
        action-label="Revocar"
        action="revocarInvitacion"
    >
        El enlace enviado dejará de funcionar. Podrás volver a invitar a esta persona cuando quieras.
    </x-ui.confirm>

    {{-- Datos de la empresa --}}
    <x-ui.dialog wire:model="mostrarDatos" title="Datos de la empresa" max-width="460px">
        <form wire:submit="guardarDatos" id="form-datos-empresa" class="grid gap-3">
            <x-ui.field label="Nombre de la empresa" for="empresa-nombre" error="nombre">
                <x-ui.input wire:model="nombre" id="empresa-nombre" error="nombre" />
            </x-ui.field>
            <x-ui.field label="Sector" for="empresa-sector" error="sector">
                <x-ui.input wire:model="sector" id="empresa-sector" placeholder="Tecnología" error="sector" />
            </x-ui.field>
            <x-ui.field label="Sitio web" for="empresa-web" error="sitioWeb">
                <x-ui.input wire:model="sitioWeb" id="empresa-web" type="url" placeholder="https://empresa.com" error="sitioWeb" />
            </x-ui.field>
            <x-ui.field label="Descripción" for="empresa-descripcion" error="descripcion">
                <x-ui.textarea wire:model="descripcion" id="empresa-descripcion" error="descripcion" />
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-datos-empresa" variant="primary" wire:loading.attr="disabled">Guardar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
