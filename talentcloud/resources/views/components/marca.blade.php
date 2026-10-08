{{--
    Logo de TalentCloud AI, igual en la presentación, las pantallas de acceso, la barra de la
    app, las maquetas y los errores. Archivos SVG en public/marca (origen y cómo se generan:
    resources/brand/README.md); se sirven como <img> para que el navegador los guarde en caché
    y no viajen en cada respuesta de Livewire.

    fondo:  oscuro (barra azul petróleo, por defecto) | claro (pie y fondos claros).
    tamano: grande (cabecera pública) | mediano (barra de la app) | pequeno (maquetas, pie).
    El lema de la página de inicio va como texto junto al logo (x-presentacion.cabecera): dentro de
    la imagen, a la altura de la barra, no se leería.
--}}
@props(['fondo' => 'oscuro', 'tamano' => 'mediano'])

@php
    $archivo = $fondo === 'claro' ? 'talentcloud-ai.svg' : 'talentcloud-ai-oscuro.svg';
    $ruta = public_path('marca/'.$archivo);
    // La versión en la URL hace que un logo nuevo no quede oculto tras la caché del navegador.
    $src = asset('marca/'.$archivo).(is_file($ruta) ? '?v='.filemtime($ruta) : '');

    $alto = match ($tamano) {
        'grande' => 'h-[34px] sm:h-[40px]',
        'pequeno' => 'h-[20px]',
        default => 'h-[32px]',
    };
@endphp

<img src="{{ $src }}" alt="TalentCloud AI" {{ $attributes->class(['block w-auto max-w-full', $alto]) }}>
