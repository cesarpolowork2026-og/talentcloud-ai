{{--
    Pantallas de acceso fuera de la presentación (crear empresa, registro de
    postulante, contraseña, verificación e invitación): la misma cabecera, pie y
    composición que el hero de layouts.presentacion. $slot es el formulario.
    El mensaje de la izquierda se dirige al postulante en su registro y a la
    empresa en el resto (texto y tarjeta de coincidencias de Login.dc.html).
--}}
@php($paraPostulante = request()->routeIs('register.postulante'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="antialiased">
<div class="flex min-h-screen flex-col bg-bg">
    <x-presentacion.cabecera />

    <main class="mx-auto grid w-full max-w-[1200px] flex-1 items-start gap-10 px-4 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-20 lg:py-14">
        {{-- Mensaje (en móvil, debajo del formulario). --}}
        <div class="order-last grid min-w-0 content-start gap-8 lg:order-first">
            <div class="max-w-[600px]">
                <p class="mb-3 text-12 uppercase tracking-[0.12em] text-accent-700">— {{ $paraPostulante ? 'Para postulantes' : 'Reclutamiento con IA explicable' }}</p>
                <h1 class="mb-4 text-[clamp(32px,4vw,44px)] leading-[1.08] [text-wrap:balance]">
                    {{ $paraPostulante ? 'Postula con tu CV y sigue cada proceso.' : 'Encuentra el talento que necesitas.' }}
                </h1>
                <p class="m-0 font-heading text-18 italic leading-[1.5] text-neutral-800 [text-wrap:pretty]">
                    {{ $paraPostulante ? 'Sube tu CV una sola vez y consulta en qué etapa está cada postulación.' : 'La IA analiza, relaciona y explica. Tú tomas la decisión.' }}
                </p>
            </div>

            {{-- Ejemplo ilustrativo (contenido fijo, no son datos reales). --}}
            @if ($paraPostulante)
                {{-- Su pantalla en el teléfono, con los estados reales de EstadoDeMiPostulacion. En móvil se omite:
                     va debajo del formulario y alargaría la página sin aportar al registro. --}}
                <x-presentacion.telefono class="hidden !mx-0 lg:block">
                    <x-presentacion.maqueta-mis-postulaciones-movil />
                </x-presentacion.telefono>
            @else
                <x-ui.card kicker="Vacante · Desarrollador Backend" elev="md" class="max-w-[440px] !bg-white" aria-hidden="true">
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
            @endif

            <ul class="m-0 grid max-w-[46ch] list-none gap-3 p-0">
                @foreach ($paraPostulante
                    ? ['La IA lee tu CV y lo relaciona con cada vacante.', 'Tus datos solo los ven las empresas a las que postulas.', 'Puedes eliminar tu cuenta y tus datos cuando quieras.']
                    : ['Cada coincidencia cita el fragmento del CV que la respalda.', 'Los datos de tu empresa están aislados de las demás.', 'La decisión final siempre es de tu equipo.'] as $punto)
                    <li class="flex items-start gap-3 text-15 text-neutral-800">
                        <x-ui.icon name="check-circle" :size="20" class="mt-0.5 text-accent-700" />{{ $punto }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Formulario --}}
        <div class="w-full lg:sticky lg:top-24">
            <x-ui.card elev="lg" class="!bg-white !p-6 sm:!p-8">
                {{ $slot }}
            </x-ui.card>
        </div>
    </main>

    <x-presentacion.pie />
</div>
@livewireScripts
</body>
</html>
