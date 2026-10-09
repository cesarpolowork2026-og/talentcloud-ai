{{--
    Pie de las páginas públicas: la marca y los enlaces por tipo de visitante, más los
    documentos legales (la política de privacidad debe estar a mano: Ley 29733).
--}}
@php
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

<footer class="border-t border-divider">
    <div class="mx-auto grid max-w-[1480px] gap-8 px-4 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]">
        <div class="grid content-start gap-3">
            <a href="{{ route('inicio') }}#inicio" class="self-start hover:no-underline" aria-label="TalentCloud AI, ir al inicio">
                <x-marca fondo="claro" />
            </a>
            <p class="m-0 max-w-[32ch] text-14 text-neutral-700">Reclutamiento con IA explicable: la IA sugiere y explica; tú decides.</p>
        </div>

        <nav aria-label="Pie de página" class="grid grid-cols-2 gap-8 md:grid-cols-4">
            @foreach ($columnas as $titulo => $enlaces)
                <div>
                    <h2 class="mb-3 text-12 font-semibold uppercase tracking-[0.12em] text-neutral-700">{{ $titulo }}</h2>
                    <ul class="m-0 grid list-none gap-2 p-0 text-14">
                        @foreach ($enlaces as $texto => $url)
                            <li><a href="{{ $url }}">{{ $texto }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </div>

    <div class="border-t border-divider">
        <div class="mx-auto max-w-[1480px] px-4 py-4 sm:px-8">
            <p class="m-0 text-13 text-neutral-700">© {{ now()->year }} {{ config('talentcloud.legal.responsable') }}. Precios en soles; no incluyen IGV.</p>
        </div>
    </div>
</footer>
