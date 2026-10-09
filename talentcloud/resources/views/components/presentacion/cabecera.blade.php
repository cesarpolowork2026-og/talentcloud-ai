{{--
    Barra superior de las páginas públicas (presentación, registro, contraseña,
    invitación y errores sin sesión).
    enPresentacion: en / y /login, las anclas marcan la sección visible (necesita
    `seccion` en un x-data superior) e "Iniciar sesión" abre el diálogo de acceso.
    lema: muestra «Inteligencia que conecta talento» bajo el logo. Lo llevan las páginas del
    menú (presentación y bolsa de empleos), para que la barra sea la misma al pasar de una a
    otra; los formularios (registro, contraseña…) usan la barra compacta.
--}}
@props(['enPresentacion' => false, 'lema' => false])

@php
    $secciones = ['inicio' => 'Inicio', 'caracteristicas' => 'Características', 'beneficios' => 'Beneficios', 'seguridad' => 'Seguridad'];
@endphp

<header class="barra sticky top-0 z-40">
    <div class="mx-auto flex max-w-[1480px] items-center gap-3 px-4 py-3 sm:gap-6 sm:px-8">
        <a href="{{ route('inicio') }}#inicio" class="shrink-0 hover:no-underline" aria-label="TalentCloud AI, inicio">
            @if ($lema)
                {{-- El lema como texto: dentro de la imagen, a la altura de la barra, mediría ~6 px. --}}
                <span class="flex flex-col items-start gap-1">
                    <x-marca tamano="grande" />
                    {{-- En una línea; por debajo de 1280 px no cabe junto a los enlaces y se oculta. --}}
                    <span class="hidden whitespace-nowrap text-11 uppercase tracking-[0.18em] text-accent-200 xl:block">Inteligencia que conecta talento</span>
                </span>
            @else
                <x-marca tamano="grande" />
            @endif
        </a>

        <nav aria-label="Secciones" class="mx-auto hidden gap-6 lg:flex xl:gap-8">
            @foreach ($secciones as $ancla => $nombre)
                @if ($enPresentacion)
                    <a
                        href="{{ route('inicio') }}#{{ $ancla }}"
                        class="barra-enlace border-b-2 border-transparent py-2 text-15"
                        :aria-current="seccion === '{{ $ancla }}' ? 'true' : 'false'"
                    >{{ $nombre }}</a>
                @else
                    <a href="{{ route('inicio') }}#{{ $ancla }}" class="barra-enlace border-b-2 border-transparent py-2 text-15">{{ $nombre }}</a>
                @endif
            @endforeach
            <a
                href="{{ route('empleos.index') }}"
                class="barra-enlace border-b-2 border-transparent py-2 text-15"
                @if (request()->routeIs('empleos.*')) aria-current="page" @endif
            >Empleos</a>
            <a
                href="{{ route('planes') }}"
                class="barra-enlace border-b-2 border-transparent py-2 text-15"
                @if (request()->routeIs('planes')) aria-current="page" @endif
            >Planes</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            @auth
                {{-- Verificar correo y confirmar contraseña se ven con sesión iniciada. --}}
                <x-ui.button :href="\App\Support\Navegacion::rutaInicio(auth()->user())" class="whitespace-nowrap">Ir a mi panel</x-ui.button>
            @else
                @unless (request()->routeIs('register'))
                    <x-ui.button variant="ghost" :href="route('register')" class="hidden whitespace-nowrap sm:inline-flex lg:hidden xl:inline-flex">Crear empresa</x-ui.button>
                @endunless
                {{-- En la presentación abre el diálogo de acceso; en el resto, lleva al login. --}}
                <x-ui.button
                    :href="route('login')"
                    class="whitespace-nowrap"
                    :x-on:click.prevent="$enPresentacion ? '$dispatch(\'open-dialog\', \'acceso\')' : false"
                >
                    <span class="sm:hidden">Ingresar</span><span class="hidden sm:inline">Iniciar sesión</span>
                </x-ui.button>
            @endauth
        </div>
    </div>
</header>
