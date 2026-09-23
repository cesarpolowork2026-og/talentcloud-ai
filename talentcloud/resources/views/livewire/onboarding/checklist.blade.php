{{-- Onboarding del prototipo (TalentCloud App.dc.html, isOnboarding). --}}
<div class="max-w-[640px]">
    <h1 class="mb-1 text-30">Bienvenido a TalentCloud</h1>
    <p class="mb-3 text-15 text-neutral-700">Configuremos {{ $empresa->nombre }} en 3 pasos.</p>
    <p class="mb-4 text-13 text-neutral-600">{{ $completados }} de 3 completadas</p>

    <div class="grid gap-3">
        @foreach ($pasos as $paso)
            <x-ui.card class="!flex-row flex-wrap items-center justify-between !gap-3" wire:key="paso-{{ $paso['clave'] }}">
                <div class="flex items-center gap-3">
                    <x-ui.icon name="check-circle" :size="20" @class(['text-accent' => $paso['hecho'], 'text-neutral-300' => ! $paso['hecho']]) />
                    <div>
                        <div class="text-15 font-semibold">
                            {{ $paso['titulo'] }}
                            @if ($paso['hecho'])
                                <span class="sr-only">(completado)</span>
                            @endif
                        </div>
                        <div class="text-13 text-neutral-600">{{ $paso['descripcion'] }}</div>
                    </div>
                </div>
                <x-ui.button :href="$paso['hecho'] ? $paso['urlHecho'] : $paso['url']" wire:navigate>
                    {{ $paso['hecho'] ? 'Ver' : $paso['accion'] }}
                </x-ui.button>
            </x-ui.card>
        @endforeach
    </div>

    <a href="{{ route('dashboard') }}" wire:navigate class="mt-4 inline-block text-14">Saltar por ahora →</a>
</div>
