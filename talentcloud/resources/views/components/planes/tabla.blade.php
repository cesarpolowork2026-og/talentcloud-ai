{{--
    Tabla de planes con sus precios (PlanEmpresa). La usan la página pública /planes y
    el diálogo «Planes» de Mi empresa.
    empresa: null en la página pública. Con empresa, marca su plan y muestra «Solicitar
    plan…» (wire:click="solicitarPlan('pro')" del componente Livewire que la contiene).
--}}
@props(['empresa' => null])

@php
    $soles = fn (int $monto) => 'S/ '.number_format($monto, 0, ',', ' ');
    $cantidad = fn (?int $n, string $uno, string $varios) => $n === null ? ucfirst($varios).' sin límite' : number_format($n, 0, ',', ' ').' '.($n === 1 ? $uno : $varios);
@endphp

{{-- Columnas según el espacio del contenedor (página o diálogo), no según la pantalla. --}}
<div {{ $attributes->class('grid gap-4') }} style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));">
    @foreach (\App\Enums\PlanEmpresa::cases() as $plan)
        @php
            $recomendado = $plan === \App\Enums\PlanEmpresa::Pro;
            $esSuPlan = $empresa !== null && $empresa->plan === $plan;
        @endphp
        <section @class(['card elev-sm flex flex-col', '!border-2 !border-accent-700' => $recomendado]) aria-labelledby="plan-{{ $plan->value }}" wire:key="plan-{{ $plan->value }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="plan-{{ $plan->value }}" class="m-0 text-20">{{ $plan->etiqueta() }}</h3>
                @if ($recomendado)
                    <x-ui.tag tone="accent">Recomendado</x-ui.tag>
                @endif
            </div>
            <p class="m-0 text-14 text-neutral-700">{{ $plan->descripcion() }}</p>

            <p class="m-0">
                @if ($plan->seCotiza())
                    <span class="text-13 text-neutral-700">Desde</span>
                @endif
                <span class="text-30 font-semibold">{{ $soles($plan->precioMensual()) }}</span>
                <span class="text-14 text-neutral-700">al mes</span>
            </p>
            <p class="m-0 text-13 text-neutral-700">
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
                <li>{{ $plan->cupoIaMensual() === null ? 'Matching con IA sin límite' : $cantidad($plan->cupoIaMensual(), 'postulación', 'postulaciones').' con IA al mes' }}</li>
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
                    @else
                        <x-ui.button size="sm" :variant="$recomendado ? 'primary' : 'secondary'" wire:click="solicitarPlan('{{ $plan->value }}')" wire:loading.attr="disabled">
                            {{ $plan->seCotiza() ? 'Solicitar cotización' : 'Solicitar plan '.$plan->etiqueta() }}
                        </x-ui.button>
                    @endif
                </div>
            @endif
        </section>
    @endforeach
</div>
