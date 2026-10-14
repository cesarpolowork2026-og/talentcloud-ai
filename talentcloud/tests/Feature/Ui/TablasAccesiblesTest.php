<?php

use Illuminate\Support\Facades\Blade;

/*
 * Una <table> con `sr-only` NO queda oculta del todo: las tablas ignoran el 1 px de ancho y el recorte, miden lo que su
 * contenido y ensanchan la página (desplazamiento horizontal en el móvil, que solo se ve en un navegador real). La
 * tabla de datos para lectores de pantalla va dentro de un `<div class="sr-only">`.
 */

it('ninguna vista usa una tabla directamente con sr-only', function () {
    $infractoras = [];

    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($archivos as $archivo) {
        if ($archivo->isFile() && str_ends_with($archivo->getFilename(), '.blade.php')) {
            if (preg_match('/<table\b[^>]*\bsr-only\b/i', (string) file_get_contents($archivo->getPathname()))) {
                $infractoras[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }
    }

    expect($infractoras)->toBe([]);
});

it('el gráfico de líneas lleva su tabla de datos dentro de un contenedor oculto', function () {
    $html = Blade::render('<x-grafico.lineas :etiquetas="$e" :series="$s" descripcion="Prueba" />', [
        'e' => ['a', 'b'],
        's' => [['nombre' => 'Actual', 'valores' => [1, 2], 'principal' => true]],
    ]);

    expect($html)->toMatch('/<div class="sr-only">\s*<table>\s*<caption>Prueba<\/caption>/');
});
