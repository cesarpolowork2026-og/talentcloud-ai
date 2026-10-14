<?php

use App\Services\Cvs\Extraccion\ArchivoIlegible;
use App\Services\Cvs\Extraccion\ExtractorDocx;
use Database\Seeders\Demo\DocumentosDePrueba;

/*
 * El extractor de .docx rechaza archivos que se descomprimirían a un tamaño desmedido (bomba ZIP) o que no son
 * un ZIP, con un mensaje para la persona, sin llegar a cargar el contenido.
 */

/** Un ZIP con una entrada que declara `$bytes` descomprimidos (el texto es muy repetitivo: comprime casi a nada). */
function zipConEntradaDe(int $bytes, string $nombre = 'word/document.xml'): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($ruta, ZipArchive::OVERWRITE);
    $zip->addFromString($nombre, str_repeat('A', $bytes));
    $zip->close();

    $contenido = file_get_contents($ruta);
    unlink($ruta);

    return $contenido;
}

it('sigue leyendo un documento normal', function () {
    $texto = app(ExtractorDocx::class)->extraer(DocumentosDePrueba::docx('Ingeniera de software con 5 años de experiencia en Laravel.'));

    expect($texto)->toContain('Ingeniera de software');
});

it('rechaza un ZIP que se descomprime a más de lo permitido, sin intentar leerlo', function () {
    $bomba = zipConEntradaDe(ExtractorDocx::MAX_BYTES_DESCOMPRIMIDOS + 1);

    expect(strlen($bomba))->toBeLessThan(200_000) // pesa poco comprimido: por eso es peligroso
        ->and(fn () => app(ExtractorDocx::class)->extraer($bomba))->toThrow(ArchivoIlegible::class, 'demasiado grande');
});

it('rechaza un ZIP con demasiadas entradas', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($ruta, ZipArchive::OVERWRITE);
    foreach (range(1, ExtractorDocx::MAX_ENTRADAS + 1) as $i) {
        $zip->addFromString("relleno/{$i}.txt", 'x');
    }
    $zip->close();
    $contenido = file_get_contents($ruta);
    unlink($ruta);

    expect(fn () => app(ExtractorDocx::class)->extraer($contenido))->toThrow(ArchivoIlegible::class, 'demasiado complejo');
});

it('un archivo que no es un ZIP da el mismo mensaje de documento inválido', function () {
    expect(fn () => app(ExtractorDocx::class)->extraer('esto no es un docx'))->toThrow(ArchivoIlegible::class, 'docx válido');
});
