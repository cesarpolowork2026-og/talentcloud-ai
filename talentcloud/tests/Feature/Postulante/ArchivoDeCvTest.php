<?php

namespace Tests\Feature\Postulante;

use App\Rules\ArchivoDeCv;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function cvValido(string $nombre, string $contenido): bool
{
    $archivo = UploadedFile::fake()->createWithContent($nombre, $contenido);

    return Validator::make(['archivoCv' => $archivo], ['archivoCv' => [new ArchivoDeCv]])->passes();
}

/** Un DOCX con el orden de partes de Word 2010 (docProps antes de word/), que libmagic da por application/octet-stream. */
function docxDeWord2010(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'cv');
    $zip = new \ZipArchive;
    $zip->open($ruta, \ZipArchive::OVERWRITE);
    foreach (['[Content_Types].xml', '_rels/.rels', 'docProps/core.xml', 'docProps/app.xml', 'word/document.xml', 'word/stylesWithEffects.xml'] as $parte) {
        $zip->addFromString($parte, '<x/>');
    }
    $zip->close();
    $contenido = (string) file_get_contents($ruta);
    unlink($ruta);

    return $contenido;
}

test('acepta un CV en PDF o DOCX por su contenido, también un DOCX de Word 2010', function () {
    expect(cvValido('cv.pdf', DocumentosDePrueba::pdf('Ana Rojas')))->toBeTrue()
        ->and(cvValido('cv.docx', DocumentosDePrueba::docx('Ana Rojas')))->toBeTrue()
        ->and(cvValido('CV_Final.DOCX', docxDeWord2010()))->toBeTrue();
});

test('rechaza un archivo cuyo contenido no corresponde a su extensión', function () {
    expect(cvValido('cv.docx', 'texto plano renombrado'))->toBeFalse()
        ->and(cvValido('cv.pdf', 'texto plano renombrado'))->toBeFalse()
        ->and(cvValido('cv.docx', DocumentosDePrueba::pdf('Ana Rojas')))->toBeFalse()
        ->and(cvValido('cv.txt', 'Ana Rojas'))->toBeFalse();
});

test('un ZIP que no es un documento de Word no pasa por DOCX', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new \ZipArchive;
    $zip->open($ruta, \ZipArchive::OVERWRITE);
    $zip->addFromString('foto.txt', 'x');
    $zip->close();

    expect(cvValido('cv.docx', (string) file_get_contents($ruta)))->toBeFalse();
    unlink($ruta);
});

test('el mensaje de error sigue nombrando los tipos permitidos', function () {
    $archivo = UploadedFile::fake()->createWithContent('cv.docx', 'no es Word');
    $errores = Validator::make(['archivoCv' => $archivo], ['archivoCv' => [new ArchivoDeCv]], attributes: ['archivoCv' => 'CV'])->errors();

    expect($errores->first('archivoCv'))->toContain('pdf, docx');
});
