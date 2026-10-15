{{-- "Calidad de la IA" del admin SaaS (ampliación sobre el prototipo). --}}
@use('App\Support\Formato')
@use('Illuminate\Support\Str')
<div>
    <x-ui.page-header title="Calidad de la IA" subtitle="Supervisión humana de los resultados de la IA en toda la plataforma (últimos {{ $dias }} días)." />

    <div class="mb-8 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <x-ui.stat label="Analizadas con IA" :value="$resumen->porcentajeConIa() === null ? '—' : $resumen->porcentajeConIa().'%'" highlight />
        <x-ui.stat label="Con análisis básico" :value="$resumen->basicas" />
        <x-ui.stat label="Básico por cupo del plan" :value="$resumen->sinCupo" />
        <x-ui.stat label="Marcadas como incorrectas" :value="$resumen->marcasDelPeriodo" />
        <x-ui.stat label="CVs con error de lectura" :value="$resumen->cvsConError" />
    </div>

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <section class="min-w-0">
            <h2 class="mb-1 text-16">Resultados marcados como incorrectos</h2>
            <p class="mb-3 text-13 text-neutral-700">Lo que la IA dijo de cada requisito cuando un reclutador no estuvo de acuerdo. Sirve para recalibrar el matching.</p>

            @if ($resumen->marcas->isEmpty())
                <x-ui.empty-state icon="check-circle" message="Ningún reclutador ha marcado resultados como incorrectos." />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Fecha</th><th>Vacante</th><th>Requisito</th><th>La IA dijo</th><th>Marcado por</th><th><span class="sr-only">Acciones</span></th>
                    </x-slot:head>
                    @foreach ($resumen->marcas as $indice => $marca)
                        <tr wire:key="marca-{{ $indice }}">
                            <td class="whitespace-nowrap text-neutral-700">{{ Formato::fechaCorta($marca->fecha) }}</td>
                            <td>{{ $marca->vacante }}<span class="block text-12 text-neutral-700">{{ $marca->empresa }}</span></td>
                            <td>{{ $marca->requisito }}</td>
                            <td>
                                @if ($marca->nivelDeLaIa)
                                    <x-ui.estado :valor="$marca->nivelDeLaIa" />
                                @endif
                                @if ($marca->evidencia)
                                    <span class="mt-1 block text-12 italic text-neutral-800">“{{ Str::limit($marca->evidencia, 90) }}”</span>
                                @endif
                                @if ($marca->comentario)
                                    <span class="mt-1 block text-12 text-neutral-700">Comentario: {{ $marca->comentario }}</span>
                                @endif
                            </td>
                            <td>{{ $marca->marcadaPor }}</td>
                            <td><a href="{{ $marca->urlMatching }}" wire:navigate class="whitespace-nowrap">Ver matching</a></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </section>

        <div class="grid gap-6">
            @php
                $enCurso = $enCola > 0;
                // Con un total conocido (lo encolado desde esta pantalla) se muestra el avance; si no, solo cuántas esperan.
                $conTotal = $enCurso && $encoladas !== null && $encoladas >= $enCola;
                $hechas = $conTotal ? $encoladas - $enCola : 0;
                $terminado = ! $enCurso && $encoladas !== null;
            @endphp
            <x-ui.card elev="sm" class="!bg-white" kicker="Análisis básico pendiente">
                @if ($enCurso)
                    {{-- Mientras haya trabajo en cola, la pantalla se actualiza sola (el elemento desaparece al terminar y el sondeo se detiene). --}}
                    <div class="grid gap-3" wire:poll.3s.visible>
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="m-0 text-14 font-semibold text-ink">{{ $conTotal ? 'Rehaciendo con IA…' : 'Análisis de IA en curso…' }}</p>
                            <p class="m-0 text-13 tabular-nums text-neutral-700">
                                @if ($conTotal)
                                    {{ number_format($hechas, 0, ',', ' ') }} de {{ number_format($encoladas, 0, ',', ' ') }}
                                @else
                                    {{ number_format($enCola, 0, ',', ' ') }} en cola
                                @endif
                            </p>
                        </div>
                        @if ($conTotal)
                            <x-ui.progress :value="$hechas * 100 / max(1, $encoladas)" label="Avance de los análisis rehechos con IA" />
                        @endif
                        <p class="m-0 text-13 text-neutral-700">Sigue en segundo plano: puedes salir de esta pantalla. Los números de arriba se actualizan solos.</p>
                    </div>
                @elseif ($terminado)
                    <div class="grid gap-3" role="status">
                        <p class="m-0 flex items-start gap-2 text-14 text-ink">
                            <x-ui.icon name="check-circle" class="mt-[2px] shrink-0 text-accent-700" />
                            <span>
                                <strong>Listo.</strong>
                                Se procesaron {{ number_format($encoladas, 0, ',', ' ') }} {{ $encoladas === 1 ? 'postulación' : 'postulaciones' }}.
                                @if ($resumen->basicasPendientes === 0)
                                    Todas tienen ahora el análisis del servicio de IA.
                                @else
                                    {{ number_format($resumen->basicasPendientes, 0, ',', ' ') }} {{ $resumen->basicasPendientes === 1 ? 'sigue' : 'siguen' }} con el análisis básico porque el servicio no respondió; puedes reintentarlo.
                                @endif
                            </span>
                        </p>
                        <x-ui.button size="sm" wire:click="cerrarResultado">Entendido</x-ui.button>
                    </div>
                @else
                    <p class="m-0 text-14 text-neutral-800">
                        @if ($resumen->basicasPendientes === 0)
                            Todas las postulaciones tienen el análisis del servicio de IA.
                        @else
                            {{ number_format($resumen->basicasPendientes, 0, ',', ' ') }} {{ $resumen->basicasPendientes === 1 ? 'postulación tiene' : 'postulaciones tienen' }} el análisis básico porque el servicio de IA no estaba disponible cuando se calcularon.
                        @endif
                    </p>
                @endif
                @error('rehacer')
                    <p class="field-error m-0" role="alert">{{ $message }}</p>
                @enderror
                @if ($enCurso)
                    <x-ui.button variant="primary" block disabled data-cargando>Rehaciendo…</x-ui.button>
                @elseif ($resumen->basicasPendientes > 0)
                    <x-ui.button variant="primary" block wire:click="confirmarRehacer">{{ $terminado ? 'Reintentar con IA' : 'Rehacer con IA' }}</x-ui.button>
                @endif
            </x-ui.card>

            <x-ui.card elev="sm" class="!bg-white" kicker="Trabajos fallidos en la cola">
                <p class="m-0 text-14 text-neutral-800">
                    {{ $resumen->trabajosFallidos === 0 ? 'No hay trabajos fallidos.' : $resumen->trabajosFallidos.' en total. Por seguridad no se muestran sus datos ni el error: revisa el registro del servidor.' }}
                </p>
                @if ($resumen->ultimosTrabajosFallidos->isNotEmpty())
                    <ul class="m-0 grid list-none gap-1 p-0 text-13">
                        @foreach ($resumen->ultimosTrabajosFallidos as $trabajo)
                            <li class="flex justify-between gap-3 border-b border-divider pb-1">
                                <span>{{ $trabajo['nombre'] }} <span class="text-neutral-700">· {{ $trabajo['cola'] }}</span></span>
                                <span class="whitespace-nowrap text-neutral-700">{{ Formato::fechaHora($trabajo['fecha']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.dialog wire:model="mostrarConfirmacion" title="Rehacer los análisis básicos" max-width="440px">
        <p class="m-0">Se volverá a calcular con el servicio de IA el matching de {{ $resumen->basicasPendientes }} {{ $resumen->basicasPendientes === 1 ? 'postulación' : 'postulaciones' }}. Verás el avance en esta pantalla; puede tardar varios minutos si son muchas.</p>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button variant="primary" wire:click="rehacerBasicos" wire:loading.attr="disabled" wire:target="rehacerBasicos">Rehacer con IA</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
