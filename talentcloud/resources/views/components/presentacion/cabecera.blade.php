{{--
    Barra superior de las páginas públicas (presentación, registro, contraseña,
    invitación y errores sin sesión).
    enPresentacion: en / y /login, las anclas marcan la sección visible (necesita
    `seccion` en un x-data superior) e "Iniciar sesión" lleva a la tarjeta de acceso.
--}}
@props(['enPresentacion' => false])

@php
    $secciones = ['inicio' => 'Inicio', 'caracteristicas' => 'Características', 'beneficios' => 'Beneficios', 'seguridad' => 'Seguridad'];
@endphp

<header class="sticky top-0 z-40 border-b border-divider bg-bg">
    <div class="mx-auto flex max-w-[1480px] items-center gap-3 px-4 py-3 sm:gap-6 sm:px-8">
        <a href="{{ route('inicio') }}#inicio" class="hover:no-underline" aria-label="TalentCloud AI, inicio">
            <x-marca tamano="grande" />
        </a>

        <nav aria-label="Secciones" class="mx-auto hidden gap-6 lg:flex xl:gap-8">
            @foreach ($secciones as $ancla => $nombre)
                @if ($enPresentacion)
                    <a
                        href="{{ route('inicio') }}#{{ $ancla }}"
                        class="border-b-2 py-2 text-15 hover:no-underline"
                        :class="seccion === '{{ $ancla }}' ? 'border-accent-700 text-accent-700' : 'border-transparent text-ink hover:text-accent-700'"
                        :aria-current="seccion === '{{ $ancla }}' ? 'true' : 'false'"
                    >{{ $nombre }}</a>
                @else
                    <a href="{{ route('inicio') }}#{{ $ancla }}" class="border-b-2 border-transparent py-2 text-15 text-ink hover:text-accent-700 hover:no-underline">{{ $nombre }}</a>
                @endif
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            @auth
                {{-- Verificar correo y confirmar contraseña se ven con sesión iniciada. --}}
                <x-ui.button :href="\App\Support\Navegacion::rutaInicio(auth()->user())" class="whitespace-nowrap">Ir a mi panel</x-ui.button>
            @else
                @unless (request()->routeIs('register'))
                    <x-ui.button variant="ghost" :href="route('register')" class="hidden whitespace-nowrap sm:inline-flex lg:hidden xl:inline-flex">Crear empresa</x-ui.button>
                @endunless
                {{-- En la presentación lleva a la tarjeta de acceso; en el resto, al login. La flecha va en un
                     span: .btn svg { display: block } ganaría a .hidden sobre el propio svg. --}}
                <x-ui.button
                    :href="$enPresentacion ? '#acceso' : route('login')"
                    class="whitespace-nowrap"
                    :x-on:click="$enPresentacion ? '$nextTick(() => document.getElementById(\'email\')?.focus({ preventScroll: true }))' : false"
                >
                    <span class="sm:hidden">Ingresar</span><span class="hidden sm:inline">Iniciar sesión</span>
                    <span class="hidden sm:inline"><x-ui.icon name="arrow-right" :size="14" /></span>
                </x-ui.button>
            @endauth
        </div>
    </div>
</header>
