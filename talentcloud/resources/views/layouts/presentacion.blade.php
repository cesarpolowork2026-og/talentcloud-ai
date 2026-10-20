{{--
    Página de presentación (/ y /login): qué es TalentCloud AI y sus pantallas.
    $slot es el formulario de login, en un diálogo con el fondo desenfocado que
    abre "Iniciar sesión" de la cabecera; en /login se abre al cargar (allí llevan
    el middleware auth, el cierre de sesión y "Postular" de la bolsa de empleos).
--}}
@php
    $diapositivas = [
        ['maqueta' => 'kanban', 'etiqueta' => 'Proceso de selección', 'titulo' => 'Cada vacante con su tablero.', 'descripcion' => 'Mueve candidatos entre etapas arrastrando tarjetas; cada cambio queda registrado.'],
        ['maqueta' => 'candidatos', 'etiqueta' => 'Candidatos y CV', 'titulo' => 'Cada CV, en un solo lugar.', 'descripcion' => 'Recibe CV en PDF y Word, guárdalos de forma privada y anota las competencias de cada candidato.'],
        ['maqueta' => 'entrevistas', 'etiqueta' => 'Entrevistas y evaluaciones', 'titulo' => 'Agenda y evaluación por criterios.', 'descripcion' => 'Entrevistas sin choques de horario y puntajes de 0 a 10 por criterio.'],
        ['maqueta' => 'embudo', 'etiqueta' => 'Embudo de selección', 'titulo' => 'Dónde se queda cada postulación.', 'descripcion' => 'El embudo muestra cuántas llegan a cada etapa y qué parte pasa a la siguiente.'],
        ['maqueta' => 'dashboard', 'etiqueta' => 'Dashboard', 'titulo' => 'El proceso de un vistazo.', 'descripcion' => 'Vacantes activas, entrevistas del día, tendencia de postulaciones y actividad reciente.'],
    ];

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- En la raíz, el título dice qué es el producto (y para quién); /login lleva el suyo. --}}
    @php($descripcionDelSitio = 'TalentCloud AI: gestiona vacantes, candidatos, entrevistas y evaluaciones en un solo flujo, con los datos de cada empresa separados. Tú decides.')
    @include('partials.head', ['title' => request()->routeIs('inicio') ? 'Software de reclutamiento en Perú' : ($title ?? null), 'compartir' => $descripcionDelSitio, 'sinCanonical' => request()->routeIs('login')])
    <meta name="description" content="{{ $descripcionDelSitio }}">
    @if (request()->routeIs('login'))
        {{-- /login es la presentación con el diálogo abierto: no se indexa aparte de la portada. --}}
        <meta name="robots" content="noindex, follow">
    @endif
    @if (request()->routeIs('inicio'))
        <script type="application/ld+json">{!! \App\Support\DatosEstructurados::json(\App\Support\DatosEstructurados::organizacionYSitio()) !!}</script>
    @endif
</head>
<body class="antialiased">
<div class="presentacion min-h-screen bg-bg" x-data="{ seccion: 'inicio' }">
    <a href="{{ route('login') }}" x-on:click.prevent="$dispatch('open-dialog', 'acceso')" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow-md">Ir al inicio de sesión</a>

    <x-presentacion.cabecera en-presentacion lema />

    <main>
        {{-- Hero: mensaje, acciones y pantallas de la app. --}}
        <section id="inicio" class="mx-auto max-w-[1480px] px-4 pb-16 pt-10 sm:px-8 xl:pt-14" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'inicio'">
            <div class="presentacion-hero">
                <div class="presentacion-texto">
                    <p class="mb-3 text-12 uppercase tracking-[0.12em] text-accent-700">— Reclutamiento ordenado</p>
                    <h1 class="mb-4 text-[clamp(32px,6vw,44px)] leading-[1.08] [text-wrap:balance] min-[1400px]:text-[40px]">Todo tu reclutamiento en un solo lugar</h1>
                    <p class="m-0 max-w-[48ch] text-17 !leading-[1.6] text-neutral-800">Gestiona vacantes, candidatos, entrevistas y evaluaciones en un flujo simple, con un tablero por vacante y el historial de cada postulación. La decisión siempre es tuya.</p>

                    {{-- Qué hacer: la empresa empieza gratis; el postulante va a la bolsa de empleos. --}}
                    <div class="mt-7 flex flex-wrap gap-3">
                        <x-ui.button variant="primary" :href="route('register')" class="!px-5 !py-3 text-15">Crear empresa gratis</x-ui.button>
                        <x-ui.button :href="route('empleos.index')" class="!px-5 !py-3 text-15">Buscar empleo</x-ui.button>
                    </div>
                    <p class="mb-0 mt-3 text-13 text-neutral-700">Gratis con una vacante · {{ \App\Enums\PlanEmpresa::DIAS_DE_PRUEBA }} días del plan Pro al empezar · Sin tarjeta · <a href="{{ route('planes') }}" class="whitespace-nowrap underline">Ver planes</a> · <a href="#como-funciona" class="whitespace-nowrap underline">Cómo funciona</a></p>
                </div>

                <x-presentacion.carrusel class="presentacion-pantallas" :diapositivas="$diapositivas" />
            </div>
        </section>

        {{-- Cómo funciona: los tres pasos del proceso. --}}
        <x-presentacion.como-funciona />

        {{-- Características: lo que hace el producto, junto a la pantalla que lo muestra (maquetas ilustrativas). --}}
        <section id="caracteristicas" class="bg-surface py-16" x-intersect.margin.-45%.0px.-45%.0px="seccion = 'caracteristicas'">
            <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
                <x-presentacion.encabezado rotulo="Características" titulo="De la vacante a la contratación, sin cambiar de herramienta" class="!mb-12">
                    Requisitos, CV, tablero de selección, entrevistas, evaluaciones e indicadores en un mismo lugar, con los datos de cada empresa separados de los de las demás.
                </x-presentacion.encabezado>

                <div class="grid gap-16 lg:gap-20">
                    @foreach ([
                        [
                            'maqueta' => 'candidatos',
                            'titulo' => 'Candidatos y CV, en un solo lugar',
                            'texto' => 'Recibe los CV en PDF o Word, guárdalos de forma privada y registra a quienes llegan por otras vías, sin duplicar personas entre vacantes.',
                            'puntos' => [
                                'Cada candidato tiene su ficha, su CV y las competencias que anota tu equipo.',
                                'Los CV no tienen una dirección pública: solo se descargan con permiso.',
                                'Notas internas por postulación que nunca ve el candidato.',
                            ],
                        ],
                        [
                            'maqueta' => 'entrevistas',
                            'titulo' => 'Del primer CV a la contratación',
                            'texto' => 'Cada vacante tiene su tablero: mueve candidatos entre etapas, agenda entrevistas y evalúa con criterios claros.',
                            'puntos' => [
                                'Requisitos obligatorios y deseables, con su importancia y los años mínimos.',
                                'Entrevistas sin choques de horario y avisos al candidato.',
                                'Evaluaciones de 0 a 10 por criterio y el historial completo de cada etapa.',
                            ],
                        ],
                        [
                            'maqueta' => 'embudo',
                            'titulo' => 'El proceso de un vistazo',
                            'texto' => 'Vacantes activas, entrevistas del día, tendencia de postulaciones y el embudo de selección, en una sola pantalla.',
                            'puntos' => [
                                'Embudo de 7, 30 o 90 días, por vacante, con la conversión entre etapas.',
                                'Actividad reciente y avisos en la aplicación y por correo.',
                                'Exportación a Excel de lo que ves en cada listado.',
                            ],
                        ],
                    ] as $indice => $fila)
                        <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
                            <div @class(['lg:order-last' => $indice % 2 === 1])>
                                <h3 class="mb-3 text-[clamp(22px,2.4vw,28px)] leading-[1.2]">{{ $fila['titulo'] }}</h3>
                                <p class="mb-5 max-w-[50ch] text-16 text-neutral-800">{{ $fila['texto'] }}</p>
                                <ul class="m-0 grid list-none gap-3 p-0">
                                    @foreach ($fila['puntos'] as $punto)
                                        <li class="flex items-start gap-3 text-15 text-neutral-800">
                                            <x-ui.icon name="check-circle" :size="20" class="mt-0.5 shrink-0 text-accent-700" />
                                            <span>{{ $punto }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            {{-- En móvil, a ~40 % de escala, no se leería: ahí las enseña el carrusel. --}}
                            <x-presentacion.pantalla :maqueta="$fila['maqueta']" class="hidden md:block" />
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
                    <h2 class="mb-4 text-[clamp(26px,3vw,34px)] leading-[1.15]">Un proceso ordenado. Tú decides.</h2>
                    {{-- El ejemplo de evidencia que había aquí lo muestra ahora «Cómo funciona». --}}
                    <p class="m-0 max-w-[46ch] text-16 text-neutral-800">Un flujo de trabajo claro para decidir mejor: menos tiempo leyendo CVs y más tiempo con las personas.</p>
                </div>
                <ul class="m-0 grid list-none content-start gap-8 p-0 sm:grid-cols-2">
                    @foreach ([
                        ['Decisiones con información', 'Cada postulación guarda su CV, sus notas, sus entrevistas y sus evaluaciones.'],
                        ['Menos tiempo en planillas', 'El tablero y el embudo reemplazan las hojas de cálculo; tu equipo se enfoca en las entrevistas.'],
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
                <x-presentacion.encabezado rotulo="Seguridad" titulo="Datos de candidatos protegidos desde el diseño">
                    Cada empresa ve solo lo suyo, los CV no tienen una dirección pública y cada candidato decide sobre sus datos. <a href="{{ route('privacidad') }}">Lee la política de privacidad</a>.
                </x-presentacion.encabezado>
                <div class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['buildings', 'Datos aislados por empresa', 'Cada empresa ve solo sus vacantes, postulaciones y evaluaciones.'],
                        ['lock-key', 'CVs privados', 'Los CV no tienen una dirección pública: solo se descargan con permiso.'],
                        ['identification-card', 'Consentimiento', 'El candidato autoriza el tratamiento de su CV y puede eliminar su cuenta y sus datos.'],
                        ['scales', 'Decisión humana', 'Avanzar, descartar o contratar lo decide siempre una persona del equipo.'],
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

        {{-- El otro lado del producto: el postulante, en su teléfono. --}}
        <x-presentacion.para-postulantes />

        {{-- Bolsa pública: las vacantes abiertas más recientes (si hay). --}}
        <x-presentacion.vacantes-abiertas />

        {{-- Llamada final --}}
        <section class="py-16">
            <div class="mx-auto grid max-w-[1480px] gap-6 px-4 sm:px-8 md:grid-cols-2">
                <x-ui.card elev="sm" class="!bg-white !p-8">
                    <h2 class="m-0 text-24">¿Reclutas para tu empresa?</h2>
                    <p class="m-0 text-15 text-neutral-800">Crea la cuenta de tu empresa, publica tu primera vacante e invita a tu equipo. Empiezas gratis, con {{ \App\Enums\PlanEmpresa::DIAS_DE_PRUEBA }} días del plan Pro.</p>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button variant="primary" :href="route('register')">Crear empresa gratis</x-ui.button>
                        <x-ui.button variant="ghost" :href="route('planes')">Ver planes y precios</x-ui.button>
                    </div>
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
