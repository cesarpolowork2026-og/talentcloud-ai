{{-- "Dashboard" del prototipo (TalentCloud App.dc.html, isDashboard). Para el admin SaaS, "Panel de la plataforma". --}}
@use('App\Enums\PlanEmpresa')
<div>
    <x-ui.page-header :title="$titulo" :subtitle="$subtitulo" />

    @if ($plataforma)
        {{-- 1 · Empresas cliente (solo admin SaaS) --}}
        <section class="mb-10" aria-labelledby="panel-empresas">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="panel-empresas" class="m-0 text-16">1 · Empresas cliente</h2>
                    <p class="m-0 mt-1 text-13 text-neutral-700">Quién usa TalentCloud, con qué plan contratado (se cuentan las empresas activas; las que están en prueba, como Gratis) y qué empresas están por quedarse sin lugar en el suyo.</p>
                </div>
                <x-ui.button size="sm" :href="route('empresas.index')" wire:navigate>Gestionar empresas</x-ui.button>
            </div>

            <div class="mb-4 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                <x-ui.stat label="Empresas activas" :value="$plataforma->empresasActivas" highlight />
                <x-ui.stat label="Suspendidas" :value="$plataforma->empresasSuspendidas" />
                @foreach (PlanEmpresa::cases() as $plan)
                    <x-ui.stat :label="'Plan '.$plan->etiqueta()" :value="$plataforma->empresasConPlan($plan)" wire:key="plan-{{ $plan->value }}" />
                @endforeach
                <x-ui.stat :label="'En prueba '.PlanEmpresa::PLAN_DE_PRUEBA->etiqueta()" :value="$plataforma->enPrueba" />
            </div>

            @if ($plataforma->solicitudesDePlan->isNotEmpty())
                <x-ui.card class="mb-4">
                    <div class="card-kicker">Solicitudes de cambio de plan</div>
                    <p class="m-0 text-13 text-neutral-700">Coordina el pago con la empresa y aplica el plan con «Cambiar plan» en Empresas; eso atiende la solicitud.</p>
                    <x-ui.table>
                        <x-slot:head>
                            <th>Empresa</th><th>Plan actual</th><th>Solicita</th><th>Fecha</th>
                        </x-slot:head>
                        @foreach ($plataforma->solicitudesDePlan as $empresa)
                            <tr wire:key="solicitud-{{ $empresa->id }}">
                                <td>{{ $empresa->nombre }}</td>
                                <td>{{ $empresa->etiquetaDelPlan() }}</td>
                                <td>{{ $empresa->plan_solicitado?->etiqueta() }}</td>
                                <td>{{ \App\Support\Formato::fechaCorta($empresa->plan_solicitado_at) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </x-ui.card>
            @endif

            @if ($plataforma->reclamaciones)
                @php($libro = $plataforma->reclamaciones)
                <x-ui.card class="mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="card-kicker">Libro de Reclamaciones</div>
                        <x-ui.button size="sm" :href="route('reclamaciones.index')" wire:navigate>Atender reclamaciones</x-ui.button>
                    </div>
                    <p class="m-0 text-14 text-neutral-800">
                        @if ($libro->pendientes === 0)
                            No hay hojas pendientes de respuesta.
                        @else
                            {{ $libro->pendientes === 1 ? '1 hoja pendiente' : $libro->pendientes.' hojas pendientes' }}:
                            {{ $libro->vencidas }} {{ $libro->vencidas === 1 ? 'vencida' : 'vencidas' }} y
                            {{ $libro->porVencer }} por vencer en los próximos {{ App\Services\Plataforma\PanelDePlataforma::DIAS_POR_VENCER }} días hábiles.
                        @endif
                    </p>
                </x-ui.card>
            @endif

            <x-ui.card>
                <div class="card-kicker">Cerca del límite de su plan</div>
                @if ($plataforma->cercaDelLimite->isEmpty())
                    <p class="m-0 text-14 text-neutral-800">Ninguna empresa activa usa el 80 % o más de su plan.</p>
                @else
                    <p class="m-0 text-13 text-neutral-700">Usan el 80 % o más de sus vacantes activas, de sus usuarios (con invitaciones pendientes) o del cupo de IA del mes. Buen momento para ofrecerles otro plan.</p>
                    <x-ui.table>
                        <x-slot:head>
                            <th>Empresa</th><th>Plan</th><th>Vacantes activas</th><th>Usuarios e invitaciones</th><th>IA este mes</th>
                        </x-slot:head>
                        @foreach ($plataforma->cercaDelLimite as $fila)
                            @php($uso = $fila['uso'])
                            <tr wire:key="limite-{{ $fila['empresa']->id }}">
                                <td>{{ $fila['empresa']->nombre }}</td>
                                <td>{{ $uso->etiquetaDelPlan() }}</td>
                                <td>{{ $uso->vacantesActivas }} {{ $uso->limiteVacantes() === null ? '· sin límite' : 'de '.$uso->limiteVacantes() }}</td>
                                <td>{{ $uso->usuariosActivos + $uso->invitacionesPendientes }} {{ $uso->limiteUsuarios() === null ? '· sin límite' : 'de '.$uso->limiteUsuarios() }}</td>
                                <td>{{ $uso->postulacionesConIa }} {{ $uso->cupoIa() === null ? '· sin límite' : 'de '.$uso->cupoIa() }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </section>

        {{-- 2 · Salud de la IA (solo admin SaaS) --}}
        <section class="mb-10" aria-labelledby="panel-ia">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="panel-ia" class="m-0 text-16">2 · Salud de la IA</h2>
                    <p class="m-0 mt-1 text-13 text-neutral-700">Cómo está funcionando el análisis de CV y el matching en los últimos {{ App\Services\Plataforma\CalidadDeLaIa::DIAS }} días.</p>
                </div>
                <x-ui.button size="sm" :href="route('calidad-ia')" wire:navigate>Ver calidad de la IA</x-ui.button>
            </div>

            @php($ia = $plataforma->ia)
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                <x-ui.stat label="Analizadas con IA" :value="$ia->porcentajeConIa() === null ? '—' : $ia->porcentajeConIa().'%'" highlight />
                <x-ui.stat label="Análisis básico pendiente" :value="$ia->basicasPendientes" />
                <x-ui.stat label="Marcadas como incorrectas" :value="$ia->marcasDelPeriodo" />
                <x-ui.stat label="CV con error de lectura" :value="$ia->cvsConError" />
                <x-ui.stat label="Trabajos fallidos" :value="$ia->trabajosFallidos" />
            </div>
        </section>

        {{-- 3 · Actividad de selección en todas las empresas --}}
        <div class="mb-3">
            <h2 class="m-0 text-16">3 · Actividad de selección</h2>
            <p class="m-0 mt-1 text-13 text-neutral-700">Lo que pasa en los procesos de todas las empresas. Para entrar en ellos, usa «Procesos de selección» en el menú.</p>
        </div>
    @endif

    <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <x-ui.stat label="Vacantes activas" :value="$resumen->vacantesActivas" />
        <x-ui.stat label="Candidatos evaluados" :value="$resumen->candidatosEvaluados" />
        <x-ui.stat
            label="Match promedio"
            :value="$resumen->matchPromedio === null ? '—' : $resumen->matchPromedio.'%'"
            highlight
            wire:click="$set('mostrarDetalleDelMatch', true)"
        />
        <x-ui.stat label="Entrevistas hoy" :value="$resumen->entrevistasHoy" />
    </div>

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section>
            <h2 class="mb-3 text-16">Vacantes recientes</h2>
            @if ($resumen->vacantesRecientes->isEmpty())
                <x-ui.empty-state icon="briefcase" message="Todavía no hay vacantes." />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Vacante</th><th>Empresa</th><th>Candidatos</th><th>Estado</th>
                    </x-slot:head>
                    @foreach ($resumen->vacantesRecientes as $vacante)
                        <tr class="tc-row" wire:key="reciente-{{ $vacante->id }}" wire:click="verVistaPrevia({{ $vacante->id }})">
                            <td><x-ui.boton-fila wire:click.stop="verVistaPrevia({{ $vacante->id }})">{{ $vacante->titulo }}</x-ui.boton-fila></td>
                            <td>{{ $vacante->empresa?->nombre }}</td>
                            <td>{{ $vacante->postulaciones_count }}</td>
                            <td><x-ui.estado :valor="$vacante->estado" /></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-16">Actividad reciente</h2>
            @forelse ($resumen->actividad as $actividad)
                <div class="mb-3 flex gap-2 text-14" wire:key="actividad-{{ $actividad->id }}">
                    <x-ui.icon name="star-four" :size="15" class="mt-[3px] text-accent" />
                    <span class="text-neutral-800">{{ $actividad->descripcion }}</span>
                </div>
            @empty
                <p class="m-0 text-14 text-neutral-600">Aún no hay actividad.</p>
            @endforelse
        </section>
    </div>

    {{-- Embudo de selección (ampliación: no está en el prototipo) --}}
    <section class="mt-8" aria-labelledby="embudo-titulo">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="embudo-titulo" class="m-0 text-16">Embudo de selección</h2>
                <p class="m-0 mt-1 text-13 text-neutral-700">Postulaciones de los últimos {{ $embudo->dias }} días y la etapa más avanzada a la que llegaron.</p>
            </div>
            <div>
                <label for="embudo-vacante" class="sr-only">Filtrar el embudo por vacante</label>
                <x-ui.select wire:model.live="vacanteDelEmbudo" id="embudo-vacante" :options="$vacantesDelEmbudo" placeholder="Todas las vacantes" class="max-w-full" />
            </div>
        </div>

        @if ($embudo->total === 0)
            <x-ui.empty-state icon="funnel" :message="'No hay postulaciones en los últimos '.$embudo->dias.' días.'" />
        @else
            <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-ui.card>
                    <ol class="m-0 grid list-none gap-3 p-0" aria-label="Postulaciones que alcanzaron cada etapa">
                        @foreach ($embudo->etapas as $fila)
                            <li class="grid gap-1" wire:key="embudo-{{ $fila['etapa']->value }}">
                                <div class="flex items-baseline justify-between gap-2 text-14">
                                    <span class="text-neutral-800">{{ $fila['etapa']->etiqueta() }}</span>
                                    <span class="font-semibold">{{ $fila['total'] }} <span class="font-normal text-neutral-700">· {{ $fila['porcentaje'] }}%</span></span>
                                </div>
                                <x-ui.progress :value="$fila['porcentaje']" :label="$fila['etapa']->etiqueta().': '.$fila['total'].' de '.$embudo->total.' postulaciones'" />
                            </li>
                        @endforeach
                    </ol>
                    <p class="m-0 text-13 text-neutral-700">
                        {{ $embudo->descartadas }} {{ $embudo->descartadas === 1 ? 'descartada' : 'descartadas' }} por la empresa y {{ $embudo->retiradas }} {{ $embudo->retiradas === 1 ? 'retirada' : 'retiradas' }} por el candidato: cuentan en las etapas a las que llegaron antes.
                    </p>
                </x-ui.card>

                <div class="grid gap-4">
                    <x-ui.card kicker="Tiempo medio hasta contratar">
                        @if ($embudo->diasHastaContratar === null)
                            <p class="m-0 font-heading text-24 font-semibold">Sin contrataciones aún</p>
                        @else
                            <p class="m-0 font-heading text-32 font-semibold">{{ App\Support\Formato::dias($embudo->diasHastaContratar) }}</p>
                            <p class="m-0 text-13 text-neutral-700">Desde la postulación, en {{ $embudo->contrataciones }} {{ $embudo->contrataciones === 1 ? 'contratación' : 'contrataciones' }} del periodo.</p>
                        @endif
                    </x-ui.card>
                    <x-ui.card kicker="Origen de las postulaciones">
                        <ul class="m-0 grid list-none gap-1 p-0 text-14">
                            @foreach (App\Enums\OrigenPostulacion::cases() as $origen)
                                <li class="flex items-baseline justify-between gap-2">
                                    <span class="text-neutral-800">{{ $origen->etiqueta() }}</span>
                                    <span class="font-semibold">{{ $embudo->postulacionesDe($origen) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </section>

    {{-- Detalle del match promedio (prototipo: openKpiDialog) --}}
    <x-ui.dialog wire:model="mostrarDetalleDelMatch" :title="'Match promedio · '.($resumen->matchPromedio === null ? '—' : $resumen->matchPromedio.'%')" max-width="440px">
        <p class="m-0 whitespace-pre-line">@if ($resumen->postulacionesConMatch > 0)Calculado sobre {{ $resumen->postulacionesConMatch }} {{ $resumen->postulacionesConMatch === 1 ? 'postulación' : 'postulaciones' }} con matching en los últimos {{ $diasDelMatch }} días.@else Todavía no hay postulaciones con matching en los últimos {{ $diasDelMatch }} días.@endif

La IA pondera competencias técnicas, experiencia y evidencia textual del CV.</p>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            <x-ui.button variant="primary" :href="route('matching.index')" wire:navigate>Ver matching</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>

    <x-vacantes.vista-previa wire:model="mostrarVistaPrevia" :vacante="$seleccionada" />
</div>
