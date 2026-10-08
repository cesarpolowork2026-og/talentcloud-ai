{{--
    Logo de TalentCloud AI, igual en la presentación, las pantallas de acceso, la barra de la
    app, las maquetas y los errores. Archivos SVG en public/marca (origen y cómo se generan:
    resources/brand/README.md); se sirven como <img> para que el navegador los guarde en caché
    y no viajen en cada respuesta de Livewire.

    fondo:  oscuro (barra azul petróleo, por defecto) | claro (pie y fondos claros).
    tamano: grande (cabecera pública) | mediano (barra de la app) | pequeno (maquetas, pie).
    lema:   añade "Inteligencia que conecta talento" (solo la página de inicio).
--}}
@props(['fondo' => 'oscuro', 'tamano' => 'mediano', 'lema' => false])

@php
    $archivo = 'talentcloud-ai'.($lema ? '-lema' : '').($fondo === 'claro' ? '' : '-oscuro').'.svg';
    $ruta = public_path('marca/'.$archivo);
    // La versión en la URL hace que un logo nuevo no quede oculto tras la caché del navegador.
    $src = asset('marca/'.$archivo).(is_file($ruta) ? '?v='.filemtime($ruta) : '');

    $alto = match ($tamano) {
        'grande' => $lema ? 'h-[38px] sm:h-[48px]' : 'h-[34px] sm:h-[40px]',
        'pequeno' => 'h-[20px]',
        default => 'h-[32px]',
    };
@endphp

<img src="{{ $src }}" alt="TalentCloud AI" {{ $attributes->class(['block w-auto max-w-full', $alto]) }}>
