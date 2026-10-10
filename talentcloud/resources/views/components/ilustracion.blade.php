{{--
    Ilustración propia en SVG (formas en components/ilustracion/formas/{nombre}). Estilo fijo:
    clases .ilu-* de components.css (trazo de 2 px y rellenos de la marca).
    descripcion: null = decorativa (aria-hidden; el texto de al lado dice lo mismo);
                 con texto = comunica algo (role="img" + <title>).
--}}
@props(['nombre', 'descripcion' => null])

@php
    // Caja de cada ilustración (viewBox). Una ilustración nueva se registra aquí.
    $cajas = [
        'conector-horizontal' => '0 0 120 40',
        'conector-vertical' => '0 0 40 72',
        'primera-vacante' => '0 0 240 160',
        'sin-candidatos' => '0 0 240 160',
        'agenda-libre' => '0 0 240 160',
        'sin-postulaciones' => '0 0 240 160',
        'pagina-no-encontrada' => '0 0 240 160',
        'acceso-restringido' => '0 0 240 160',
        'hoja-enviada' => '0 0 240 160',
    ];
    $caja = $cajas[$nombre] ?? throw new InvalidArgumentException("Ilustración desconocida: {$nombre}");
    $tituloId = 'ilu-'.$nombre.'-'.\Illuminate\Support\Str::random(6);
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="{{ $caja }}"
    @if ($descripcion) role="img" aria-labelledby="{{ $tituloId }}" @else aria-hidden="true" focusable="false" @endif
    {{ $attributes->class('block h-auto max-w-full') }}
>
    @if ($descripcion)
        <title id="{{ $tituloId }}">{{ $descripcion }}</title>
    @endif
    @include('components.ilustracion.formas.'.$nombre)
</svg>
