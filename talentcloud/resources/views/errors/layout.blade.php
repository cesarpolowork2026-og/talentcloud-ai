{{--
    Páginas de error con el diseño del prototipo ("Acceso restringido", "Página no encontrada").
    Con sesión se muestra la barra de navegación completa; sin ella (p. ej. una 404 de una
    ruta inexistente, que no pasa por el middleware de sesión) solo la marca.
--}}
@php($usuario = ($conNavegacion ?? true) ? auth()->user() : null)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $titulo])
</head>
<body class="antialiased">
    <div class="flex min-h-screen flex-col bg-bg">
        @if ($usuario)
            <livewire:layout.navigation />
        @else
            {{-- Sin sesión: la cabecera de las páginas públicas. --}}
            <x-presentacion.cabecera />
        @endif

        <main class="mx-auto w-full max-w-page flex-1 px-4 py-6 sm:p-8">
            <div class="mx-auto my-8 max-w-[460px] text-center">
                @if (isset($ilustracion))
                    <x-ilustracion :nombre="$ilustracion" class="mx-auto w-[260px] max-w-full" />
                @else
                    <x-ui.icon :name="$icono" :size="48" @class(['mx-auto', $colorIcono]) />
                @endif
                <h1 class="mb-2 mt-4 text-24">{{ $titulo }}</h1>
                <p class="text-15 text-neutral-700">{{ $mensaje }}</p>
                @if (! ($conNavegacion ?? true))
                    <x-ui.button variant="primary" class="mt-4" href="/">Volver al inicio</x-ui.button>
                @else
                    {{-- Sin sesión, una página que no existe lleva al inicio; un 401 o un 419 (sesión caducada), a iniciar sesión. --}}
                    @php($alInicio = ! $usuario && ($invitadoAlInicio ?? false))
                    <x-ui.button variant="primary" class="mt-4" :href="$alInicio ? route('inicio') : \App\Support\Navegacion::rutaInicio($usuario)">
                        {{ $usuario ? 'Volver a mi panel' : ($alInicio ? 'Volver al inicio' : 'Ir a iniciar sesión') }}
                    </x-ui.button>
                @endif
            </div>
        </main>

        @unless ($usuario)
            <x-presentacion.pie />
        @endunless
    </div>
    @livewireScripts
</body>
</html>
