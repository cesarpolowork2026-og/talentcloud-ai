<?php

use App\Enums\EstadoProcesamientoCv;
use App\Events\CvProcesado;
use App\Jobs\ExtraerTextoCv;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Cvs\Extraccion\ArchivoIlegible;
use App\Services\Cvs\Extraccion\LectorDeCv;
use App\Services\Cvs\SubirCv;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

const TEXTO_CV = "Elena Ruiz\nDesarrolladora backend con 5 años de experiencia con Java y Spring Boot.\nDiseñé esquemas en PostgreSQL.";

beforeEach(function () {
    Storage::fake('cvs');
});

it('extrae el texto de un PDF, con tildes y eñes', function () {
    $texto = app(LectorDeCv::class)->leer(DocumentosDePrueba::pdf(TEXTO_CV), SubirCv::MIMES['pdf']);

    expect($texto)->toContain('5 años de experiencia con Java')->toContain('Diseñé esquemas en PostgreSQL');
});

it('extrae el texto de un documento de Word', function () {
    $texto = app(LectorDeCv::class)->leer(DocumentosDePrueba::docx(TEXTO_CV), SubirCv::MIMES['docx']);

    expect($texto)->toContain('Desarrolladora backend con 5 años')->toContain('PostgreSQL');
});

it('rechaza un PDF escaneado sin texto', function () {
    app(LectorDeCv::class)->leer(DocumentosDePrueba::pdf(null), SubirCv::MIMES['pdf']);
})->throws(ArchivoIlegible::class, 'no contiene texto seleccionable');

it('rechaza un archivo dañado', function () {
    app(LectorDeCv::class)->leer('esto no es un pdf', SubirCv::MIMES['pdf']);
})->throws(ArchivoIlegible::class);

it('normaliza espacios y saltos de línea', function () {
    expect(LectorDeCv::normalizar("  Java\t\t y   Spring \r\n\r\n\r\n\r\nDocker  "))->toBe("Java y Spring\n\nDocker");
});

it('guarda el CV en el disco privado y lo procesa en la cola', function () {
    Event::fake([CvProcesado::class]);
    $candidato = Candidato::factory()->create();

    $cv = app(SubirCv::class)->ejecutar($candidato, UploadedFile::fake()->createWithContent('Mi CV.pdf', DocumentosDePrueba::pdf(TEXTO_CV)), null);

    $cv->refresh();
    Storage::disk('cvs')->assertExists($cv->ruta);

    expect($cv->ruta)->toStartWith('candidatos/'.$candidato->id.'/')
        ->and($cv->ruta)->not->toContain('Mi CV')
        ->and($cv->nombre_original)->toBe('Mi CV.pdf')
        ->and($cv->estado_procesamiento)->toBe(EstadoProcesamientoCv::Procesado)
        ->and($cv->texto_extraido)->toContain('Spring Boot')
        ->and($cv->procesado_at)->not->toBeNull();

    Event::assertDispatched(CvProcesado::class);
});

it('marca el CV con error y permite reintentar el análisis', function () {
    $candidato = Candidato::factory()->create();
    $cv = app(SubirCv::class)->ejecutar($candidato, UploadedFile::fake()->createWithContent('escaneado.pdf', DocumentosDePrueba::pdf(null)), null);

    expect($cv->refresh()->estado_procesamiento)->toBe(EstadoProcesamientoCv::Error)
        ->and($cv->error)->toContain('escaneado');

    // Se reemplaza el archivo por uno legible y se reintenta.
    Storage::disk('cvs')->put($cv->ruta, DocumentosDePrueba::pdf(TEXTO_CV));
    app(SubirCv::class)->reintentar($cv);

    expect($cv->refresh()->estado_procesamiento)->toBe(EstadoProcesamientoCv::Procesado)
        ->and($cv->error)->toBeNull();
});

it('ignora un Job de un CV que ya no existe', function () {
    ExtraerTextoCv::dispatchSync(999999);

    expect(Cv::count())->toBe(0);
});

it('descarga el CV solo a quien puede ver al candidato', function () {
    $nimbus = Empresa::factory()->create();
    $vela = Empresa::factory()->create();
    $candidato = Candidato::factory()->registradoPor($nimbus)->create();
    $cv = app(SubirCv::class)->ejecutar($candidato, UploadedFile::fake()->createWithContent('CV.pdf', DocumentosDePrueba::pdf(TEXTO_CV)), null);

    $this->actingAs(User::factory()->deEmpresa($nimbus)->reclutador()->create())
        ->get(route('cvs.descargar', $cv))
        ->assertOk()
        ->assertDownload('CV.pdf');

    $this->actingAs(User::factory()->deEmpresa($vela)->reclutador()->create())
        ->get(route('cvs.descargar', $cv))
        ->assertForbidden();

    $this->actingAs(User::factory()->postulante()->create())
        ->get(route('cvs.descargar', $cv))
        ->assertForbidden();
});

it('permite al postulante descargar su propio CV', function () {
    $user = User::factory()->postulante()->create();
    $candidato = Candidato::factory()->conCuenta($user)->create();
    $cv = app(SubirCv::class)->ejecutar($candidato, UploadedFile::fake()->createWithContent('Yo.docx', DocumentosDePrueba::docx(TEXTO_CV)), $user);

    $this->actingAs($user)->get(route('cvs.descargar', $cv))->assertOk();
});
