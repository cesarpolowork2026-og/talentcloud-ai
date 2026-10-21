{{--
    Tarjeta de una vacante publicada (empresa, puesto, lugar y requisitos
    obligatorios). La usan "Vacantes" del postulante, la bolsa pública y la
    presentación. $slot: la acción de la tarjeta ("Ver y postular", "Ya postulaste").
    titulo: nivel del encabezado según la página (h2 en un listado, h3 en una sección).
--}}
@props(['vacante', 'href', 'titulo' => 'h2'])

@php($obligatorios = $vacante->requisitos->where('tipo', \App\Enums\TipoRequisito::Obligatorio))

<x-ui.card {{ $attributes }}>
    <div class="card-kicker">{{ $vacante->empresa?->nombre }}</div>
    <{{ $titulo === 'h3' ? 'h3' : 'h2' }} class="card-title m-0">
        <a href="{{ $href }}" wire:navigate class="text-ink hover:text-accent-700">{{ $vacante->titulo }}</a>
    </{{ $titulo === 'h3' ? 'h3' : 'h2' }}>
    <p class="card-body">
        {{ $vacante->lugarYModalidad() }}
        @if ($vacante->publicada_at)
            · Publicada el {{ \App\Support\Formato::fechaCorta($vacante->publicada_at) }}
        @endif
    </p>
    {{-- Lo que más pesa al comparar ofertas: el sueldo y la jornada, si la empresa los indicó. --}}
    @if ($vacante->sueldoEnTexto() || $vacante->jornada)
        <p class="m-0 text-14 font-semibold text-neutral-800">{{ collect([$vacante->sueldoEnTexto(), $vacante->jornada?->etiqueta()])->filter()->implode(' · ') }}</p>
    @endif

    @if ($obligatorios->isNotEmpty())
        <div class="card-meta flex-wrap" role="group" aria-label="Requisitos obligatorios">
            @foreach ($obligatorios->take(4) as $requisito)
                <x-ui.tag tone="outline">{{ $requisito->competencia?->nombre }}</x-ui.tag>
            @endforeach
        </div>
    @endif

    <div class="mt-1">{{ $slot }}</div>
</x-ui.card>
