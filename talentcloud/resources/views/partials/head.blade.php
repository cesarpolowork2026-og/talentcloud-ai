<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'TalentCloud AI') }}</title>

{{-- Iconos de la marca: SVG para los navegadores actuales, .ico de respaldo y el de la pantalla de inicio de iOS. --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#004961">

{{--
    Vista previa al compartir el enlace (WhatsApp, LinkedIn, Facebook, X): solo en las páginas
    públicas, que pasan $compartir con su descripción. Imagen: public/marca/vista-previa.png
    (1200 × 630, generada desde resources/brand/herramientas/vista-previa.html).
--}}
@isset($compartir)
    {{-- Dirección canónica: la de la página sin filtros ni parámetros; solo se conserva el número de página. Se omite
         donde la página no debe indexarse (el inicio de sesión repite la portada). --}}
    @unless ($sinCanonical ?? false)
        @php($paginaActual = (int) request()->query('page'))
        <link rel="canonical" href="{{ url()->current() }}{{ $paginaActual > 1 ? '?page='.$paginaActual : '' }}">
    @endunless
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'TalentCloud AI') }}">
    <meta property="og:locale" content="es_PE">
    <meta property="og:title" content="{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'TalentCloud AI') }}">
    <meta property="og:description" content="{{ $compartir }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('marca/vista-previa.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="TalentCloud AI: vacantes, candidatos y entrevistas en un solo lugar.">
    <meta name="twitter:card" content="summary_large_image">
@endisset

{{-- La fuente Source Serif 4 va empaquetada en app.css (@fontsource). Se precargan los dos pesos del texto
     (normal y seminegrita) para que no se descubran recién al leer el CSS. --}}
@foreach (['400', '600'] as $peso)
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource/source-serif-4/files/source-serif-4-latin-'.$peso.'-normal.woff2') }}">
@endforeach
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
