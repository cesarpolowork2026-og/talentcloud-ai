<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'TalentCloud AI') }}</title>

{{-- La fuente Source Serif 4 va empaquetada en app.css (@fontsource). --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
