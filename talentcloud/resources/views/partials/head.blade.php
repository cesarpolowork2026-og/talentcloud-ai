<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'TalentCloud AI') }}</title>

{{-- Iconos de la marca: SVG para los navegadores actuales, .ico de respaldo y el de la pantalla de inicio de iOS. --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#004961">

{{-- La fuente Source Serif 4 va empaquetada en app.css (@fontsource). --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
