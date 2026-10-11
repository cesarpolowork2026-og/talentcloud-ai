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
                    <td class="tc-row" wire:click="verDetalle({{ $empresa->id }})"><x-ui.boton-fila wire:click.stop="verDetalle({{ $empresa->id }})">{{ $empresa->nombre }}</x-ui.boton-fila></td>
                    <td>
                        {{ $empresa->etiquetaDelPlan() }}
                        @if ($empresa->plan_solicitado)
                            <x-ui.tag tone="accent-2" class="ml-1">Pide {{ $empresa->plan_solicitado->etiqueta() }}</x-ui.tag>
                        @endif
                        @if ($empresa->tieneSuscripcionVigente())
                            <x-ui.tag :tone="$empresa->suscripcion_estado->tono()" class="ml-1">Tarjeta · {{ $empresa->suscripcion_estado->etiqueta() }}</x-ui.tag>
                        @endif
                    </td>
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
    <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->nombre" max-width="520px">
        @if ($seleccionada)
            <p class="m-0 whitespace-pre-line">Plan: {{ $seleccionada->etiquetaDelPlan() }}{{ $seleccionada->enPrueba() ? ' hasta el '.\App\Support\Formato::fechaCorta($seleccionada->prueba_hasta).'; después, Gratis' : '' }}
@if ($seleccionada->plan_solicitado)Solicitud: plan {{ $seleccionada->plan_solicitado->etiqueta() }}, el {{ \App\Support\Formato::fechaCorta($seleccionada->plan_solicitado_at) }}
@endif
@if ($seleccionada->tieneSuscripcionVigente())Pago con tarjeta: {{ $seleccionada->suscripcion_periodicidad?->importe($seleccionada->plan) }} más IGV, {{ mb_strtolower($seleccionada->suscripcion_estado->etiqueta()) }}{{ $seleccionada->suscripcion_renueva_at ? ($seleccionada->suscripcion_cancela_al_final ? '; cancelada, termina el ' : '; se renueva el ').\App\Support\Formato::fechaCorta($seleccionada->suscripcion_renueva_at) : '' }}
@endif
Usuarios: {{ $seleccionada->usuarios_count }}
Vacantes activas: {{ $seleccionada->vacantes_activas_count }}
Estado: {{ $seleccionada->estado->etiqueta() }}</p>

            @if ($usoDeLaSeleccionada)
                <section class="mt-4 grid gap-2" aria-labelledby="detalle-uso">
                    <h2 id="detalle-uso" class="m-0 text-15">Uso del plan</h2>
                    <x-empresas.uso-del-plan :uso="$usoDeLaSeleccionada" />
                </section>
            @endif

            <section class="mt-4 grid gap-2" aria-labelledby="detalle-usuarios">
                <h2 id="detalle-usuarios" class="m-0 text-15">Usuarios de la empresa</h2>
                @if ($usuariosDeLaSeleccionada->isEmpty())
                    <p class="m-0 text-14 text-neutral-700">Todavía no tiene usuarios: la invitación de su administrador sigue pendiente.</p>
                @else
                    <ul class="m-0 grid list-none gap-1 p-0 text-14">
                        @foreach ($usuariosDeLaSeleccionada as $usuario)
                            <li class="flex flex-wrap items-baseline justify-between gap-2 border-b border-divider pb-1 last:border-0" wire:key="detalle-usuario-{{ $usuario->id }}">
                                <span>{{ $usuario->name }} <span class="text-13 text-neutral-700">· {{ $usuario->rol()?->etiquetaEnEmpresa() }}</span></span>
                                <span class="text-13 {{ $usuario->estaActivo() ? 'text-neutral-700' : 'text-accent-2-700' }}">{{ $usuario->estaActivo() ? 'Activo' : 'Desactivado' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            @if ($seleccionada)
                {{-- Un plan pagado con tarjeta lo cambia la empresa en su suscripción (CambiarPlanEmpresa lo impide). --}}
                @can('cambiarPlan', $seleccionada)
                    @unless ($seleccionada->tieneSuscripcionVigente())
                        <x-ui.button variant="primary" wire:click="abrirCambioDePlan({{ $seleccionada->id }})">Cambiar plan</x-ui.button>
                    @endunless
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Cambiar plan --}}
    <x-ui.dialog wire:model="mostrarCambioDePlan" :title="'Cambiar plan de '.$seleccionada?->nombre" max-width="460px">
        <form wire:submit="cambiarPlan" id="form-cambiar-plan" class="grid gap-3">
            <x-ui.field label="Plan" for="cambiar-plan" error="planNuevo" :hint="$planElegido?->descripcionDeLimites()">
                <x-ui.select wire:model.live="planNuevo" id="cambiar-plan" :options="$planes" class="w-full" error="planNuevo" />
            </x-ui.field>
            @if ($seleccionada?->plan_solicitado)
                <p class="m-0 text-14">La empresa solicitó el plan {{ $seleccionada->plan_solicitado->etiqueta() }} el {{ \App\Support\Formato::fechaCorta($seleccionada->plan_solicitado_at) }}. Aplícalo cuando el pago esté coordinado.</p>
            @endif
            <p class="m-0 text-13 text-neutral-700">Con un plan menor, la empresa conserva sus vacantes y usuarios aunque superen el nuevo límite; solo no podrá sumar más. Cambiar el plan termina la prueba del plan Pro y se avisará a la cuenta de la empresa.</p>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-cambiar-plan" variant="primary" wire:loading.attr="disabled" wire:target="cambiarPlan">Cambiar plan</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    {{-- Confirmación de Suspender / Activar con los textos del prototipo --}}
    {{-- El texto sigue a lo que se confirmó al abrir, no al estado que se relee en cada render. --}}
    @php($activa = $suspenderSeleccionada)
    <x-ui.confirm
        wire:model="mostrarConfirmacion"
        :title="($activa ? 'Suspender ' : 'Activar ').($seleccionada?->nombre ?? '')"
        :action-label="$activa ? 'Suspender' : 'Activar'"
        action="cambiarEstado"
    >
        {{ $activa
            ? 'La empresa y sus usuarios perderán acceso a la plataforma de inmediato. Sus vacantes dejan de verse y sus procesos quedan en pausa; los candidatos no reciben aviso.'
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
