<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="antialiased">
    {{-- Pantallas de acceso con el diseño de Login.dc.html: mensaje y ejemplo de matching a la izquierda, formulario a la derecha. --}}
    <div class="flex min-h-screen flex-wrap items-stretch bg-bg">

        <div class="flex min-w-0 flex-[1_1_460px] flex-col justify-center gap-6 px-4 py-8 sm:p-8">
            <div class="flex items-baseline gap-2">
                <span class="font-heading text-22 font-semibold tracking-[-0.01em]">TalentCloud</span>
                <x-ui.tag tone="outline">AI</x-ui.tag>
            </div>

            <div class="max-w-[46ch]">
                <h1 class="mb-3 text-[clamp(32px,4vw,44px)] leading-[1.08] [text-wrap:pretty]">Encuentra el talento que necesitas.</h1>
                <p class="m-0 font-heading text-18 italic leading-[1.5] text-neutral-800 [text-wrap:pretty]">La IA analiza, relaciona y explica. Tú tomas la decisión.</p>
            </div>

            {{-- Ejemplo ilustrativo de coincidencias (contenido fijo del prototipo). --}}
            <x-ui.card kicker="Vacante · Desarrollador Backend" elev="md" class="max-w-[400px]" aria-hidden="true">
                <div class="card-title mb-3">Coincidencias encontradas</div>
                <div class="grid gap-2">
                    @foreach (['Java' => 96, 'Spring Boot' => 91, 'PostgreSQL' => 87, 'Docker' => 82] as $competencia => $porcentaje)
                        <div class="flex items-center gap-3">
                            <span class="w-[130px] text-14">{{ $competencia }}</span>
                            <x-ui.progress :value="$porcentaje" class="flex-1" />
                            <span class="w-[34px] text-right text-13 font-semibold text-accent-700">{{ $porcentaje }}%</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center gap-2 border-t border-divider pt-3">
                    <x-ui.icon name="check-circle" class="text-accent" />
                    <span class="text-14 text-neutral-800">4 competencias encontradas</span>
                </div>
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-[1_1_380px] items-center justify-center px-4 py-8 max-[900px]:order-first sm:p-8">
            <x-ui.card elev="lg" class="w-full max-w-[380px] !p-6">
                {{ $slot }}
            </x-ui.card>
        </div>
    </div>
    @livewireScripts
</body>
</html>
