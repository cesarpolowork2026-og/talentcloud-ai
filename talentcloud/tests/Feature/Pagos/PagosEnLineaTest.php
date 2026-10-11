<?php

use App\DTO\SuscripcionData;
use App\Enums\EstadoSuscripcion;
use App\Enums\EstadoVacante;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Livewire\Empresas\Listado as Empresas;
use App\Livewire\MiEmpresa\Panel as MiEmpresa;
use App\Models\Actividad;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCambioDePlan;
use App\Notifications\AvisoPagoFallido;
use App\Notifications\AvisoSolicitudDePlan;
use App\Pagos\PasarelaDePago;
use App\Pagos\PasarelaFalsa;
use App\Services\Empresas\CambiarPlanEmpresa;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
 * Cobro en línea de los planes Starter y Pro con la pasarela falsa: la empresa
 * paga en la página de la pasarela y el plan cambia solo con su aviso (webhook),
 * que puede llegar repetido o desordenado.
 */

beforeEach(function () {
    Notification::fake();
    config(['talentcloud.pagos.pasarela' => 'falsa']);
    $this->app->instance(PasarelaDePago::class, $this->pasarela = new PasarelaFalsa);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->empresa = Empresa::factory()->create(['nombre' => 'Orbita Labs', 'plan' => PlanEmpresa::Gratis, 'prueba_hasta' => now()->addDays(10)]);
        $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create(['name' => 'Olga Ramos']);
        $this->reclutador = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
        $this->admin = User::factory()->admin()->create();
    });
});

/** La empresa ya es cliente de la pasarela (pagó o intentó pagar antes). */
function empresaConClienteDePago(Empresa $empresa, string $cliente = 'cus_orbita'): Empresa
{
    $empresa->forceFill(['pasarela_cliente_id' => $cliente])->save();

    return $empresa;
}

function suscripcionDePrueba(string $id = 'sub_1', PlanEmpresa $plan = PlanEmpresa::Pro, EstadoSuscripcion $estado = EstadoSuscripcion::Activa, bool $cancelaAlFinal = false, string $cliente = 'cus_orbita'): SuscripcionData
{
    return new SuscripcionData($id, $cliente, $plan, Periodicidad::Mensual, $estado, Carbon::now()->addMonth(), $cancelaAlFinal);
}

/** POST /stripe/webhook como lo envía la pasarela falsa. */
function enviarAvisoDePago(array $aviso, string $firma = PasarelaFalsa::FIRMA_VALIDA): TestResponse
{
    return test()->call('POST', '/stripe/webhook', server: ['HTTP_STRIPE_SIGNATURE' => $firma, 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode($aviso));
}

/** Aviso de que cambió la suscripción $id (la pasarela falsa devuelve lo que se le dio con conSuscripcion). */
function avisoDeSuscripcion(string $id = 'sub_1', string $evento = 'evt_1'): array
{
    return ['id' => $evento, 'tipo' => 'customer.subscription.updated', 'suscripcion' => $id, 'cliente' => 'cus_orbita'];
}

// — Contratar —

it('contratar lleva a la página de pago sin cambiar el plan', function () {
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->call('abrirPlanes')
        ->assertSee('Pagar S/ 349 al mes')
        ->assertSee('Pagar S/ 3 348 al año')
        ->assertSee('Pagar S/ 1 188 al año')
        ->assertSee('Solicitar cotización')
        ->call('contratarPlan', 'pro', 'anual')
        ->assertHasNoErrors()
        ->assertRedirect('https://pagos.example/checkout/1');

    $pago = $this->pasarela->pagosIniciados[0];
    expect($pago['plan'])->toBe(PlanEmpresa::Pro)
        ->and($pago['periodicidad'])->toBe(Periodicidad::Anual)
        ->and($pago['pagado'])->toBe(route('mi-empresa', ['pago' => 'confirmando']))
        ->and($pago['cancelado'])->toBe(route('mi-empresa', ['pago' => 'cancelado']));

    // Hasta que la pasarela confirme el pago, sigue en prueba.
    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Gratis)
        ->and($empresa->enPrueba())->toBeTrue()
        ->and($empresa->pasarela_cliente_id)->toBe('cus_falso_1');
});

it('registra a la empresa como cliente de la pasarela una sola vez', function () {
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)->call('contratarPlan', 'starter', 'mensual');
    Livewire::test(MiEmpresa::class)->call('contratarPlan', 'pro', 'mensual');

    expect(array_column($this->pasarela->pagosIniciados, 'cliente'))->toBe(['cus_falso_1', 'cus_falso_1']);
});

it('Gratis y Enterprise no se pagan en línea, y Starter y Pro no se solicitan a mano', function () {
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->call('contratarPlan', 'enterprise', 'anual')->assertHasErrors('plan')
        ->call('contratarPlan', 'gratis', 'mensual')->assertHasErrors('plan')
        ->call('solicitarPlan', 'pro')->assertHasErrors('plan')
        ->assertSee('El plan Pro se contrata pagando en línea.');

    // Enterprise se cotiza: sigue siendo una solicitud al admin SaaS.
    Livewire::test(MiEmpresa::class)->call('solicitarPlan', 'enterprise')->assertHasNoErrors();

    expect($this->pasarela->pagosIniciados)->toBe([])
        ->and($this->empresa->fresh()->plan_solicitado)->toBe(PlanEmpresa::Enterprise);
    Notification::assertSentTo($this->admin, AvisoSolicitudDePlan::class);
});

it('con una suscripción en curso, el cambio de plan y la cancelación van al portal de la pasarela', function () {
    empresaConClienteDePago($this->empresa)->forceFill(['plan' => PlanEmpresa::Pro, 'prueba_hasta' => null, 'pasarela_suscripcion_id' => 'sub_1', 'suscripcion_estado' => EstadoSuscripcion::Activa, 'suscripcion_periodicidad' => Periodicidad::Mensual, 'suscripcion_renueva_at' => now()->addDays(20)])->save();
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)
        ->assertSee('Pagas S/ 349 al mes más IGV con tarjeta.')
        ->assertSee('Gestionar suscripción')
        ->call('abrirPlanes')
        ->assertSee('Cambiar al plan Starter')
        ->assertSee('Cancelar suscripción')
        ->assertDontSee('Pagar S/ 129 al mes')
        ->call('contratarPlan', 'starter', 'mensual')
        ->assertHasErrors('plan')
        ->assertSee('Tu empresa ya paga el plan Pro.')
        ->call('solicitarPlan', 'gratis')
        ->assertHasErrors('plan')
        ->call('gestionarSuscripcion')
        ->assertRedirect('https://pagos.example/portal/cus_orbita');

    expect($this->pasarela->pagosIniciados)->toBe([]);
});

it('solo la cuenta de empresa paga y gestiona la suscripción', function () {
    empresaConClienteDePago($this->empresa);
    actuarComo($this->reclutador);

    Livewire::test(MiEmpresa::class)->call('contratarPlan', 'pro', 'mensual')->assertForbidden();
    Livewire::test(MiEmpresa::class)->call('gestionarSuscripcion')->assertForbidden();

    expect($this->pasarela->pagosIniciados)->toBe([]);
});

it('al volver de la página de pago explica en qué quedó', function () {
    actuarComo($this->cuenta);

    $this->get(route('mi-empresa', ['pago' => 'cancelado']))->assertOk()->assertSee('No se completó el pago: tu plan no cambió');
    $this->get(route('mi-empresa', ['pago' => 'confirmando']))->assertOk()->assertSee('Estamos confirmando tu pago con Stripe');

    empresaConClienteDePago($this->empresa)->forceFill(['plan' => PlanEmpresa::Pro, 'suscripcion_estado' => EstadoSuscripcion::Activa, 'suscripcion_periodicidad' => Periodicidad::Mensual])->save();
    actuarComo($this->cuenta->fresh()); // Como en una petición real: la empresa se lee de nuevo.
    $this->get(route('mi-empresa', ['pago' => 'confirmando']))->assertOk()->assertSee('Pago confirmado: tu empresa ya tiene el plan Pro.');
});

// — Avisos de la pasarela —

it('rechaza un aviso sin firma válida sin tocar nada', function () {
    empresaConClienteDePago($this->empresa);
    $this->pasarela->conSuscripcion(suscripcionDePrueba());

    enviarAvisoDePago(avisoDeSuscripcion(), 'firma-falsa')->assertStatus(400);

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);
});

it('sin pasarela configurada no atiende avisos', function () {
    config(['talentcloud.pagos.pasarela' => null]);

    enviarAvisoDePago(avisoDeSuscripcion())->assertNotFound();
});

it('el pago confirmado da el plan, termina la prueba, atiende la solicitud y avisa a la cuenta de empresa', function () {
    empresaConClienteDePago($this->empresa)->forceFill(['plan_solicitado' => PlanEmpresa::Enterprise, 'plan_solicitado_at' => now()])->save();
    $this->pasarela->conSuscripcion(suscripcionDePrueba());

    enviarAvisoDePago(['id' => 'evt_1', 'tipo' => 'checkout.session.completed', 'suscripcion' => 'sub_1', 'cliente' => 'cus_orbita'])->assertOk();

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Pro)
        ->and($empresa->enPrueba())->toBeFalse()
        ->and($empresa->prueba_hasta)->toBeNull()
        ->and($empresa->plan_solicitado)->toBeNull()
        ->and($empresa->pasarela_suscripcion_id)->toBe('sub_1')
        ->and($empresa->suscripcion_estado)->toBe(EstadoSuscripcion::Activa)
        ->and($empresa->suscripcion_periodicidad)->toBe(Periodicidad::Mensual)
        ->and($empresa->suscripcion_renueva_at)->not->toBeNull();

    Notification::assertSentTo($this->cuenta, AvisoCambioDePlan::class, fn (AvisoCambioDePlan $n) => $n->texto === 'Orbita Labs pasó al plan Pro.');
    Notification::assertNotSentTo($this->reclutador, AvisoCambioDePlan::class);

    $actividad = app(TenantContext::class)->ejecutarPara($this->empresa->id, fn () => Actividad::sole());
    expect($actividad->descripcion)->toBe('Orbita Labs pasó del plan Gratis al plan Pro.')
        ->and($actividad->actor_id)->toBeNull();
});

it('un aviso repetido no cambia nada ni avisa otra vez', function () {
    empresaConClienteDePago($this->empresa);
    $this->pasarela->conSuscripcion(suscripcionDePrueba());

    enviarAvisoDePago(avisoDeSuscripcion())->assertOk();
    enviarAvisoDePago(avisoDeSuscripcion())->assertOk();
    enviarAvisoDePago(avisoDeSuscripcion(evento: 'evt_2'))->assertOk();

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Pro);
    Notification::assertSentToTimes($this->cuenta, AvisoCambioDePlan::class, 1);
    expect(app(TenantContext::class)->ejecutarPara($this->empresa->id, fn () => Actividad::count()))->toBe(1);
});

it('el cambio de plan en el portal llega como aviso y se aplica', function () {
    empresaConClienteDePago($this->empresa);
    enviarAvisoDePago(avisoDeSuscripcion())->assertOk(); // Aún no existe en la pasarela: no hace nada.
    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);

    $this->pasarela->conSuscripcion(suscripcionDePrueba(plan: PlanEmpresa::Pro));
    enviarAvisoDePago(avisoDeSuscripcion(evento: 'evt_2'));
    $this->pasarela->conSuscripcion(suscripcionDePrueba(plan: PlanEmpresa::Starter));
    enviarAvisoDePago(avisoDeSuscripcion(evento: 'evt_3'));

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Starter);
});

it('cancelar al final del periodo mantiene el plan hasta que la suscripción termina; después, Gratis sin borrar nada', function () {
    empresaConClienteDePago($this->empresa);
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->count(3)->create());

    $this->pasarela->conSuscripcion(suscripcionDePrueba());
    enviarAvisoDePago(avisoDeSuscripcion('sub_1', 'evt_1'));

    $this->pasarela->conSuscripcion(suscripcionDePrueba(cancelaAlFinal: true));
    enviarAvisoDePago(avisoDeSuscripcion('sub_1', 'evt_2'));
    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Pro);

    actuarComo($this->cuenta);
    Livewire::test(MiEmpresa::class)->assertSee('Cancelaste tu suscripción: tendrás el plan Pro hasta el');

    $this->pasarela->conSuscripcion(suscripcionDePrueba(estado: EstadoSuscripcion::Terminada, cancelaAlFinal: true));
    enviarAvisoDePago(['id' => 'evt_3', 'tipo' => 'customer.subscription.deleted', 'suscripcion' => 'sub_1', 'cliente' => 'cus_orbita']);

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Gratis)
        ->and($empresa->tieneSuscripcionVigente())->toBeFalse()
        ->and(app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::query()->where('empresa_id', $empresa->id)->where('estado', EstadoVacante::Abierta)->count()))->toBe(3);
    Notification::assertSentTo($this->cuenta, AvisoCambioDePlan::class, fn (AvisoCambioDePlan $n) => $n->texto === 'Orbita Labs pasó al plan Gratis.');
});

it('un aviso tardío de una suscripción anterior no deshace la actual', function () {
    empresaConClienteDePago($this->empresa);
    $this->pasarela->conSuscripcion(suscripcionDePrueba('sub_nueva', PlanEmpresa::Starter))
        ->conSuscripcion(suscripcionDePrueba('sub_vieja', estado: EstadoSuscripcion::Terminada));

    enviarAvisoDePago(avisoDeSuscripcion('sub_nueva', 'evt_1'));
    enviarAvisoDePago(avisoDeSuscripcion('sub_vieja', 'evt_2'));

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Starter)
        ->and($empresa->pasarela_suscripcion_id)->toBe('sub_nueva');
});

it('una suscripción que nunca se pagó no toca el plan aplicado por el admin', function () {
    empresaConClienteDePago($this->empresa)->forceFill(['plan' => PlanEmpresa::Enterprise, 'prueba_hasta' => null])->save();

    // Pago abandonado en la verificación 3D Secure: la suscripción queda incompleta y luego vence.
    $this->pasarela->conSuscripcion(suscripcionDePrueba(estado: EstadoSuscripcion::Incompleta));
    enviarAvisoDePago(avisoDeSuscripcion(evento: 'evt_1'));
    $this->pasarela->conSuscripcion(suscripcionDePrueba(estado: EstadoSuscripcion::Terminada));
    enviarAvisoDePago(avisoDeSuscripcion(evento: 'evt_2'));

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Enterprise);
    Notification::assertNothingSentTo($this->cuenta);
});

it('un cobro fallido mantiene el plan y avisa a la cuenta de empresa una sola vez', function () {
    empresaConClienteDePago($this->empresa);
    $this->pasarela->conSuscripcion(suscripcionDePrueba());
    enviarAvisoDePago(avisoDeSuscripcion());

    $this->pasarela->conSuscripcion(suscripcionDePrueba(estado: EstadoSuscripcion::PagoPendiente));
    $fallido = ['id' => 'evt_fallo', 'tipo' => 'invoice.payment_failed', 'suscripcion' => 'sub_1', 'cliente' => 'cus_orbita', 'pago_fallido' => true];
    enviarAvisoDePago($fallido)->assertOk();
    enviarAvisoDePago($fallido)->assertOk();

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Pro)
        ->and($empresa->suscripcion_estado)->toBe(EstadoSuscripcion::PagoPendiente);
    Notification::assertSentToTimes($this->cuenta, AvisoPagoFallido::class, 1);
    Notification::assertSentTo($this->cuenta, AvisoPagoFallido::class, fn (AvisoPagoFallido $n, array $canales) => $n->texto === 'No pudimos cobrar el plan Pro de Orbita Labs. Actualiza tu tarjeta en «Gestionar suscripción».' && in_array('mail', $canales, true));
    Notification::assertNotSentTo($this->reclutador, AvisoPagoFallido::class);

    actuarComo($this->cuenta);
    Livewire::test(MiEmpresa::class)->assertSee('No pudimos cobrar la última cuota de tu plan.');
});

it('tras el último reintento fallido la empresa pasa a Gratis sin aviso de cobro fallido', function () {
    empresaConClienteDePago($this->empresa);
    $this->pasarela->conSuscripcion(suscripcionDePrueba());
    enviarAvisoDePago(avisoDeSuscripcion());

    $this->pasarela->conSuscripcion(suscripcionDePrueba(estado: EstadoSuscripcion::Terminada));
    enviarAvisoDePago(['id' => 'evt_ultimo', 'tipo' => 'invoice.payment_failed', 'suscripcion' => 'sub_1', 'cliente' => 'cus_orbita', 'pago_fallido' => true]);

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);
    Notification::assertNotSentTo($this->cuenta, AvisoPagoFallido::class);
});

// — Admin SaaS —

it('el admin ve el pago con tarjeta y no puede cambiar un plan que la empresa paga', function () {
    empresaConClienteDePago($this->empresa)->forceFill(['plan' => PlanEmpresa::Pro, 'prueba_hasta' => null, 'pasarela_suscripcion_id' => 'sub_1', 'suscripcion_estado' => EstadoSuscripcion::Activa, 'suscripcion_periodicidad' => Periodicidad::Anual, 'suscripcion_renueva_at' => now()->addMonths(6)])->save();
    actuarComo($this->admin);

    Livewire::test(Empresas::class)
        ->assertSee('Tarjeta · Al día')
        ->call('verDetalle', $this->empresa->id)
        ->assertSee('Pago con tarjeta: S/ 3 348 al año más IGV, al día; se renueva el')
        ->assertDontSeeHtml('wire:click="abrirCambioDePlan(');

    expect(fn () => app(CambiarPlanEmpresa::class)->ejecutar($this->empresa, PlanEmpresa::Enterprise, $this->admin))
        ->toThrow(OperacionNoPermitida::class, 'paga el plan Pro con tarjeta');
    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Pro);
});
