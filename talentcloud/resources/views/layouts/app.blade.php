<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    {{-- Pantallas con sesión: privadas, nunca para buscadores (ver también CabecerasDeSeguridad y robots.txt). --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
</head>
<body class="antialiased">
    <div class="min-h-screen bg-bg">
        <livewire:layout.navigation />

        {{--
            Contenedor de página del prototipo (1240px, padding space-8).
            Mientras wire:navigate carga la pantalla siguiente se muestra el
            esqueleto de navegación, como en el prototipo.
        --}}
        <main
            class="mx-auto max-w-page px-4 py-6 sm:p-8"
            x-data="{ navegando: false }"
            x-on:livewire:navigate.window="navegando = true"
            x-on:livewire:navigated.window="navegando = false"
        >
            <x-ui.page-skeleton x-show="navegando" x-cloak />
            <div x-show="! navegando">
                {{-- El admin SaaS supervisa los procesos de las empresas, pero no los modifica (modo auditoría/soporte). --}}
                @if (auth()->user()?->tieneRol(\App\Enums\Rol::Admin) && request()->routeIs('vacantes.*', 'seleccion.*', 'kanban.*', 'postulaciones.*', 'candidatos.*', 'entrevistas.*', 'evaluaciones.*'))
                    <p class="card mb-6 !flex-row items-center gap-2 text-14" role="note">
                        <x-ui.icon name="eye" :size="18" class="shrink-0 text-accent-700" />
                        <span><strong>Panorama de la plataforma.</strong> Aquí ves cómo avanzan las empresas hoy. Cada empresa decide sobre sus propios candidatos.</span>
                    </p>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
