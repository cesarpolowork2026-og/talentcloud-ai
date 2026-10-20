{{--
    Pie de las páginas públicas, en el mismo azul petróleo que la barra superior (.barra):
    la marca, los enlaces por tipo de visitante, la ayuda (correo de soporte y Libro de
    Reclamaciones, obligatorio en Perú) y los documentos legales (Ley 29733).
--}}
@php
    $soporte = config('talentcloud.soporte.correo');

    $columnas = [
        'Producto' => [
            'Características' => route('inicio').'#caracteristicas',
            'Beneficios' => route('inicio').'#beneficios',
            'Seguridad' => route('inicio').'#seguridad',
            'Planes y precios' => route('planes'),
        ],
        'Para empresas' => [
            'Crear empresa gratis' => route('register'),
            'Iniciar sesión' => route('login'),
        ],
        'Para postulantes' => [
            'Ver empleos' => route('empleos.index'),
            'Registrarme como postulante' => route('register.postulante'),
        ],
        'Legal' => [
            'Política de privacidad' => route('privacidad'),
            'Términos de uso' => route('terminos'),
        ],
    ];
@endphp

<footer class="barra">
    <div class="mx-auto grid max-w-[1480px] gap-8 px-4 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]">
        <div class="grid content-start gap-4">
            <a href="{{ route('inicio') }}#inicio" class="self-start hover:no-underline" aria-label="TalentCloud AI, ir al inicio">
                <x-marca />
            </a>
            <p class="m-0 max-w-[32ch] text-14 text-accent-100">Reclutamiento ordenado: vacantes, candidatos y entrevistas en un solo lugar; tú decides.</p>

            {{-- Ayuda: soporte y Libro de Reclamaciones, a la vista en todas las páginas públicas. --}}
            <div class="grid gap-3" role="group" aria-labelledby="pie-ayuda">
                <h2 id="pie-ayuda" class="m-0 text-12 font-semibold uppercase tracking-[0.12em] text-accent-200">Ayuda</h2>
                <a href="mailto:{{ $soporte }}" class="barra-enlace flex items-center gap-2 text-14 break-all">
                    <x-ui.icon name="envelope-simple" :size="18" class="shrink-0" />
                    <span><span class="sr-only">Correo de soporte: </span>{{ $soporte }}</span>
                </a>
                <a href="{{ route('reclamaciones.crear') }}" class="barra-libro justify-self-start" @if (request()->routeIs('reclamaciones.crear')) aria-current="page" @endif>
                    <x-ui.icon name="book-open-text" :size="22" class="shrink-0" />
                    <span>Libro de Reclamaciones</span>
                </a>
            </div>
        </div>

        <nav aria-label="Pie de página" class="grid grid-cols-2 gap-8 md:grid-cols-4">
            @foreach ($columnas as $titulo => $enlaces)
                <div>
                    <h2 class="mb-3 text-12 font-semibold uppercase tracking-[0.12em] text-accent-200">{{ $titulo }}</h2>
                    <ul class="m-0 grid list-none gap-2 p-0 text-14">
                        @foreach ($enlaces as $texto => $url)
                            <li><a href="{{ $url }}" class="barra-enlace">{{ $texto }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </div>

    <div class="barra-divisor border-t">
        <div class="mx-auto max-w-[1480px] px-4 py-4 sm:px-8">
            <p class="m-0 text-13 text-accent-100">© {{ now()->year }} {{ config('talentcloud.legal.responsable') }}. Precios en soles; no incluyen IGV.</p>
        </div>
    </div>
</footer>
