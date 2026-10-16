<?php

use App\Models\Candidato;
use App\Models\Cv;
use App\Services\Cvs\Extraccion\ArchivoIlegible;
use App\Services\Cvs\Extraccion\LectorDeCv;
use Illuminate\Support\Facades\Storage;

/*
 * Un volcado de la base trae las filas de los CV pero no sus archivos: el comando los vuelve a crear.
 */

beforeEach(fn () => Storage::fake('cvs'));

function cvDeDemostracion(string $email, string $ruta, string $mime, ?string $texto): Cv
{
    $candidato = Candidato::factory()->create(['email' => $email]);

    return Cv::factory()->procesado($texto ?? '')->create([
        'candidato_id' => $candidato->id,
        'ruta' => $ruta,
        'mime' => $mime,
        'texto_extraido' => $texto,
    ]);
}

it('crea el archivo que falta y su texto se puede leer otra vez', function () {
    $texto = "Marco Díaz\nExperiencia\n5 años de experiencia con Java y Spring Boot.";
    $pdf = cvDeDemostracion('marco@correo.com', 'candidatos/1/a.pdf', 'application/pdf', $texto);
    $docx = cvDeDemostracion('rosa@correo.com', 'candidatos/2/b.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $texto);

    $this->artisan('talentcloud:regenerar-cvs-demo')
        ->expectsOutput('Archivos creados: 2. Ya existían: 0.')
        ->assertSuccessful();

    $lector = app(LectorDeCv::class);
    expect($lector->leer(Storage::disk('cvs')->get($pdf->ruta), $pdf->mime))->toContain('5 años de experiencia con Java')
        ->and($lector->leer(Storage::disk('cvs')->get($docx->ruta), $docx->mime))->toContain('5 años de experiencia con Java');
});

it('no pisa un archivo que ya existe', function () {
    $cv = cvDeDemostracion('marco@correo.com', 'candidatos/1/a.pdf', 'application/pdf', 'Texto de la base');
    Storage::disk('cvs')->put($cv->ruta, 'contenido original');

    $this->artisan('talentcloud:regenerar-cvs-demo')
        ->expectsOutput('Archivos creados: 0. Ya existían: 1.');

    expect(Storage::disk('cvs')->get($cv->ruta))->toBe('contenido original');
});

it('no toca los CV de candidatos que no son de demostración', function () {
    $real = cvDeDemostracion('persona@empresa.pe', 'candidatos/9/real.pdf', 'application/pdf', 'CV de una persona real');

    $this->artisan('talentcloud:regenerar-cvs-demo')
        ->expectsOutput('Archivos creados: 0. Ya existían: 0.');

    Storage::disk('cvs')->assertMissing($real->ruta);
});

it('crea una página sin texto cuando el CV no tiene texto extraído', function () {
    $cv = cvDeDemostracion('escaneado@correo.com', 'candidatos/3/c.pdf', 'application/pdf', null);

    $this->artisan('talentcloud:regenerar-cvs-demo')->assertSuccessful();

    Storage::disk('cvs')->assertExists($cv->ruta);
    // Como el documento escaneado que era: la aplicación lo reconoce como ilegible.
    expect(fn () => app(LectorDeCv::class)->leer(Storage::disk('cvs')->get($cv->ruta), 'application/pdf'))
        ->toThrow(ArchivoIlegible::class);
});
