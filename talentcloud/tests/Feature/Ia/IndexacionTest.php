<?php

use App\DTO\RequisitoData;
use App\Enums\FuenteCompetencia;
use App\Enums\TipoRequisito;
use App\Events\CvAnalizado;
use App\Ia\ServicioIaNoDisponible;
use App\Jobs\AnalizarCvConIa;
use App\Jobs\GenerarEmbeddingsDeRequisitos;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\CompetenciasDelCandidato;
use App\Services\Cvs\AnalizarCv;
use App\Services\Cvs\SubirCv;
use App\Services\Vacantes\GestionarRequisitos;
use App\Services\Vacantes\IndexarRequisitos;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Pgvector\Laravel\Vector;

/*
 * Indexación con IA: análisis de CV (fragmentos con embedding, datos
 * estructurados, competencias extraídas) y embeddings de requisitos.
 * El servicio se simula con respuestas que cumplen el contrato.
 */

// Suficiente texto para que la extracción no lo tome por un PDF escaneado.
const TEXTO_CV = "Experiencia\nJava con Spring Boot en producción durante cinco años.\nContenedores con Docker.";

function vector384(float $semilla = 1.0): array
{
    return array_fill(0, 384, $semilla / sqrt(384));
}

/**
 * @param  list<array{0: Competencia, 1: float|null, 2?: bool}>  $competencias  [competencia, años, dudosa]
 * @param  list<string>  $fragmentos
 */
function respuestaDeAnalisis(array $competencias, array $fragmentos = ['Java en producción.']): array
{
    return [
        'version' => 1,
        'secciones' => [['nombre' => 'experiencia', 'titulo' => 'Experiencia', 'orden' => 0, 'texto' => implode("\n", $fragmentos)]],
        'competencias' => array_map(fn (array $c) => [
            'competencia_id' => $c[0]->id,
            'nombre' => $c[0]->nombre,
            'anios' => $c[1],
            'evidencia' => $c[0]->nombre.' en producción.',
            'seccion' => 'experiencia',
            'dudosa' => $c[2] ?? false,
        ], $competencias),
        'experiencia_total_anios' => 5.0,
        'cargos' => [['cargo' => 'Backend Developer', 'organizacion' => 'Acme', 'desde' => 2021, 'hasta' => null, 'evidencia' => 'Backend Developer — Acme (2021 – actualidad)']],
        'certificaciones' => [],
        'fragmentos' => array_map(fn (string $texto, int $i) => [
            'seccion' => 'experiencia', 'orden' => $i, 'texto' => $texto, 'embedding' => vector384($i + 1),
        ], $fragmentos, array_keys($fragmentos)),
        'modelo_embeddings' => 'paraphrase-multilingual-MiniLM-L12-v2',
    ];
}

function competenciasExtraidas(Candidato $candidato): array
{
    return $candidato->competencias()
        ->wherePivot('fuente', FuenteCompetencia::Extraccion->value)
        ->orderBy('nombre')
        ->get()
        ->mapWithKeys(fn (Competencia $c) => [$c->nombre => $c->pivot->anios === null ? null : (float) $c->pivot->anios])
        ->all();
}

beforeEach(function () {
    Storage::fake('cvs');
    Http::preventStrayRequests();
    Sleep::fake();
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.ia.url' => 'http://ia.test']);

    $this->java = Competencia::obtenerOCrear('Java');
    $this->docker = Competencia::obtenerOCrear('Docker');
    $this->kubernetes = Competencia::obtenerOCrear('Kubernetes');
    $this->candidato = Candidato::factory()->create();
});

// — Análisis de CV —

it('analiza el CV recién subido y guarda fragmentos, datos y competencias', function () {
    Event::fake([CvAnalizado::class]);
    Http::fake(['ia.test/cv/analizar' => Http::response(respuestaDeAnalisis(
        [[$this->java, 5.0], [$this->docker, null], [$this->kubernetes, null, true]],
        ['Java con Spring Boot en producción.', 'Contenedores con Docker.'],
    ))]);

    $cv = app(SubirCv::class)->ejecutar($this->candidato, UploadedFile::fake()->createWithContent('cv.docx', DocumentosDePrueba::docx(TEXTO_CV)), null);

    $cv->refresh();
    $fragmentos = $cv->fragmentos()->get();

    expect($fragmentos->pluck('texto')->all())->toBe(['Java con Spring Boot en producción.', 'Contenedores con Docker.'])
        ->and($fragmentos[0]->embedding)->toBeInstanceOf(Vector::class)
        ->and($fragmentos[0]->embedding->toArray())->toHaveCount(384)
        ->and($fragmentos[0]->embedding_modelo)->toBe('paraphrase-multilingual-MiniLM-L12-v2')
        ->and($cv->datos_estructurados['experiencia_total_anios'])->toBe(5)
        ->and($cv->datos_estructurados['cargos'][0]['organizacion'])->toBe('Acme')
        ->and($cv->datos_estructurados)->toHaveKey('analizado_at')
        // Kubernetes solo como interés: no se acredita.
        ->and(competenciasExtraidas($this->candidato))->toBe(['Docker' => null, 'Java' => 5.0]);

    Http::assertSent(fn (Request $request) => $request['cv']['id'] === $cv->id
        && collect($request['catalogo'])->pluck('nombre')->contains('Kubernetes'));
    Event::assertDispatched(CvAnalizado::class, fn (CvAnalizado $e) => $e->cv->is($cv));
});

it('reemplaza el análisis anterior sin tocar las competencias manuales', function () {
    app(CompetenciasDelCandidato::class)->agregar($this->candidato, 'Liderazgo');
    $cv = Cv::factory()->procesado('Java y Docker.')->create(['candidato_id' => $this->candidato->id]);

    Http::fake(['ia.test/cv/analizar' => Http::sequence()
        ->push(respuestaDeAnalisis([[$this->java, 2.0], [$this->docker, 1.0]], ['Uno.', 'Dos.', 'Tres.']))
        ->push(respuestaDeAnalisis([[$this->java, 3.0]], ['Solo uno.']))]);

    app(AnalizarCv::class)->ejecutar($cv);
    app(AnalizarCv::class)->ejecutar($cv);

    expect($cv->fragmentos()->pluck('texto')->all())->toBe(['Solo uno.'])
        ->and(competenciasExtraidas($this->candidato))->toBe(['Java' => 3.0])
        ->and($this->candidato->competencias()->wherePivot('fuente', FuenteCompetencia::Manual->value)->pluck('nombre')->all())->toBe(['Liderazgo']);
});

it('no deja que un CV anterior pise las competencias del CV vigente', function () {
    $anterior = Cv::factory()->procesado('Java.')->create(['candidato_id' => $this->candidato->id]);
    Cv::factory()->procesado('Docker.')->create(['candidato_id' => $this->candidato->id]);
    Http::fake(['ia.test/cv/analizar' => Http::response(respuestaDeAnalisis([[$this->java, 9.0]]))]);

    app(AnalizarCv::class)->ejecutar($anterior);

    expect($anterior->fragmentos()->count())->toBe(1)
        ->and(competenciasExtraidas($this->candidato))->toBe([]);
});

it('no llama al servicio si está deshabilitado o el CV no tiene texto', function () {
    Http::fake();

    config(['talentcloud.ia.habilitada' => false]);
    $cv = app(SubirCv::class)->ejecutar($this->candidato, UploadedFile::fake()->createWithContent('cv.docx', DocumentosDePrueba::docx(TEXTO_CV)), null);
    expect($cv->refresh()->texto_extraido)->toContain('Spring Boot');

    config(['talentcloud.ia.habilitada' => true]);
    app(SubirCv::class)->ejecutar($this->candidato, UploadedFile::fake()->createWithContent('escaneado.pdf', DocumentosDePrueba::pdf(null)), null);

    Http::assertNothingSent();
});

it('reintenta el análisis si el servicio no responde', function () {
    Http::fake(['ia.test/*' => Http::response([], 503)]);
    $cv = Cv::factory()->procesado('Java.')->create(['candidato_id' => $this->candidato->id]);

    $job = (new AnalizarCvConIa($cv->id))->withFakeQueueInteractions();

    // La excepción llega a la cola, que reintenta según $tries y $backoff.
    expect(fn () => $job->handle(app(AnalizarCv::class)))->toThrow(ServicioIaNoDisponible::class);
    $job->assertNotFailed();
});

it('da por fallido el análisis si el servicio rechaza la petición', function () {
    Http::fake(['ia.test/*' => Http::response(['detail' => 'Clave de API no válida.'], 401)]);
    $cv = Cv::factory()->procesado('Java.')->create(['candidato_id' => $this->candidato->id]);

    $job = (new AnalizarCvConIa($cv->id))->withFakeQueueInteractions();
    $job->handle(app(AnalizarCv::class));

    $job->assertFailed();
    expect($cv->fragmentos()->count())->toBe(0);
});

// — Embeddings de requisitos —

it('genera el embedding de los requisitos al guardarlos', function () {
    $empresa = Empresa::factory()->create();
    actuarComo(User::factory()->deEmpresa($empresa)->reclutador()->create());
    $vacante = Vacante::factory()->deEmpresa($empresa)->create();
    Http::fake(['ia.test/requisitos/embeddings' => function (Request $request) {
        return Http::response([
            'version' => 1, 'modelo' => 'paraphrase-multilingual-MiniLM-L12-v2', 'dimensiones' => 384,
            'embeddings' => array_map(fn (array $t) => ['id' => $t['id'], 'vector' => vector384()], $request['textos']),
        ]);
    }]);

    $requisito = app(GestionarRequisitos::class)->agregar($vacante, new RequisitoData('Kubernetes', TipoRequisito::Deseable, 2, 2, 'Operación de clústeres'));

    $requisito->refresh();
    expect($requisito->embedding?->toArray())->toHaveCount(384)
        ->and($requisito->embedding_modelo)->toBe('paraphrase-multilingual-MiniLM-L12-v2');
    Http::assertSent(fn (Request $request) => $request['textos'] === [
        ['id' => $requisito->id, 'texto' => 'Kubernetes. Operación de clústeres.'],
    ]);
});

it('describe el requisito para el embedding igual que el servicio de IA', function () {
    // Mismo caso que tests/test_evaluador.py::test_describe_el_requisito_sin_los_anios del servicio.
    $this->kubernetes->update(['sinonimos' => ['k8s']]);
    $requisito = Requisito::factory()->make([
        'competencia_id' => $this->kubernetes->id,
        'anios_minimos' => 2,
        'descripcion' => 'Operación de clústeres en producción.',
    ]);

    expect(IndexarRequisitos::textoParaEmbedding($requisito))->toBe('Kubernetes (k8s). Operación de clústeres en producción.');
});

it('programa los embeddings con la empresa de la vacante', function () {
    Queue::fake();
    $empresa = Empresa::factory()->create();
    actuarComo(User::factory()->deEmpresa($empresa)->reclutador()->create());
    $vacante = Vacante::factory()->deEmpresa($empresa)->create();

    app(GestionarRequisitos::class)->agregar($vacante, new RequisitoData('Java', TipoRequisito::Obligatorio, 3, null, null));

    Queue::assertPushedOn('ia', GenerarEmbeddingsDeRequisitos::class,
        fn (GenerarEmbeddingsDeRequisitos $job) => $job->vacanteId === $vacante->id && $job->empresaId === $empresa->id);
});

it('distingue el primer análisis de un CV de un reanálisis', function () {
    Event::fake([CvAnalizado::class]);
    $cv = Cv::factory()->procesado('Java y Docker.')->create(['candidato_id' => $this->candidato->id]);
    Http::fake(['ia.test/cv/analizar' => Http::response(respuestaDeAnalisis([[$this->java, 2.0]]))]);

    app(AnalizarCv::class)->ejecutar($cv);
    app(AnalizarCv::class)->ejecutar($cv->fresh());

    Event::assertDispatched(CvAnalizado::class, fn (CvAnalizado $e) => $e->primeraVez);
    Event::assertDispatched(CvAnalizado::class, fn (CvAnalizado $e) => ! $e->primeraVez);
});
