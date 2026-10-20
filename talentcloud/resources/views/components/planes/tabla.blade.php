{{--
    Tabla de planes con sus precios (PlanEmpresa). La usan la página pública /planes y
    el diálogo «Planes» de Mi empresa.
    empresa: null en la página pública. Con empresa, marca su plan y muestra las acciones
    del componente Livewire que la contiene: «Solicitar plan…» (solicitarPlan('pro')) o,
    con cobro (pasarela de pago), «Pagar…» (contratarPlan('pro', 'mensual')) y el portal
    de la suscripción (gestionarSuscripcion) para cambiarla o cancelarla.
--}}
@props(['empresa' => null, 'cobro' => false])

@php
    // Sin empresa (página pública): adónde lleva elegir un plan; null si la sesión no es de una empresa.
    $destinoPublico = $empresa !== null ? null : match (true) {
        ! auth()->check() => route('register'),
        auth()->user()->hasRole(\App\Enums\Rol::Empresa->value) => route('mi-empresa', ['abrir' => 'planes']),
        default => null,
    };
    $soles = fn (int $monto) => 'S/ '.number_format($monto, 0, ',', ' ');
    $cantidad = fn (?int $n, string $uno, string $varios) => $n === null ? ucfirst($varios).' sin límite' : number_format($n, 0, ',', ' ').' '.($n === 1 ? $uno : $varios);
@endphp

{{-- Columnas según el espacio del contenedor (página o diálogo), no según la pantalla. --}}
<div {{ $attributes->class('grid gap-4') }} style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));">
    @foreach (\App\Enums\PlanEmpresa::cases() as $plan)
        @php
            $recomendado = $plan === \App\Enums\PlanEmpresa::Pro;
            $esSuPlan = $empresa !== null && $empresa->plan === $plan;
            // A más precio, un fondo más intenso: degradados claros donde manda el teal de la marca y que
            // se funden hacia menta (Starter) o lila (Pro y, más marcado, Enterprise). Texto oscuro en todas.
            // Clases escritas completas para que Tailwind las genere.
            [$fondo, $secundario] = match ($plan) {
                \App\Enums\PlanEmpresa::Gratis => ['!bg-white', 'text-neutral-700'],
                \App\Enums\PlanEmpresa::Starter => ['plan-starter', 'text-neutral-700'],
                \App\Enums\PlanEmpresa::Pro => ['plan-pro !border-2 !border-accent-700', 'text-neutral-800'],
                \App\Enums\PlanEmpresa::Enterprise => ['plan-premium', 'text-ink'],
            };
        @endphp
        <section @class(['card elev-sm flex flex-col', $fondo]) aria-labelledby="plan-{{ $plan->value }}" wire:key="plan-{{ $plan->value }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="plan-{{ $plan->value }}" class="m-0 text-20">{{ $plan->etiqueta() }}</h3>
                @if ($recomendado)
                    <x-ui.tag tone="accent">Recomendado</x-ui.tag>
                @endif
            </div>
            <p class="m-0 text-14 {{ $secundario }}">{{ $plan->descripcion() }}</p>

            <p class="m-0">
                @if ($plan->seCotiza())
                    <span class="text-13 {{ $secundario }}">Desde</span>
                @endif
                <span class="text-30 font-semibold">{{ $soles($plan->precioMensual()) }}</span>
                <span class="text-14 {{ $secundario }}">al mes</span>
            </p>
            <p class="m-0 text-13 {{ $secundario }}">
                @if ($plan->precioMensualAnual() !== null)
                    {{ $soles($plan->precioMensualAnual()) }} al mes con pago anual. Más IGV.
                @elseif ($plan->seCotiza())
                    Contrato anual, cotizado a medida. Más IGV.
                @else
                    Sin tarjeta y sin fecha de vencimiento.
                @endif
            </p>

            <ul class="m-0 grid list-disc gap-1 pl-5 text-14">
                <li>{{ $cantidad($plan->limiteVacantesActivas(), 'vacante activa', 'vacantes activas') }}</li>
                <li>{{ $cantidad($plan->limiteUsuarios(), 'usuario', 'usuarios') }}</li>
                <li>{{ $plan->soporte() }}</li>
            </ul>

            @if ($empresa !== null)
                <div class="mt-auto pt-2">
                    @if ($esSuPlan && ! $empresa->enPrueba())
                        <x-ui.tag tone="accent">Tu plan</x-ui.tag>
                    @elseif ($esSuPlan)
                        <x-ui.tag tone="outline">Al terminar la prueba</x-ui.tag>
                    @elseif ($empresa->plan_solicitado === $plan)
                        <x-ui.tag>Solicitado el {{ \App\Support\Formato::fechaCorta($empresa->plan_solicitado_at) }}</x-ui.tag>
                    @elseif ($cobro && $empresa->tieneSuscripcionVigente() && ($plan->sePagaEnLinea() || $plan === \App\Enums\PlanEmpresa::Gratis))
                        {{-- Con una suscripción en curso, cambiar de plan o cancelarla se hace en el portal de la pasarela. --}}
                        <x-ui.button size="sm" variant="secondary" wire:click="gestionarSuscripcion" wire:loading.attr="disabled">
                            {{ $plan === \App\Enums\PlanEmpresa::Gratis ? 'Cancelar suscripción' : 'Cambiar al plan '.$plan->etiqueta() }}
                        </x-ui.button>
                    @elseif ($cobro && $plan->sePagaEnLinea() && $empresa->plan !== \App\Enums\PlanEmpresa::Enterprise)
                        <div class="grid gap-2">
                            @foreach (\App\Enums\Periodicidad::cases() as $periodicidad)
                                <x-ui.button size="sm" :variant="$recomendado && $periodicidad === \App\Enums\Periodicidad::Mensual ? 'primary' : 'secondary'" wire:click="contratarPlan('{{ $plan->value }}', '{{ $periodicidad->value }}')" wire:loading.attr="disabled">
                                    Pagar {{ $periodicidad->importe($plan) }}
                                </x-ui.button>
                            @endforeach
                        </div>
                    @else
                        <x-ui.button size="sm" :variant="$recomendado ? 'primary' : 'secondary'" wire:click="solicitarPlan('{{ $plan->value }}')" wire:loading.attr="disabled">
                            {{ $plan->seCotiza() ? 'Solicitar cotización' : 'Solicitar plan '.$plan->etiqueta() }}
                        </x-ui.button>
                    @endif
                </div>
            @elseif ($destinoPublico !== null)
                {{-- Página pública: sin sesión se crea la empresa; con sesión de empresa se elige en «Mi empresa». --}}
                <div class="mt-auto pt-2">
                    <x-ui.button size="sm" :variant="$recomendado ? 'primary' : 'secondary'" :href="$destinoPublico" wire:navigate>
                        @if ($plan->seCotiza())
                            Solicitar cotización
                        @elseif (auth()->check())
                            Elegir plan {{ $plan->etiqueta() }}
                        @else
                            {{-- Registrarse es gratis y trae la prueba del plan Pro: el botón no promete una prueba de Starter. --}}
                            Crear empresa gratis
                        @endif
                    </x-ui.button>
                </div>
            @endif
        </section>
    @endforeach
</div>
