<?php

use App\Enums\EstadoVacante;
use App\Enums\MotorMatching;
use App\Enums\PlanEmpresa;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Livewire\Empresas\Listado as Empresas;
use App\Livewire\Matching\Detalle as Matching;
use App\Livewire\MiEmpresa\Panel as MiEmpresa;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoSolicitudDePlan;
use App\Services\Empresas\CupoDeIa;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

/*
 * Planes con capa gratuita: prueba del plan Pro, cupo mensual de matching con IA
 * y solicitud de cambio de plan (sin pasarela de pago todavía).
 */

/** Respuesta del servicio de IA que cumple el contrato para los requisitos enviados. */
function respuestaDeIaParaCupo(Request $request): array
{
    return [
        'version' => 1,
        'score' => 80.0,
        'motor' => 'http',
        'resumen' => 'Coincide en Java.',
        'requisitos' => array_map(fn (array $r) => [
            'requisito_id' => $r['id'],
            'competencia' => $r['competencia'],
            'porcentaje' => 90,
            'nivel' => 'coincide',
            'evidencia' => 'Evidencia para '.$r['competencia'].'.',
            'motivo' => 'Motivo para '.$r['competencia'].'.',
        ], $request['requisitos']),
    ];
}

/** El servicio de IA responde según el contrato. */
function iaRespondeParaCupo(): void
{
    Http::fake(['ia.test/matching/evaluar' => fn (Request $request) => Http::response(respuestaDeIaParaCupo($request))]);
}

/** Postulación con CV procesado a la vacante: lista para calcular su matching. */
function postulacionParaCupo(Vacante $vacante): Postulacion
{
    $candidato = Candidato::factory()->create();
    $cv = Cv::factory()->procesado('5 años de experiencia con Java.')->create(['candidato_id' => $candidato->id]);

    return Postulacion::factory()->paraVacante($vacante)->create(['candidato_id' => $candidato->id, 'cv_id' => $cv->id]);
}

/** Ocupa $cuantas lugares del cupo de la empresa en el mes de $fecha. */
function ocuparCupo(Vacante $vacante, int $cuantas, DateTimeInterface $fecha): void
{
    $ids = Postulacion::factory()->count($cuantas)->paraVacante($vacante)->create()->pluck('id');
    DB::table('postulaciones')->whereIn('id', $ids)->update(['ia_cupo_at' => $fecha]);
}

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'talentcloud.ia.habilitada' => true,
        'talentcloud.ia.url' => 'http://ia.test',
        'talentcloud.ia.api_key' => 'clave',
        'talentcloud.matching.motor' => 'http',
    ]);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->empresa = Empresa::factory()->create(['nombre' => 'Orbita Labs', 'plan' => PlanEmpresa::Gratis]);
        $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create(['name' => 'Olga Ramos']);
        $this->reclutador = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
        $this->admin = User::factory()->admin()->create();

        $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        Requisito::factory()->create(['vacante_id' => $this->vacante->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'tipo' => TipoRequisito::Obligatorio]);
    });

    actuarComo($this->reclutador);
});

// — Prueba del plan Pro —

it('rige el plan Pro durante la prueba y el Gratis al vencer, sin tocar lo existente', function () {
    $this->empresa->forceFill(['prueba_hasta' => now()->addDays(PlanEmpresa::DIAS_DE_PRUEBA)])->save();

    expect($this->empresa->planVigente())->toBe(PlanEmpresa::Pro)
        ->and($this->empresa->etiquetaDelPlan())->toBe('Pro (prueba)')
        ->and($this->empresa->diasDePrueba())->toBe(14);

    // Durante la prueba publica una segunda vacante (Gratis permite una).
    $segunda = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Borrador)->create();
    DB::transaction(fn () => app(LimitesDelPlan::class)->verificarVacanteActiva($this->empresa->id));
    $segunda->forceFill(['estado' => EstadoVacante::Abierta])->save();

    $this->travel(PlanEmpresa::DIAS_DE_PRUEBA + 1)->days();
    $empresa = $this->empresa->fresh();

    expect($empresa->enPrueba())->toBeFalse()
        ->and($empresa->planVigente())->toBe(PlanEmpresa::Gratis)
        ->and(app(LimitesDelPlan::class)->uso($empresa)->excedeVacantes())->toBeTrue();

    expect(fn () => DB::transaction(fn () => app(LimitesDelPlan::class)->verificarVacanteActiva($empresa->id)))
        ->toThrow(OperacionNoPermitida::class, 'El plan Gratis permite 1 vacantes activas a la vez.');
});

// — Cupo de IA —

it('consume un lugar del cupo por postulación, y recalcularla no consume otro', function () {
    iaRespondeParaCupo();
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);
    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    $postulacion->refresh();
    expect($postulacion->match_motor)->toBe(MotorMatching::Http)
        ->and($postulacion->ia_cupo_at)->not->toBeNull()
        ->and($postulacion->ia_sin_cupo)->toBeFalse()
        ->and(app(CupoDeIa::class)->usadasEsteMes($this->empresa->id))->toBe(1);
});

it('con el cupo del mes agotado usa el análisis básico, lo explica y no llama al servicio', function () {
    iaRespondeParaCupo();
    ocuparCupo($this->vacante, 30, now());
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    $postulacion->refresh();
    expect($postulacion->match_motor)->toBe(MotorMatching::Fake)
        ->and($postulacion->ia_sin_cupo)->toBeTrue()
        ->and($postulacion->ia_cupo_at)->toBeNull();
    Http::assertNothingSent();

    Livewire::test(Matching::class, ['postulacion' => $postulacion])
        ->assertSee('el cupo mensual de matching con IA del plan de Orbita Labs estaba agotado');

    actuarComo($this->cuenta);
    Livewire::test(MiEmpresa::class)
        ->assertSee('Postulaciones con IA este mes')
        ->assertSee('Cupo agotado: hasta el');
});

it('renueva el cupo cada mes: lo usado el mes anterior no cuenta', function () {
    iaRespondeParaCupo();
    ocuparCupo($this->vacante, 30, now()->subMonthNoOverflow()->startOfMonth());
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    expect($postulacion->fresh()->match_motor)->toBe(MotorMatching::Http);
});

it('no consume cupo si el servicio no responde y se usa el análisis básico', function () {
    Http::fake(['ia.test/matching/evaluar' => Http::response('caído', 503)]);
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    $postulacion->refresh();
    expect($postulacion->match_motor)->toBe(MotorMatching::Fake)
        ->and($postulacion->ia_cupo_at)->toBeNull()
        ->and($postulacion->ia_sin_cupo)->toBeFalse();
});

it('no consume cupo con la IA apagada', function () {
    config(['talentcloud.ia.habilitada' => false]);
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    expect($postulacion->fresh()->ia_cupo_at)->toBeNull();
});

it('Enterprise no tiene tope de IA', function () {
    iaRespondeParaCupo();
    $this->empresa->forceFill(['plan' => PlanEmpresa::Enterprise])->save();
    ocuparCupo($this->vacante, 40, now());
    $postulacion = postulacionParaCupo($this->vacante);

    CalcularMatch::dispatchSync($this->empresa->id, $postulacion->id);

    expect($postulacion->fresh()->match_motor)->toBe(MotorMatching::Http);
});

// — Solicitud de cambio de plan —

it('la cuenta de empresa solicita un plan: avisa al admin SaaS y lo anota en la actividad', function () {
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->call('abrirPlanes')
        ->assertSee('Solicitar plan Pro')
        ->assertSee('S/ 349')
        ->call('solicitarPlan', 'pro')
        ->assertHasNoErrors()
        ->assertSet('mostrarPlanes', false)
        ->assertSee('Solicitaste el plan Pro el')
        ->call('solicitarPlan', 'pro')
        ->assertHasErrors('plan');

    $empresa = $this->empresa->fresh();
    expect($empresa->plan_solicitado)->toBe(PlanEmpresa::Pro)
        ->and($empresa->plan)->toBe(PlanEmpresa::Gratis);

    Notification::assertSentTo($this->admin, AvisoSolicitudDePlan::class, fn (AvisoSolicitudDePlan $n) => $n->texto === 'Olga Ramos (Orbita Labs) solicitó pasar del plan Gratis al plan Pro.');
    Notification::assertNotSentTo($this->reclutador, AvisoSolicitudDePlan::class);

    $actividad = app(TenantContext::class)->ejecutarPara($this->empresa->id, fn () => Actividad::sole());
    expect($actividad->descripcion)->toBe('Olga Ramos solicitó el plan Pro para Orbita Labs.');
});

it('no deja solicitar el plan que ya se tiene', function () {
    $this->empresa->forceFill(['plan' => PlanEmpresa::Starter])->save();
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->call('solicitarPlan', 'starter')
        ->assertHasErrors('plan')
        ->assertSee('Tu empresa ya tiene el plan Starter.');
});

it('solo la cuenta de empresa solicita planes', function () {
    // El reclutador ve los datos de su empresa, pero no pide planes.
    Livewire::test(MiEmpresa::class)->call('abrirPlanes')->assertForbidden();
    Livewire::test(MiEmpresa::class)->call('solicitarPlan', 'pro')->assertForbidden();

    expect($this->empresa->fresh()->plan_solicitado)->toBeNull();
});

it('aplicar el plan desde Empresas atiende la solicitud y termina la prueba', function () {
    $this->empresa->forceFill(['prueba_hasta' => now()->addDays(5), 'plan_solicitado' => PlanEmpresa::Starter, 'plan_solicitado_at' => now()])->save();
    actuarComo($this->admin);

    Livewire::test(Empresas::class)
        ->call('verDetalle', $this->empresa->id)
        ->assertSee('Solicitud: plan Starter')
        ->call('abrirCambioDePlan', $this->empresa->id)
        ->assertSet('planNuevo', 'starter')
        ->call('cambiarPlan')
        ->assertHasNoErrors();

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Starter)
        ->and($empresa->plan_solicitado)->toBeNull()
        ->and($empresa->prueba_hasta)->toBeNull()
        ->and($empresa->planVigente())->toBe(PlanEmpresa::Starter);
});

// — Precios —

it('publica los planes con sus precios en soles, enlazados desde la cabecera', function () {
    $this->get('/empleos')->assertOk()->assertSee('href="'.route('planes').'"', false);

    $this->get('/planes')
        ->assertOk()
        ->assertSee('Planes y precios')
        ->assertSee('S/ 0')
        ->assertSee('S/ 129')
        ->assertSee('S/ 349')
        ->assertSee('S/ 1 200')
        ->assertSee('Crear empresa gratis')
        ->assertSee('aria-current="page"', false);
});

it('muestra en Mi empresa los días que le quedan a la prueba', function () {
    $this->empresa->forceFill(['prueba_hasta' => now()->addDays(10)])->save();
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->assertSee('Pro (prueba)')
        ->assertSee('quedan 10 días');
});
