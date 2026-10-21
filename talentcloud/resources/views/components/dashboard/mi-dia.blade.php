{{--
    «Mi día» del reclutador (dashboard): lo que le toca a él, no a la empresa. Sus entrevistas, las evaluaciones que le
    faltan y la cola de postulaciones por revisar (esa sí es de la empresa: las postulaciones no se asignan a una persona).
    Todo sale de MiDiaDelReclutadorService; aquí solo se pinta.
--}}
@props(['dia'])

@php
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? $uno : $varios;
    $espera = function ($desde): string {
        $dias = (int) floor(\Illuminate\Support\Carbon::parse($desde)->diffInDays(now()));

        return match (true) {
            $dias <= 0 => 'llegó hoy',
            $dias === 1 => 'espera desde hace 1 día',
            default => "espera desde hace {$dias} días",
        };
    };
@endphp

{{-- Indicadores: míos primero; «Por revisar» es de la empresa y se dice. --}}
<div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <x-ui.stat label="Mis entrevistas hoy" :value="$dia->entrevistasHoy" />
    <x-ui.stat label="Mis próximos 7 días" :value="$dia->entrevistasProximas" />
    <x-ui.stat label="Por cerrar" :value="$dia->totalPorCerrar" :highlight="$dia->totalPorCerrar > 0" />
    <x-ui.stat label="Por evaluar" :value="$dia->totalPorEvaluar" :highlight="$dia->totalPorEvaluar > 0" />
    <x-ui.stat label="Por revisar (empresa)" :value="$dia->totalPorRevisar" :highlight="$dia->totalPorRevisar > 0" />
</div>

{{-- Entrevistas que ya terminaron y nadie cerró: sin completarlas no se pueden evaluar. Solo aparece si hay alguna. --}}
@if ($dia->totalPorCerrar > 0)
    <section class="mb-8" aria-labelledby="por-cerrar-titulo">
        <h2 id="por-cerrar-titulo" class="mb-1 text-16">Entrevistas por cerrar</h2>
        <p class="mb-3 mt-0 text-13 text-neutral-700">Ya terminaron y siguen como pendientes o confirmadas. Dalas por completadas (o cancélalas) para poder evaluarlas.</p>
        <x-ui.card class="!gap-0 !p-0">
            <ul class="m-0 list-none p-0">
                @foreach ($dia->porCerrar as $entrevista)
                    @php($inicio = $entrevista->inicia_at->copy()->timezone(config('app.timezone')))
                    <li class="border-b border-divider last:border-b-0" wire:key="por-cerrar-{{ $entrevista->id }}">
                        <a href="{{ route('postulaciones.show', $entrevista->postulacion_id) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-14 no-underline hover:bg-neutral-100">
                            <span class="w-[5.5rem] shrink-0 text-13 text-neutral-700">
                                <span class="block font-semibold text-neutral-800">{{ \App\Support\Formato::fechaCorta($inicio) }}</span>
                                {{ $inicio->format('H:i') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-neutral-800">{{ $entrevista->postulacion?->candidato?->nombre }}</span>
                                <span class="block truncate text-13 text-neutral-700">{{ $entrevista->postulacion?->vacante?->titulo }}</span>
                            </span>
                            <x-ui.estado :valor="$entrevista->estado" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-divider px-4 py-3">
                <span class="text-13 text-neutral-700">
                    @if ($dia->totalPorCerrar > count($dia->porCerrar))
                        y {{ $dia->totalPorCerrar - count($dia->porCerrar) }} más
                    @endif
                </span>
                <x-ui.button size="sm" variant="primary" :href="route('entrevistas.index', ['vencidas' => 1])" wire:navigate>Ver y cerrar</x-ui.button>
            </div>
        </x-ui.card>
    </section>
@endif

<div class="mb-8 grid items-start gap-6 md:grid-cols-2">
    {{-- Mi agenda --}}
    <section aria-labelledby="mi-agenda-titulo">
        <h2 id="mi-agenda-titulo" class="mb-3 text-16">Mi agenda</h2>
        <x-ui.card class="!gap-0 !p-0">
            @if ($dia->agenda->isEmpty())
                <div class="grid justify-items-center gap-2 px-4 py-8 text-center">
                    <x-ui.icon name="calendar-blank" :size="32" class="text-neutral-600" />
                    <p class="m-0 text-14 text-neutral-700">No tienes entrevistas programadas.</p>
                </div>
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($dia->agenda as $entrevista)
                        @php($inicia = $entrevista->inicia_at->copy()->timezone(config('app.timezone')))
                        <li class="border-b border-divider last:border-b-0" wire:key="mi-entrevista-{{ $entrevista->id }}">
                            <a href="{{ route('postulaciones.show', $entrevista->postulacion_id) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-14 no-underline hover:bg-neutral-100">
                                <span class="w-[5.5rem] shrink-0 text-13 text-neutral-700">
                                    <span class="block font-semibold text-neutral-800">{{ $inicia->isToday() ? 'Hoy' : ($inicia->isTomorrow() ? 'Mañana' : \App\Support\Formato::fechaCorta($inicia)) }}</span>
                                    {{ $inicia->format('H:i') }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold text-neutral-800">{{ $entrevista->postulacion?->candidato?->nombre }}</span>
                                    <span class="block truncate text-13 text-neutral-700">{{ $entrevista->postulacion?->vacante?->titulo }} · {{ $entrevista->modalidad->etiqueta() }}</span>
                                </span>
                                <x-ui.estado :valor="$entrevista->estado" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-divider px-4 py-3">
                <span class="text-13 text-neutral-700">
                    @if ($dia->sinConfirmar > 0)
                        {{ $dia->sinConfirmar }} {{ $plural($dia->sinConfirmar, 'entrevista por confirmar', 'entrevistas por confirmar') }}
                    @endif
                </span>
                <x-ui.button size="sm" :href="route('entrevistas.index')" wire:navigate>Ver mis entrevistas</x-ui.button>
            </div>
        </x-ui.card>
    </section>

    {{-- Por evaluar --}}
    <section aria-labelledby="por-evaluar-titulo">
        <h2 id="por-evaluar-titulo" class="mb-3 text-16">Evaluaciones pendientes</h2>
        <x-ui.card class="!gap-0 !p-0">
            @if ($dia->porEvaluar->isEmpty())
                <div class="grid justify-items-center gap-2 px-4 py-8 text-center">
                    <x-ui.icon name="seal-check" :size="32" class="text-accent-700" />
                    <p class="m-0 text-14 text-neutral-700">No tienes evaluaciones pendientes.</p>
                </div>
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($dia->porEvaluar as $postulacion)
                        <li class="border-b border-divider last:border-b-0" wire:key="por-evaluar-{{ $postulacion->id }}">
                            <a href="{{ route('postulaciones.show', $postulacion->id) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-14 no-underline hover:bg-neutral-100">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold text-neutral-800">{{ $postulacion->candidato?->nombre }}</span>
                                    <span class="block truncate text-13 text-neutral-700">{{ $postulacion->vacante?->titulo }} · ya entrevistaste</span>
                                </span>
                                <span class="text-13 text-accent-700">Evaluar</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-divider px-4 py-3">
                <span class="text-13 text-neutral-700">
                    @if ($dia->totalPorEvaluar > count($dia->porEvaluar))
                        y {{ $dia->totalPorEvaluar - count($dia->porEvaluar) }} más
                    @endif
                </span>
                <x-ui.button size="sm" :href="route('evaluaciones.index')" wire:navigate>Ir a evaluaciones</x-ui.button>
            </div>
        </x-ui.card>
    </section>
</div>

{{-- Cola de trabajo: lo que más espera primero --}}
<section class="mb-8" aria-labelledby="por-revisar-titulo">
    <h2 id="por-revisar-titulo" class="mb-1 text-16">Postulaciones por revisar</h2>
    <p class="mb-3 mt-0 text-13 text-neutral-700">Las de toda la empresa que siguen en «Postulado», las que más esperan primero.</p>
    <x-ui.card class="!gap-0 !p-0">
        @if ($dia->porRevisar->isEmpty())
            <div class="grid justify-items-center gap-2 px-4 py-8 text-center">
                <x-ui.icon name="seal-check" :size="32" class="text-accent-700" />
                <p class="m-0 text-14 text-neutral-700">No hay postulaciones esperando revisión.</p>
            </div>
        @else
            <ul class="m-0 list-none p-0">
                @foreach ($dia->porRevisar as $postulacion)
                    <li class="border-b border-divider last:border-b-0" wire:key="por-revisar-{{ $postulacion->id }}">
                        <a href="{{ route('postulaciones.show', $postulacion->id) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-14 no-underline hover:bg-neutral-100">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-neutral-800">{{ $postulacion->candidato?->nombre }}</span>
                                <span class="block truncate text-13 text-neutral-700">{{ $postulacion->vacante?->titulo }}</span>
                            </span>
                            <span class="shrink-0 text-13 text-neutral-700">{{ $espera($postulacion->getAttribute('en_espera_desde')) }}</span>
                            <span class="text-13 text-accent-700">Revisar</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-divider px-4 py-3">
            <span class="text-13 text-neutral-700">
                @if ($dia->totalPorRevisar > count($dia->porRevisar))
                    y {{ $dia->totalPorRevisar - count($dia->porRevisar) }} más
                @endif
            </span>
            <x-ui.button size="sm" variant="primary" :href="route('seleccion.index')" wire:navigate>Abrir Selección</x-ui.button>
        </div>
    </x-ui.card>
</section>
