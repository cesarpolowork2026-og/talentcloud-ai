<?php

use App\Jobs\AnalizarCvConIa;
use App\Jobs\GenerarEmbeddingsDeRequisitos;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.ia.url' => 'http://ia.test']);

    // CV y vacantes de dos empresas: el comando recorre toda la plataforma.
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach (Empresa::factory()->count(2)->create() as $empresa) {
            Requisito::factory()->create(['vacante_id' => Vacante::factory()->deEmpresa($empresa)->create()->id]);
        }
        Vacante::factory()->deEmpresa(Empresa::factory()->create())->create(); // sin requisitos
    });

    $this->procesados = Cv::factory()->count(2)->procesado()->create(['candidato_id' => Candidato::factory()->create()->id]);
    Cv::factory()->conError()->create();
});

it('encola el análisis de los CV procesados y los embeddings de las vacantes con requisitos', function () {
    Queue::fake();
    Http::fake(['ia.test/health' => Http::response(['estado' => 'ok'])]);

    $this->artisan('talentcloud:reindexar-embeddings')
        ->expectsOutputToContain('Encolando 2 CV')
        ->expectsOutputToContain('Encolando 2 vacantes')
        ->assertSuccessful();

    Queue::assertPushed(AnalizarCvConIa::class, 2);
    Queue::assertPushed(GenerarEmbeddingsDeRequisitos::class, 2);
});

it('puede limitarse a los CV', function () {
    Queue::fake();
    Http::fake(['ia.test/health' => Http::response(['estado' => 'ok'])]);

    $this->artisan('talentcloud:reindexar-embeddings --cvs')->assertSuccessful();

    Queue::assertPushed(AnalizarCvConIa::class, 2);
    Queue::assertNotPushed(GenerarEmbeddingsDeRequisitos::class);
});

it('no hace nada si el servicio de IA está deshabilitado', function () {
    Queue::fake();
    config(['talentcloud.ia.habilitada' => false]);

    $this->artisan('talentcloud:reindexar-embeddings')
        ->expectsOutputToContain('IA_HABILITADA=false')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('avisa si el servicio de IA no responde', function () {
    Queue::fake();
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('talentcloud:reindexar-embeddings')
        ->expectsOutputToContain('no responde en http://ia.test')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('en modo síncrono cuenta los fallos y termina con error', function () {
    Http::fake([
        'ia.test/health' => Http::response(['estado' => 'ok']),
        'ia.test/*' => Http::response(['detail' => 'Clave de API no válida.'], 401),
    ]);

    $this->artisan('talentcloud:reindexar-embeddings --cvs --sync')
        ->expectsOutputToContain('Procesando 2 CV')
        ->assertFailed();
});
