{{--
    «Cómo funciona» de la presentación: el diferencial del producto dibujado. Un fragmento de CV
    con la frase resaltada, unido al requisito que respalda, y los tres pasos del proceso.
    El esquema es ilustrativo (datos ficticios, los mismos de la maqueta de matching) y está oculto
    a los lectores de pantalla: la frase en .sr-only dice lo mismo. Se anima una vez al llegar
    (resaltado, línea y requisito) salvo con «reducir movimiento»; sin JavaScript se ve terminado.
--}}
<section
    id="como-funciona"
    class="como-funciona py-16"
    x-data="{ animar: ! window.matchMedia('(prefers-reduced-motion: reduce)').matches, visto: false }"
    x-intersect.once.margin.-20%.0px.-20%.0px="visto = true"
    :class="{ 'animar': animar, 'visto': visto }"
    aria-labelledby="como-funciona-titulo"
>
    <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
        <x-presentacion.encabezado rotulo="Cómo funciona" titulo="Cada coincidencia, con su evidencia" id="como-funciona-titulo">
            La IA no da puntajes a ciegas: por cada requisito de la vacante señala la frase del CV que lo respalda, para que revises en segundos y decidas con fundamento.
        </x-presentacion.encabezado>

        <p class="sr-only">Ejemplo: el requisito obligatorio Spring Boot coincide en un 94 %, porque el CV dice «Diseñé APIs REST con Java y Spring Boot para una pasarela de pagos de alto tráfico» en la sección Experiencia.</p>

        {{-- Esquema: CV → línea → requisito (en móvil, de arriba abajo). --}}
        <div class="mb-12 grid items-center gap-2 lg:grid-cols-[minmax(0,1.15fr)_120px_minmax(0,1fr)] lg:gap-4" aria-hidden="true">
            <div class="card elev-md !bg-white">
                <div class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-14 font-semibold">
                        <x-ui.icon name="file-text" :size="20" class="text-accent-700" />
                        CV · Rosa Paredes
                    </span>
                    <x-ui.tag tone="outline">PDF</x-ui.tag>
                </div>
                <p class="m-0 text-11 uppercase tracking-[0.12em] text-neutral-700">Experiencia</p>
                <div class="grid gap-2">
                    <span class="block h-2 w-11/12 rounded bg-neutral-200"></span>
                    <span class="block h-2 w-3/4 rounded bg-neutral-200"></span>
                </div>
                <p class="m-0 font-heading text-17 leading-[1.6]">
                    «<span class="resaltado">Diseñé APIs REST con Java y Spring Boot para una pasarela de pagos de alto tráfico.</span>»
                </p>
                <div class="grid gap-2">
                    <span class="block h-2 w-5/6 rounded bg-neutral-200"></span>
                    <span class="block h-2 w-2/3 rounded bg-neutral-200"></span>
                </div>
            </div>

            <x-ilustracion nombre="conector-horizontal" class="mx-auto hidden w-full lg:block" />
            <x-ilustracion nombre="conector-vertical" class="mx-auto w-10 lg:hidden" />

            <div class="card elev-md requisito-aparece !bg-white">
                <p class="m-0 text-11 uppercase tracking-[0.12em] text-accent-700">Requisito obligatorio · Desarrollador Backend</p>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-20 font-semibold">Spring Boot</span>
                    <x-ui.tag tone="accent">Coincide</x-ui.tag>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.progress :value="94" class="flex-1" />
                    <span class="text-14 font-semibold">94 %</span>
                </div>
                <p class="m-0 flex items-center gap-2 text-13 text-neutral-700">
                    <x-ui.icon name="quotes" :size="16" class="text-accent-700" />
                    Evidencia: sección Experiencia del CV
                </p>
            </div>
        </div>

        {{-- Los tres pasos. --}}
        <ol class="m-0 grid list-none gap-8 p-0 md:grid-cols-3">
            @foreach ([
                ['briefcase', 'Publica tu vacante', 'Define requisitos obligatorios y deseables, con los años mínimos de experiencia.'],
                ['sparkle', 'La IA lee y explica', 'Analiza cada CV y, por cada requisito, cita el fragmento que lo respalda o avisa que no lo encontró.'],
                ['scales', 'Tú decides', 'Avanzar, descartar o contratar lo decide siempre una persona de tu equipo.'],
            ] as $indice => [$icono, $titulo, $texto])
                <li class="flex items-start gap-4">
                    <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-full bg-accent-100 text-accent-700">
                        <x-ui.icon :name="$icono" :size="22" />
                        <span class="absolute -right-1 -top-1 grid h-6 w-6 place-items-center rounded-full bg-accent-700 text-12 font-semibold text-white" aria-hidden="true">{{ $indice + 1 }}</span>
                    </span>
                    <div>
                        <h3 class="mb-1 text-17">{{ $titulo }}</h3>
                        <p class="m-0 text-14 text-neutral-700">{{ $texto }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
