<?php

use App\Enums\EstadoVacante;
use App\Enums\MotorMatching;
use App\Enums\TipoRequisito;
use App\Ia\RespuestaDeMatching;
use App\Ia\RespuestaIaNoValida;
use App\Jobs\CalcularMatch;
use App\Livewire\Matching\Detalle;
use App\Matching\FakeMatchingEngine;
use App\Matching\HttpMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Matching\MotorConRespaldo;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

/*
 * Matching con el servicio de IA (MATCHING_ENGINE=http): contrato, respaldo en
 * el análisis básico si el servicio falla y recálculo posterior.
 */

/** Respuesta del servicio que cumple el contrato para los requisitos que se le enviaron. */
function respuestaDelServicio(Request $request): array
{
    return [
        'version' => 1,
        'score' => 81.5,
        'motor' => 'http',
        'resumen' => 'Coincide en 1 de 2 requisitos (Java). Para revisar: Kubernetes.',
        'requisitos' => array_map(fn (array $r) => [
            'requisito_id' => $r['id'],
            'competencia' => $r['competencia'],
            'porcentaje' => $r['competencia'] === 'Java' ? 94 : 20,
            'nivel' => $r['competencia'] === 'Java' ? 'coincide' : 'revisar',
            'evidencia' => 'Evidencia del servicio para '.$r['competencia'].'.',
            'motivo' => 'Motivo del servicio para '.$r['competencia'].'.',
        ], $request['requisitos']),
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'talentcloud.ia.habilitada' => true,
        'talentcloud.ia.url' => 'http://ia.test',
        'talentcloud.ia.api_key' => 'clave',
        'talentcloud.matching.motor' => 'http',
    ]);

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    foreach (['Java' => TipoRequisito::Obligatorio, 'Kubernetes' => TipoRequisito::Deseable] as $nombre => $tipo) {
        Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear($nombre)->id, 'tipo' => $tipo]);
    }

    $elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz']);
    $cv = Cv::factory()->procesado("5 años de experiencia con Java.\nKubernetes (interés en aprender).")->create(['candidato_id' => $elena->id]);
    $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $elena->id, 'cv_id' => $cv->id]);

    actuarComo($this->marta);
});

// — Contrato —

it('envía el cuerpo del contrato y lee la respuesta del ejemplo', function () {
    Http::fake(['ia.test/matching/evaluar' => Http::response(ejemploDeContrato('match_result'))]);
    $entrada = entradaDeEjemplo(ejemploDeContrato('match_input'));

    $resultado = app(HttpMatchingEngine::class)->evaluar($entrada);

    expect($resultado->aArreglo())->toEqual(ejemploDeContrato('match_result'));
    Http::assertSent(fn (Request $request) => $request->url() === 'http://ia.test/matching/evaluar'
        && $request->hasHeader('X-Api-Key', 'clave')
        && $request->data() === ejemploDeContrato('match_input'));
});

it('rechaza respuestas de matching fuera de contrato', function (callable $alterar, string $campo) {
    $entrada = entradaDeEjemplo(ejemploDeContrato('match_input'));
    $respuesta = $alterar(ejemploDeContrato('match_result'));

    expect(fn () => RespuestaDeMatching::leer($respuesta, $entrada))->toThrow(RespuestaIaNoValida::class, $campo);
})->with([
    'otra versión' => [fn (array $r) => [...$r, 'version' => 2], 'version'],
    'otro motor' => [fn (array $r) => [...$r, 'motor' => 'fake'], 'motor'],
    'score fuera de rango' => [fn (array $r) => [...$r, 'score' => 120], 'score'],
    'nivel desconocido' => [function (array $r) {
        $r['requisitos'][0]['nivel'] = 'excelente';

        return $r;
    }, 'nivel'],
    'porcentaje fuera de rango' => [function (array $r) {
        $r['requisitos'][0]['porcentaje'] = 140;

        return $r;
    }, 'porcentaje'],
    'falta un requisito' => [function (array $r) {
        array_pop($r['requisitos']);

        return $r;
    }, 'no corresponden a los enviados'],
]);

// — Motor configurado —

it('usa el servicio con respaldo solo si está elegido y habilitado', function (string $motor, bool $habilitada, string $clase) {
    config(['talentcloud.matching.motor' => $motor, 'talentcloud.ia.habilitada' => $habilitada]);

    expect(app(MatchingEngineInterface::class))->toBeInstanceOf($clase);
})->with([
    'http y habilitada' => ['http', true, MotorConRespaldo::class],
    'http sin IA' => ['http', false, FakeMatchingEngine::class],
    'fake' => ['fake', true, FakeMatchingEngine::class],
]);

// — Cálculo con el servicio y respaldo —

it('guarda el matching explicado por el servicio y la pantalla lo presenta como tal', function () {
    Http::fake(['ia.test/matching/evaluar' => fn (Request $request) => Http::response(respuestaDelServicio($request))]);

    CalcularMatch::dispatchSync($this->nimbus->id, $this->postulacion->id);

    $postulacion = $this->postulacion->fresh();
    expect($postulacion->match_motor)->toBe(MotorMatching::Http)
        ->and($postulacion->match_score)->toEqual(81.5)
        ->and($postulacion->match_detalle['requisitos'][0]['motivo'])->toBe('Motivo del servicio para Java.');

    Livewire::test(Detalle::class, ['postulacion' => $postulacion])
        ->assertSee('Evidencia del servicio para Java.')
        ->assertSee('correspondencia semántica (embeddings)')
        ->assertDontSee('Análisis básico');
});

it('usa el análisis básico si el servicio no responde, y lo avisa en el log', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    Log::spy();

    CalcularMatch::dispatchSync($this->nimbus->id, $this->postulacion->id);

    $postulacion = $this->postulacion->fresh();
    expect($postulacion->match_motor)->toBe(MotorMatching::Fake)
        ->and($postulacion->match_score)->toBeGreaterThan(0);
    Log::shouldHaveReceived('log')->withArgs(fn ($nivel, $mensaje) => $nivel === 'warning' && $mensaje === 'Matching con el motor de respaldo')->once();

    Livewire::test(Detalle::class, ['postulacion' => $postulacion])->assertSee('Análisis básico');
});

it('registra como error una respuesta inválida y usa el análisis básico', function () {
    Http::fake(['ia.test/*' => Http::response(['detail' => 'Clave de API no válida.'], 401)]);
    Log::spy();

    CalcularMatch::dispatchSync($this->nimbus->id, $this->postulacion->id);

    expect($this->postulacion->fresh()->match_motor)->toBe(MotorMatching::Fake);
    Log::shouldHaveReceived('log')->withArgs(fn ($nivel) => $nivel === 'error')->once();
});

// — Recálculo cuando el servicio vuelve —

it('recalcula solo los resultados del análisis básico', function () {
    Queue::fake();
    $basica = $this->postulacion;
    $basica->update(['match_motor' => MotorMatching::Fake]);
    $conIa = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)->create(['match_motor' => MotorMatching::Http]));

    $this->artisan('talentcloud:recalcular-matching --solo-basicos')
        ->expectsOutputToContain('Encolando 1 postulaciones')
        ->assertSuccessful();

    Queue::assertPushed(CalcularMatch::class, 1);
    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $basica->id && $job->postulacionId !== $conIa->id);
});

it('no recalcula resultados básicos si el motor configurado es el básico', function () {
    Queue::fake();
    config(['talentcloud.matching.motor' => 'fake']);

    $this->artisan('talentcloud:recalcular-matching --solo-basicos')
        ->expectsOutputToContain('no mejoraría nada')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('recalcula ahora con --sync', function () {
    Http::fake(['ia.test/matching/evaluar' => fn (Request $request) => Http::response(respuestaDelServicio($request))]);

    $this->artisan('talentcloud:recalcular-matching --sync')->assertSuccessful();

    expect($this->postulacion->fresh()->match_motor)->toBe(MotorMatching::Http);
});
