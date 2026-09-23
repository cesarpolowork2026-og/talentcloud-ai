<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
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
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
