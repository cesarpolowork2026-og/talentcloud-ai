{{--
    Datos públicos de una vacante publicada: descripción, empresa y requisitos.
    La usan el detalle del postulante y la bolsa pública (/empleos). Recibe una
    vacante de VacanteRepository::publicadas(), que solo carga columnas públicas.
--}}
@props(['vacante'])

@php
    $empresa = $vacante->empresa;
    $sitioWeb = \App\Support\EnlaceExterno::seguro($empresa?->sitio_web);
@endphp

<div {{ $attributes->class('grid gap-8') }}>
    <section>
        <h2 class="mb-2 text-16">Descripción del puesto</h2>
        <p class="m-0 whitespace-pre-line text-15 text-neutral-800">{{ $vacante->descripcion ?: 'La empresa no añadió una descripción.' }}</p>
        @if ($vacante->publicada_at)
            <p class="mb-0 mt-3 text-13 text-neutral-700">Publicada el {{ \App\Support\Formato::fechaCorta($vacante->publicada_at) }}.</p>
        @endif
    </section>

    @if ($vacante->jornada || $vacante->sueldoEnTexto())
        <section>
            <h2 class="mb-2 text-16">Condiciones</h2>
            <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-15">
                @if ($vacante->jornada)
                    <dt class="text-neutral-700">Jornada</dt>
                    <dd class="m-0">{{ $vacante->jornada->etiqueta() }}</dd>
                @endif
                @if ($vacante->sueldoEnTexto())
                    <dt class="text-neutral-700">Sueldo</dt>
                    <dd class="m-0">{{ $vacante->sueldoEnTexto() }}</dd>
                @endif
            </dl>
        </section>
    @endif

    @if ($empresa && ($empresa->sector || $empresa->descripcion || $sitioWeb))
        <section>
            <h2 class="mb-2 text-16">Sobre {{ $empresa->nombre }}</h2>
            @if ($empresa->sector)
                <p class="mb-2 text-13 text-neutral-700">{{ $empresa->sector }}</p>
            @endif
            @if ($empresa->descripcion)
                <p class="m-0 whitespace-pre-line text-15 text-neutral-800">{{ $empresa->descripcion }}</p>
            @endif
            @if ($sitioWeb)
                <p class="mb-0 mt-2 text-14"><a href="{{ $sitioWeb }}" target="_blank" rel="noopener noreferrer">{{ \App\Support\EnlaceExterno::dominio($sitioWeb) }}</a></p>
            @endif
        </section>
    @endif

    <section>
        <h2 class="mb-1 text-16">Requisitos</h2>
        <p class="mb-3 text-13 text-neutral-700">Un equipo de personas revisará tu CV con estos requisitos; la decisión final es siempre de la empresa.</p>
        @if ($vacante->requisitos->isEmpty())
            <p class="text-14 text-neutral-700">Sin requisitos publicados.</p>
        @else
            <ul class="m-0 grid list-none gap-2 p-0">
                @foreach ($vacante->requisitos as $requisito)
                    <li class="flex flex-wrap items-center gap-2 border-b border-divider pb-2">
                        <span class="text-15">{{ $requisito->competencia?->nombre }}</span>
                        <x-ui.estado :valor="$requisito->tipo" />
                        @if ($requisito->anios_minimos)
                            <span class="text-13 text-neutral-700">{{ $requisito->anios_minimos }} {{ $requisito->anios_minimos === 1 ? 'año' : 'años' }} de experiencia</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
