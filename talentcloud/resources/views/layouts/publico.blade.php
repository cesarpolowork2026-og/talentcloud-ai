{{--
    Páginas públicas de contenido (bolsa de empleos): la misma cabecera y pie
    que la presentación, con el contenido a lo ancho. Sin datos internos: solo
    lo que devuelve VacanteRepository::publicadas().
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? null])
    @isset($descripcion)
        <meta name="description" content="{{ $descripcion }}">
    @endisset
</head>
<body class="antialiased">
<div class="flex min-h-screen flex-col bg-bg">
    <x-presentacion.cabecera />

    <main class="mx-auto w-full max-w-[1240px] flex-1 px-4 py-10 sm:px-8">
        {{ $slot }}
    </main>

    <x-presentacion.pie />
</div>
@livewireScripts
</body>
</html>
