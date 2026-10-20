{{-- "Dashboard" del prototipo (TalentCloud App.dc.html, isDashboard). Para el admin SaaS, "Panel de la plataforma". --}}
@use('App\Enums\PlanEmpresa')
<div>
    <x-ui.page-header :title="$titulo" :subtitle="$subtitulo" />

    @if ($plataforma)
        @php($soles = fn (int $monto): string => 'S/ '.number_format($monto, 0, ',', ' '))

        {{-- 1 · El negocio: empresas cliente, planes e ingresos --}}
        <section class="mb-10" aria-labelledby="panel-empresas">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="panel-empresas" class="m-0 text-16">1 · Empresas cliente</h2>
                    <p class="m-0 mt-1 text-13 text-neutral-700">Quién usa TalentCloud y con qué plan contratado (se cuentan las empresas activas; las que están en prueba, como Gratis).</p>
                </div>
                <x-ui.button size="sm" :href="route('empresas.index')" wire:navigate>Gestionar empresas</x-ui.button>
            </div>

            <div class="mb-4 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                <x-ui.stat label="Empresas activas" :value="$plataforma->empresasActivas" highlight />
                <x-ui.stat label="Suspendidas" :value="$plataforma->empresasSuspendidas" />
                <x-ui.stat :label="'En prueba '.PlanEmpresa::PLAN_DE_PRUEBA->etiqueta()" :value="$plataforma->enPrueba" />
                <x-ui.stat label="Ingreso mensual de referencia" :value="$soles($plataforma->ingresoMensual)" />
            </div>
            <p class="-mt-2 mb-4 text-13 text-neutral-700">
                Suma el precio de lista mensual (sin IGV) de Starter y Pro de las empresas activas; las pruebas aún no pagan.
                @if ($plataforma->enterpriseSinPrecio > 0)
                    {{ $plataforma->enterpriseSinPrecio === 1 ? '1 empresa Enterprise queda' : $plataforma->enterpriseSinPrecio.' empresas Enterprise quedan' }} por cotizar y no suma.
                @endif
                Es una referencia, no lo cobrado: el ingreso real se verá cuando se active el cobro en línea.
            </p>

            <x-ui.card class="mb-4 !bg-white">
                <div class="card-kicker">Empresas activas por plan contratado</div>
                <x-grafico.barra-apilada
                    rotulo="Empresas activas por plan contratado"
                    :items="collect(PlanEmpresa::cases())->map(fn (PlanEmpresa $plan) => [
                        'clave' => $plan->value,
                        'etiqueta' => $plan->etiqueta(),
                        'valor' => $plataforma->empresasConPlan($plan),
                        'detalle' => collect($plataforma->nombresConPlan($plan))->map(fn (array $e) => ['texto' => $e['nombre'], 'url' => route('empresas.index', ['ver' => $e['id']])])->all(),
                    ])->all()"
                />
            </x-ui.card>

            @if ($plataforma->solicitudesDePlan->isNotEmpty())
                <x-ui.card class="mb-4">
                    <div class="card-kicker">Solicitudes de cambio de plan</div>
                    <p class="m-0 text-13 text-neutral-700">Coordina el pago con la empresa y pulsa «Aplicar plan»: se abre el cambio de plan con el plan solicitado ya propuesto, y al confirmarlo la solicitud se atiende.</p>
                    <x-ui.table>
                        <x-slot:head>
                            <th>Empresa</th><th>Plan actual</th><th>Solicita</th><th>Fecha</th><th>Acción</th>
                        </x-slot:head>
                        {{-- Variables propias del bucle: `$empresa` y `$uso` las usa más abajo la tarjeta «Tu plan» de una empresa. --}}
                        @foreach ($plataforma->solicitudesDePlan as $solicitante)
                            <tr wire:key="solicitud-{{ $solicitante->id }}">
                                <td>{{ $solicitante->nombre }}</td>
                                <td>{{ $solicitante->etiquetaDelPlan() }}</td>
                                <td>{{ $solicitante->plan_solicitado?->etiqueta() }}</td>
                                <td>{{ \App\Support\Formato::fechaCorta($solicitante->plan_solicitado_at) }}</td>
                                <td><x-ui.button size="sm" variant="primary" :href="route('empresas.index', ['cambiar-plan' => $solicitante->id])" wire:navigate>Aplicar plan</x-ui.button></td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </x-ui.card>
            @endif

            <x-ui.card>
                <div class="card-kicker">Cerca del límite de su plan</div>
                @if ($plataforma->cercaDelLimite->isEmpty())
                    <p class="m-0 text-14 text-neutral-800">Ninguna empresa activa usa el 80 % o más de su plan.</p>
                @else
                    <p class="m-0 text-13 text-neutral-700">Usan el 80 % o más de sus vacantes activas o de sus usuarios (con invitaciones pendientes). Buen momento para ofrecerles otro plan.</p>
                    <x-ui.table>
                        <x-slot:head>
                            <th>Empresa</th><th>Plan</th><th>Vacantes activas</th><th>Usuarios e invitaciones</th>
                        </x-slot:head>
                        @foreach ($plataforma->cercaDelLimite as $fila)
                            @php($usoDeLaFila = $fila['uso'])
                            <tr wire:key="limite-{{ $fila['empresa']->id }}">
                                <td>{{ $fila['empresa']->nombre }}</td>
                                <td>{{ $usoDeLaFila->etiquetaDelPlan() }}</td>
                                <td>{{ $usoDeLaFila->vacantesActivas }} {{ $usoDeLaFila->limiteVacantes() === null ? '· sin límite' : 'de '.$usoDeLaFila->limiteVacantes() }}</td>
                                <td>{{ $usoDeLaFila->usuariosActivos + $usoDeLaFila->invitacionesPendientes }} {{ $usoDeLaFila->limiteUsuarios() === null ? '· sin límite' : 'de '.$usoDeLaFila->limiteUsuarios() }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </section>

        {{-- 2 · Uso de la plataforma y crecimiento --}}
        <section class="mb-10" aria-labelledby="panel-uso">
            <div class="mb-3">
                <h2 id="panel-uso" class="m-0 text-16">2 · Uso y crecimiento</h2>
                <p class="m-0 mt-1 text-13 text-neutral-700">Cuánta gente y cuánta actividad hay en la plataforma. Para ver los procesos de las empresas, usa «Supervisión» en el menú (solo lectura).</p>
            </div>

            <div class="mb-4 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                <x-ui.stat label="Usuarios de empresas" :value="$plataforma->usuariosDeEmpresas" />
                <x-ui.stat label="Postulantes registrados" :value="$plataforma->postulantes" />
                <x-ui.stat label="Vacantes activas" :value="$plataforma->vacantesActivas" />
                <x-ui.stat label="Postulaciones del mes" :value="$plataforma->postulacionesDelMes" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card class="!bg-white">
                    <div class="card-kicker">Postulaciones por semana</div>
                    <x-grafico.lineas
                        id="crecimiento-postulaciones"
                        :etiquetas="array_column($plataforma->crecimiento, 'semana')"
                        :series="[['nombre' => 'Postulaciones', 'valores' => array_column($plataforma->crecimiento, 'postulaciones'), 'principal' => true]]"
                        :descripcion="'Postulaciones recibidas en las últimas '.count($plataforma->crecimiento).' semanas, por semana (la etiqueta es el lunes de cada semana)'"
                    />
                </x-ui.card>
                <x-ui.card class="!bg-white">
                    <div class="card-kicker">Empresas nuevas por semana</div>
                    <x-grafico.lineas
                        id="crecimiento-empresas"
                        unidad="empresas"
                        :etiquetas="array_column($plataforma->crecimiento, 'semana')"
                        :series="[['nombre' => 'Empresas nuevas', 'valores' => array_column($plataforma->crecimiento, 'empresas'), 'principal' => true]]"
                        :descripcion="'Empresas que se registraron en las últimas '.count($plataforma->crecimiento).' semanas, por semana (la etiqueta es el lunes de cada semana)'"
                    />
                </x-ui.card>
            </div>
        </section>

        {{-- 3 · Estado del sistema y últimas acciones --}}
        <section class="mb-10 grid gap-4 md:grid-cols-2" aria-labelledby="panel-sistema">
            <x-ui.card>
                <div class="card-kicker"><h2 id="panel-sistema" class="m-0 text-inherit">3 · Estado del sistema</h2></div>
                <p class="m-0 text-14 text-neutral-800">
                    @if ($plataforma->sistemaSano())
                        Todo en orden: no hay trabajos fallidos ni CV con error de lectura.
                    @else
                        Hay cosas por revisar en el servidor.
                    @endif
                </p>
                <ul class="m-0 grid list-none gap-1 p-0 text-14">
                    <li><strong>{{ $plataforma->trabajosFallidos }}</strong> {{ $plataforma->trabajosFallidos === 1 ? 'trabajo fallido' : 'trabajos fallidos' }}</li>
                    <li><strong>{{ $plataforma->trabajosEnCola }}</strong> {{ $plataforma->trabajosEnCola === 1 ? 'trabajo en cola' : 'trabajos en cola' }}</li>
                    <li><strong>{{ $plataforma->cvConError }}</strong> {{ $plataforma->cvConError === 1 ? 'CV con error de lectura' : 'CV con error de lectura' }}</li>
                </ul>
            </x-ui.card>

            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="card-kicker">Últimas acciones de administración</div>
                    <x-ui.button size="sm" :href="route('auditoria')" wire:navigate>Ver el registro</x-ui.button>
                </div>
                @if ($plataforma->ultimasAcciones->isEmpty())
                    <p class="m-0 text-14 text-neutral-800">Todavía no se registró ninguna acción.</p>
                @else
                    <ul class="m-0 grid list-none gap-2 p-0 text-14">
                        @foreach ($plataforma->ultimasAcciones as $accion)
                            <li wire:key="accion-{{ $accion->id }}">
                                {{ $accion->descripcion }}
                                <span class="block text-13 text-neutral-700">{{ $accion->usuario_nombre }} · {{ \App\Support\Formato::fechaHora($accion->created_at) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>
    @else

    {{-- Indicadores: lo que hay que mirar primero. --}}
    <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <x-ui.stat label="Vacantes activas" :value="$resumen->vacantesActivas" />
        <x-ui.stat label="Postulantes" :value="$resumen->postulantes" />
        <x-ui.stat label="Por revisar" :value="$resumen->porRevisar" highlight />
        <x-ui.stat label="Entrevistas hoy" :value="$resumen->entrevistasHoy" />
    </div>
    <p class="-mt-3 mb-6 text-13 text-neutral-700">
        {{ $resumen->postulacionesNuevas }} {{ $resumen->postulacionesNuevas === 1 ? 'postulación nueva' : 'postulaciones nuevas' }} en los últimos {{ App\Services\Dashboard\DashboardService::DIAS_NUEVAS }} días
        · {{ $resumen->entrevistasProximas }} {{ $resumen->entrevistasProximas === 1 ? 'entrevista' : 'entrevistas' }} en los próximos 7 días.
        «Por revisar» son las postulaciones que siguen en «Postulado».
    </p>

    <div class="mb-8 grid items-stretch gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        {{-- Qué pide una acción hoy: la tarjeta llena el alto de la columna de al lado y cierra con los accesos directos. --}}
        <section aria-labelledby="atencion-titulo" class="flex flex-col">
            <h2 id="atencion-titulo" class="mb-3 text-16">Requiere tu atención</h2>
            <x-ui.card class="flex-1 !gap-0 !p-0">
                @if ($resumen->atencion === [])
                    <div class="grid flex-1 place-content-center justify-items-center gap-2 px-4 py-8 text-center">
                        <x-ui.icon name="seal-check" :size="36" class="text-accent-700" />
                        <p class="m-0 font-heading text-18 font-semibold">Todo al día</p>
                        <p class="m-0 max-w-[44ch] text-14 text-neutral-700">No hay entrevistas por confirmar ni postulaciones esperando más de {{ $diasSinRevisar }} días.</p>
                    </div>
                @else
                    <ul class="m-0 list-none p-0">
                        @foreach ($resumen->atencion as $aviso)
                            <li class="border-b border-divider last:border-b-0" wire:key="atencion-{{ $loop->index }}">
                                <a href="{{ route($aviso['ruta'], $aviso['parametros']) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-14 no-underline hover:bg-neutral-100">
                                    <x-ui.icon :name="$aviso['icono']" :size="20" :class="$aviso['urgente'] ? 'text-accent-2-700' : 'text-accent-700'" />
                                    <span @class(['flex-1 text-neutral-800', 'font-semibold' => $aviso['urgente']])>{{ $aviso['texto'] }}</span>
                                    <span class="text-13 text-accent-700">Ver</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-divider px-4 py-3">
                    <span class="mr-auto text-13 text-neutral-700">Ir directo a:</span>
                    <x-ui.button size="sm" :href="route('seleccion.index')" wire:navigate>Selección</x-ui.button>
                    <x-ui.button size="sm" :href="route('entrevistas.index')" wire:navigate>Entrevistas</x-ui.button>
                    <x-ui.button size="sm" :href="route('vacantes.index')" wire:navigate>Vacantes</x-ui.button>
                </div>
            </x-ui.card>
        </section>

        <div class="grid gap-4">
            {{-- Plan: dónde está la empresa y cuánto le queda (el momento natural de pensar en un plan mayor). --}}
            @if ($uso && $empresa)
                <x-ui.card :kicker="'Tu plan · '.$empresa->etiquetaDelPlan()">
                    @if ($empresa->enPrueba())
                        <p class="m-0 text-13 text-neutral-800">Te {{ $empresa->diasDePrueba() === 1 ? 'queda 1 día' : 'quedan '.$empresa->diasDePrueba().' días' }} de prueba del plan {{ App\Enums\PlanEmpresa::PLAN_DE_PRUEBA->etiqueta() }}.</p>
                    @endif
                    <x-empresas.uso-del-plan :uso="$uso" />
                    <div class="flex flex-wrap gap-2">
                        @can('solicitarPlan', $empresa)
                            <x-ui.button size="sm" :href="route('mi-empresa')" wire:navigate>Ver planes y gestionar</x-ui.button>
                        @else
                            <x-ui.button size="sm" :href="route('planes')" wire:navigate>Ver planes y precios</x-ui.button>
                        @endcan
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>

    {{-- Evolución de postulaciones: el periodo elegido frente al anterior de la misma duración. --}}
    <section class="mb-8" aria-labelledby="tendencia-titulo">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-[min(100%,320px)] flex-1">
                <h2 id="tendencia-titulo" class="m-0 text-16">Evolución de postulaciones</h2>
                <p class="m-0 mt-1 max-w-[90ch] text-13 text-neutral-700">
                    Postulaciones recibidas {{ $tendencia->diasPorCubeta === 1 ? 'por día' : 'cada '.$tendencia->diasPorCubeta.' días' }}, del {{ App\Support\Formato::fechaCorta($tendencia->desde) }} al {{ App\Support\Formato::fechaCorta($tendencia->hasta) }},
                    frente a los {{ $tendencia->dias }} días anteriores. Cuenta postulaciones, no personas. El periodo también define el embudo.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span id="periodo-rotulo" class="text-13 text-neutral-700">Periodo</span>
                <x-ui.seg labelledby="periodo-rotulo">
                    @foreach (App\Services\Dashboard\TendenciaDePostulaciones::PERIODOS as $dias)
                        <x-ui.seg-option name="periodo" :value="$dias" wire:model.live="periodo" wire:key="periodo-{{ $dias }}">{{ $dias }} días</x-ui.seg-option>
                    @endforeach
                </x-ui.seg>
            </div>
        </div>

        <x-ui.actualizable target="periodo">
        <x-ui.card>
            @if (! $tendencia->hayDatos())
                <x-ui.empty-state icon="chart-line-up" :message="'No hay postulaciones en estos '.$tendencia->dias.' días ni en los '.$tendencia->dias.' anteriores. Cuando lleguen, aquí verás cómo evolucionan.'" />
            @else
                <div class="flex flex-wrap items-baseline gap-x-8 gap-y-2">
                    <p class="m-0">
                        <span class="font-heading text-32 font-semibold text-accent-700">{{ number_format($tendencia->totalActual(), 0, ',', ' ') }}</span>
                        <span class="text-14 text-neutral-800">{{ $tendencia->totalActual() === 1 ? 'postulación' : 'postulaciones' }} en los últimos {{ $tendencia->dias }} días</span>
                    </p>
                    <p class="m-0 text-14 text-neutral-800" role="status">
                        @if ($tendencia->variacion() === null)
                            Sin postulaciones en los {{ $tendencia->dias }} días anteriores: no hay con qué comparar.
                        @else
                            <span class="font-semibold">{{ $tendencia->variacion() > 0 ? '▲ +' : ($tendencia->variacion() < 0 ? '▼ ' : '= ') }}{{ str_replace('.', ',', (string) $tendencia->variacion()) }} %</span>
                            frente a las {{ number_format($tendencia->totalAnterior(), 0, ',', ' ') }} del periodo anterior
                        @endif
                    </p>
                </div>

                <ul class="m-0 flex list-none flex-wrap gap-x-5 gap-y-1 p-0 text-13 text-neutral-800" aria-hidden="true">
                    <li class="flex items-center gap-2"><span class="inline-block h-[3px] w-6 rounded bg-accent-700"></span> {{ App\Support\Formato::fechaCorta($tendencia->desde) }} – {{ App\Support\Formato::fechaCorta($tendencia->hasta) }}</li>
                    <li class="flex items-center gap-2"><span class="inline-block w-6 border-t-2 border-dashed border-neutral-600"></span> {{ App\Support\Formato::fechaCorta($tendencia->desdeAnterior) }} – {{ App\Support\Formato::fechaCorta($tendencia->hastaAnterior) }} (anterior)</li>
                </ul>

                <x-grafico.lineas
                    id="tendencia"
                    :etiquetas="array_map(fn (array $c) => $c['desde']->equalTo($c['hasta']) ? App\Support\Formato::fechaCorta($c['desde']) : App\Support\Formato::fechaCorta($c['desde']).'–'.App\Support\Formato::fechaCorta($c['hasta']), $tendencia->cubetas)"
                    :series="[
                        ['nombre' => 'Periodo actual', 'valores' => array_column($tendencia->cubetas, 'actual'), 'principal' => true],
                        ['nombre' => 'Periodo anterior', 'valores' => array_column($tendencia->cubetas, 'anterior')],
                    ]"
                    :descripcion="'Postulaciones recibidas en los últimos '.$tendencia->dias.' días: '.$tendencia->totalActual().', frente a '.$tendencia->totalAnterior().' en los '.$tendencia->dias.' anteriores'"
                />
            @endif
        </x-ui.card>
        </x-ui.actualizable>
    </section>

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section aria-labelledby="vacantes-titulo">
            <h2 id="vacantes-titulo" class="mb-3 text-16">Vacantes en curso</h2>
            @if ($resumen->vacantesConMovimiento->isEmpty())
                <x-ui.empty-state icon="briefcase" message="Todavía no hay vacantes activas.">
                    @can('create', App\Models\Vacante::class)
                        <x-ui.button variant="primary" :href="route('vacantes.index', ['nueva' => 1])" wire:navigate class="mt-3">Crear una vacante</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Vacante</th>@if ($plataforma)<th>Empresa</th>@endif<th>Postulantes</th><th>Por revisar</th><th>Estado</th>
                    </x-slot:head>
                    @foreach ($resumen->vacantesConMovimiento as $vacante)
                        <tr class="tc-row" wire:key="reciente-{{ $vacante->id }}" wire:click="verVistaPrevia({{ $vacante->id }})">
                            <td>
                                <x-ui.boton-fila wire:click.stop="verVistaPrevia({{ $vacante->id }})">{{ $vacante->titulo }}</x-ui.boton-fila>
                                @if (isset($resumen->estancadas[$vacante->id]))
                                    <x-ui.tag tone="accent-2" class="ml-2">Sin avances · {{ $resumen->estancadas[$vacante->id] }} d</x-ui.tag>
                                @endif
                            </td>
                            @if ($plataforma)<td>{{ $vacante->empresa?->nombre }}</td>@endif
                            <td>{{ $vacante->postulaciones_count }}</td>
                            <td @class(['font-semibold text-accent-700' => $vacante->por_revisar > 0])>{{ $vacante->por_revisar }}</td>
                            <td class="whitespace-nowrap"><x-ui.estado :valor="$vacante->estado" /></td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <p class="mt-2 text-13 text-neutral-700">Ordenadas por las que más postulaciones tienen por revisar.</p>
                <a href="{{ route('vacantes.index') }}" wire:navigate class="mt-1 inline-block text-13">Ver todas las vacantes</a>
            @endif
        </section>

        <livewire:dashboard.actividad-reciente />
    </div>

    {{-- Embudo de selección (ampliación: no está en el prototipo) --}}
    <section class="mt-8" aria-labelledby="embudo-titulo">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="embudo-titulo" class="m-0 text-16">Embudo de selección</h2>
                <p class="m-0 mt-1 max-w-[70ch] text-13 text-neutral-700">
                    Postulaciones recibidas del {{ App\Support\Formato::fechaCorta($embudo->desde) }} al {{ App\Support\Formato::fechaCorta($embudo->hasta) }} ({{ $embudo->dias }} días) y la etapa más avanzada a la que llegó cada una.
                    El porcentaje junto a cada etapa es qué parte de quienes llegaron a la anterior pasó a esta.
                </p>
            </div>
            <div>
                <label for="embudo-vacante" class="sr-only">Filtrar el embudo por vacante</label>
                <x-ui.buscador id="embudo-vacante" metodo="buscarVacantes" modelo="vacanteDelEmbudo" :etiqueta="$etiquetaDeLaVacanteDelEmbudo" vacio="Todas las vacantes" placeholder="Todas las vacantes" class="w-[280px] max-w-full" />
            </div>
        </div>

        <x-ui.actualizable target="periodo,vacanteDelEmbudo">
        @if ($embudo->total === 0)
            <x-ui.empty-state icon="funnel" :message="'No hay postulaciones en los últimos '.$embudo->dias.' días.'" />
        @else
            {{-- items-start: la tarjeta del embudo mide lo que su contenido, no la altura de la columna de la derecha (dejaba un vacío debajo). --}}
            <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-ui.card class="!bg-white">
                    <x-grafico.embudo :etapas="collect($embudo->etapas)->map(fn (array $f) => [
                        'clave' => $f['etapa']->value,
                        'etiqueta' => $f['etapa']->etiqueta(),
                        'total' => $f['total'],
                        'porcentaje' => $f['porcentaje'],
                        'conversion' => $f['conversion'],
                    ])->all()" />
                    @php($conversion = $embudo->conversionTotalTexto())
                    @if ($conversion !== null)
                        <p class="m-0 text-14 text-neutral-800">De cada 100 postulaciones del periodo, <span class="font-semibold">{{ $conversion }}</span> {{ in_array($conversion, ['1', 'menos de 1'], true) ? 'llegó' : 'llegaron' }} a {{ $embudo->etapas[array_key_last($embudo->etapas)]['etapa']->etiqueta() }}.</p>
                    @endif
                    <p class="m-0 text-13 text-neutral-700">
                        {{ $embudo->descartadas }} {{ $embudo->descartadas === 1 ? 'descartada' : 'descartadas' }} por la empresa y {{ $embudo->retiradas }} {{ $embudo->retiradas === 1 ? 'retirada' : 'retiradas' }} por el candidato: cuentan en las etapas a las que llegaron antes.
                    </p>
                </x-ui.card>

                <div class="flex flex-col gap-4">
                    <x-ui.card kicker="Tiempo medio hasta contratar">
                        @if ($embudo->diasHastaContratar === null)
                            <p class="m-0 font-heading text-24 font-semibold">Sin contrataciones aún</p>
                        @else
                            <p class="m-0 font-heading text-32 font-semibold">{{ App\Support\Formato::dias($embudo->diasHastaContratar) }}</p>
                            <p class="m-0 text-13 text-neutral-700">Desde la postulación, en {{ $embudo->contrataciones }} {{ $embudo->contrataciones === 1 ? 'contratación' : 'contrataciones' }} del periodo.</p>
                        @endif
                    </x-ui.card>
                    @if ($embudo->tiempoPorEtapa !== [])
                        <x-ui.card kicker="Tiempo en cada etapa" class="!bg-white">
                            <x-grafico.barras
                                rotulo="Días medios en cada etapa"
                                :items="collect($embudo->tiempoPorEtapa)->map(fn (array $t) => [
                                    'clave' => $t['etapa']->value,
                                    'etiqueta' => $t['etapa']->etiqueta(),
                                    'valor' => $t['dias'],
                                    'texto' => App\Support\Formato::dias($t['dias']),
                                ])->all()"
                            />
                            <p class="m-0 text-13 text-neutral-700">Lo que tardaron en salir de cada etapa las postulaciones del periodo (solo etapas ya terminadas). La barra azul es la más lenta: la que más conviene mirar.</p>
                        </x-ui.card>
                    @endif
                    <x-ui.card kicker="Origen de las postulaciones" class="flex-1">
                        <ul class="m-0 grid list-none gap-1 p-0 text-14">
                            @foreach (App\Enums\OrigenPostulacion::cases() as $origen)
                                <li class="flex items-baseline justify-between gap-2">
                                    <span class="text-neutral-800">{{ $origen->etiqueta() }}</span>
                                    <span class="font-semibold">{{ $embudo->postulacionesDe($origen) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @php($propias = $embudo->postulacionesDe(App\Enums\OrigenPostulacion::Postulante))
                        @if ($embudo->total > 0)
                            @php($porcentajePropias = (int) round($propias * 100 / $embudo->total))
                            <div class="mt-auto grid gap-1">
                                <x-ui.progress :value="$porcentajePropias" :label="$porcentajePropias.' % de las postulaciones llegaron por cuenta del postulante'" />
                                <p class="m-0 text-13 text-neutral-700">{{ $porcentajePropias }} % llegó por cuenta del postulante, sin que el equipo la registrara.</p>
                            </div>
                        @endif
                    </x-ui.card>
                </div>
            </div>
        @endif
        </x-ui.actualizable>
    </section>

    @endif

    <x-vacantes.vista-previa wire:model="mostrarVistaPrevia" :vacante="$seleccionada" />
</div>
