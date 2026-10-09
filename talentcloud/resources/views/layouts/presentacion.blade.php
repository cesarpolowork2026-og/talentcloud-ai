{{--
    Página de presentación (/ y /login): qué es TalentCloud AI y sus pantallas.
    $slot es el formulario de login, en un diálogo con el fondo desenfocado que
    abre "Iniciar sesión" de la cabecera; en /login se abre al cargar (allí llevan
    el middleware auth, el cierre de sesión y "Postular" de la bolsa de empleos).
--}}
@php
    $diapositivas = [
        ['maqueta' => 'kanban', 'etiqueta' => 'Proceso de selección', 'titulo' => 'Cada vacante con su Kanban.', 'descripcion' => 'Mueve candidatos entre etapas arrastrando tarjetas; cada cambio queda registrado.'],
        ['maqueta' => 'matching', 'etiqueta' => 'Matching con IA explicable', 'titulo' => 'Coincidencias con evidencia.', 'descripcion' => 'Cada requisito muestra si el CV lo cumple y el fragmento que lo respalda.'],
        ['maqueta' => 'candidatos', 'etiqueta' => 'Candidatos y CV', 'titulo' => 'CVs analizados al subirlos.', 'descripcion' => 'La IA extrae competencias, años de experiencia y certificaciones de PDF y Word.'],
        ['maqueta' => 'entrevistas', 'etiqueta' => 'Entrevistas y evaluaciones', 'titulo' => 'Agenda y evaluación por criterios.', 'descripcion' => 'Entrevistas sin choques de horario y puntajes de 0 a 10 por criterio.'],
        ['maqueta' => 'dashboard', 'etiqueta' => 'Dashboard', 'titulo' => 'El proceso de un vistazo.', 'descripcion' => 'Vacantes activas, match promedio, entrevistas del día y actividad reciente.'],
    ];

    $modulos = [
        ['icono' => 'briefcase', 'titulo' => 'Vacantes', 'texto' => 'Publica y gestiona tus vacantes con requisitos claros.'],
        ['icono' => 'users', 'titulo' => 'Candidatos', 'texto' => 'Organiza y da seguimiento a cada postulación.'],
        ['icono' => 'sparkle', 'titulo' => 'Matching con IA', 'texto' => 'Compara CVs con los requisitos y explica el porqué.'],
        ['icono' => 'calendar-check', 'titulo' => 'Entrevistas y evaluaciones', 'texto' => 'Coordina entrevistas y evalúa por criterios.'],
    ];

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- En la raíz, el título es solo el nombre del producto. --}}
    @include('partials.head', ['title' => request()->routeIs('inicio') ? null : ($title ?? null)])
    <meta name="description" content="TalentCloud AI: gestiona vacantes, candidatos, entrevistas y evaluaciones con un matching de CVs que la IA explica. La IA sugiere; tú decides.">
    <link rel="canonical" href="{{ route('inicio') }}">
</head>
<body class="antialiased">
<div class="presentacion min-h-screen bg-bg" x-data="{ seccion: 'inicio' }">
    <a href="{{ route('login') }}" x-on:click.prevent="$dispatch('open-dialog', 'acceso')" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow-md">Ir al inicio de sesión</a>

    <x-presentacion.cabecera en-presentacion lema />

    <main>
        {{-- Hero: mensaje y pantallas de la app. --}}
        <section id="inicio" class="mx-auto max-w-[1480px] px-4 pb-16 pt-10 sm:px-8 xl:pt-14" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'inicio'">
            <div class="presentacion-hero">
                <div class="presentacion-texto">
                    <p class="mb-3 text-12 uppercase tracking-[0.12em] text-accent-700">— Reclutamiento con IA explicable</p>
                    <h1 class="mb-4 text-[clamp(32px,6vw,44px)] leading-[1.08] [text-wrap:balance] min-[1400px]:text-[40px]">Todo tu reclutamiento en un solo lugar</h1>
                    <p class="m-0 max-w-[48ch] text-17 !leading-[1.6] text-neutral-800">Gestiona vacantes, candidatos, entrevistas y evaluaciones en un flujo simple. La IA analiza cada CV y explica cada coincidencia; la decisión siempre es tuya.</p>
                </div>

                <ul class="presentacion-modulos m-0 grid list-none gap-5 p-0 sm:grid-cols-2 min-[1400px]:grid-cols-4 min-[1400px]:gap-8">
                    @foreach ($modulos as $modulo)
                        <li class="flex items-start gap-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-accent-100 text-accent-700">
                                <x-ui.icon :name="$modulo['icono']" :size="20" />
                            </span>
                            <span>
                                <span class="block text-16 font-semibold">{{ $modulo['titulo'] }}</span>
                                <span class="block text-14 text-neutral-700">{{ $modulo['texto'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <x-presentacion.carrusel class="presentacion-pantallas" :diapositivas="$diapositivas" />
            </div>
        </section>

        {{-- Características --}}
        <section id="caracteristicas" class="bg-surface py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'caracteristicas'">
            <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
                <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Características</p>
                <h2 class="mb-10 max-w-[28ch] text-[clamp(26px,3vw,34px)] leading-[1.15]">De la vacante a la contratación, sin cambiar de herramienta</h2>
                <div class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['briefcase', 'Vacantes y requisitos', 'Requisitos obligatorios y deseables, con su peso y los años mínimos de experiencia.'],
                        ['kanban', 'Kanban de selección', 'Arrastra candidatos entre etapas; la vacante cerrada queda de solo lectura.'],
                        ['file-text', 'Análisis de CV con IA', 'Sube CVs en PDF o Word: competencias, años de experiencia y certificaciones al instante.'],
                        ['sparkle', 'Matching explicable', 'Cada requisito dice si coincide y cita el fragmento del CV que lo respalda.'],
                        ['calendar-check', 'Entrevistas y evaluaciones', 'Agenda sin choques de horario y evaluación por criterios de 0 a 10.'],
                        ['chart-bar', 'Dashboard y exportación', 'Indicadores del proceso, actividad reciente y exportación a Excel de lo que ves.'],
                    ] as [$icono, $titulo, $texto])
                        <div class="flex items-start gap-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-accent-100 text-accent-700">
                                <x-ui.icon :name="$icono" :size="20" />
                            </span>
                            <div>
                                <h3 class="mb-1 text-17">{{ $titulo }}</h3>
                                <p class="m-0 text-14 text-neutral-700">{{ $texto }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Beneficios --}}
        <section id="beneficios" class="py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'beneficios'">
            <div class="mx-auto grid max-w-[1480px] gap-10 px-4 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:gap-16">
                <div>
                    <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Beneficios</p>
                    <h2 class="mb-4 text-[clamp(26px,3vw,34px)] leading-[1.15]">La IA analiza y explica. Tú decides.</h2>
                    <p class="mb-6 max-w-[46ch] text-16 text-neutral-800">Un flujo de trabajo claro para decidir mejor: menos tiempo leyendo CVs y más tiempo con las personas.</p>
                    <x-ui.card elev="md" kicker="Evidencia · Spring Boot" class="max-w-[420px] !bg-white" aria-hidden="true">
                        <p class="m-0 font-heading text-15 italic leading-[1.55]">“Diseñé APIs REST con Java y Spring Boot para una pasarela de pagos de alto tráfico.”</p>
                        <div class="flex items-center justify-between">
                            <span class="text-13 text-neutral-700">Sección Experiencia</span>
                            <x-ui.tag tone="accent">Coincide</x-ui.tag>
                        </div>
                    </x-ui.card>
                </div>
                <ul class="m-0 grid list-none content-start gap-8 p-0 sm:grid-cols-2">
                    @foreach ([
                        ['Decisiones con evidencia', 'Nada de puntajes opacos: cada coincidencia cita el texto del CV.'],
                        ['Menos tiempo filtrando', 'La IA lee y ordena los CVs; tu equipo se enfoca en las entrevistas.'],
                        ['Todo el equipo alineado', 'Administración y reclutadores trabajan sobre el mismo proceso.'],
                        ['Seguimiento en tiempo real', 'Avisos en la aplicación y por correo cuando hay novedades.'],
                    ] as [$titulo, $texto])
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check-circle" :size="24" class="text-accent-700" />
                            <div>
                                <h3 class="mb-1 text-17">{{ $titulo }}</h3>
                                <p class="m-0 text-14 text-neutral-700">{{ $texto }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Seguridad --}}
        <section id="seguridad" class="bg-surface py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'seguridad'">
            <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
                <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Seguridad</p>
                <h2 class="mb-10 max-w-[30ch] text-[clamp(26px,3vw,34px)] leading-[1.15]">Datos de candidatos protegidos desde el diseño</h2>
                <div class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['buildings', 'Datos aislados por empresa', 'Cada empresa ve solo sus vacantes, postulaciones y evaluaciones.'],
                        ['lock-key', 'CVs privados', 'Los CV no tienen una dirección pública: solo se descargan con permiso.'],
                        ['identification-card', 'Consentimiento', 'El candidato autoriza el análisis de su CV y puede eliminar su cuenta y sus datos.'],
                        ['scales', 'Decisión humana', 'La IA sugiere y explica; avanzar, descartar o contratar lo decide una persona.'],
                    ] as [$icono, $titulo, $texto])
                        <div>
                            <x-ui.icon :name="$icono" :size="28" class="mb-3 text-accent-700" />
                            <h3 class="mb-1 text-17">{{ $titulo }}</h3>
                            <p class="m-0 text-14 text-neutral-700">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Bolsa pública: las vacantes abiertas más recientes (si hay). --}}
        <x-presentacion.vacantes-abiertas />

        {{-- Llamada final --}}
        <section class="py-16">
            <div class="mx-auto grid max-w-[1480px] gap-6 px-4 sm:px-8 md:grid-cols-2">
                <x-ui.card elev="sm" class="!bg-white !p-8">
                    <h2 class="m-0 text-24">¿Reclutas para tu empresa?</h2>
                    <p class="m-0 text-15 text-neutral-800">Crea la cuenta de tu empresa, publica tu primera vacante e invita a tu equipo.</p>
                    <div><x-ui.button variant="primary" :href="route('register')">Crear empresa</x-ui.button></div>
                </x-ui.card>
                <x-ui.card elev="sm" class="!bg-white !p-8">
                    <h2 class="m-0 text-24">¿Buscas empleo?</h2>
                    <p class="m-0 text-15 text-neutral-800">Regístrate, sube tu CV una sola vez y sigue el estado de tus postulaciones.</p>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button :href="route('register.postulante')">Registrarme como postulante</x-ui.button>
                        <x-ui.button variant="ghost" :href="route('empleos.index')">Ver empleos</x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        </section>
    </main>

    <x-presentacion.pie />

    {{-- Iniciar sesión: ventana superpuesta; foco atrapado dentro, Esc o clic fuera para cerrar. --}}
    <x-ui.dialog
        name="acceso"
        title="Iniciar sesión"
        max-width="440px"
        class="dialog-backdrop-desenfocado"
        :x-init="request()->routeIs('login') ? '$nextTick(() => open = true)' : false"
    >
        {{ $slot }}
    </x-ui.dialog>
</div>
@livewireScripts
</body>
</html>
