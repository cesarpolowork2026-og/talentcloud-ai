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
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'TalentCloud AI') }}">
    <meta property="og:locale" content="es_PE">
    <meta property="og:title" content="{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'TalentCloud AI') }}">
    <meta property="og:description" content="{{ $compartir }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('marca/vista-previa.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="TalentCloud AI: reclutamiento con IA que explica cada coincidencia.">
    <meta name="twitter:card" content="summary_large_image">
@endisset

{{-- La fuente Source Serif 4 va empaquetada en app.css (@fontsource). --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
