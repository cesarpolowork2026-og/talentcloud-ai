<?php

use App\Enums\EstadoSuscripcion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Pagos\PasarelaDePago;
use App\Pagos\PasarelaStripe;
use Illuminate\Testing\TestResponse;
use Stripe\StripeClient;

/*
 * Pagos contra el ENTORNO DE PRUEBAS REAL de Stripe (no contra el cliente falso de PasarelaStripeTest): comprueba que lo
 * que Stripe devuelve de verdad —Checkout, suscripciones, estados de un cobro fallido— sigue entendiéndose bien y que el
 * aviso (webhook) firmado cambia el plan por la ruta HTTP real.
 *
 * NO corre con `composer check`: es opt-in y necesita red y las claves de prueba de `.env`.
 *     STRIPE_LIVE_TESTS=1 php artisan test --filter=StripeEnModoDePrueba
 *
 * Garantías: se niega a ejecutarse si la clave no empieza por `sk_test_`; usa datos sintéticos (correos de example.com);
 * y borra al terminar cada cliente y suscripción que crea. No cobra dinero: las tarjetas son los métodos de prueba de Stripe.
 * El webhook no necesita túnel ni la CLI de Stripe: el aviso se firma aquí con el mismo secreto (`STRIPE_WEBHOOK_SECRET`)
 * y se envía a la ruta de la aplicación; la aplicación vuelve a leer la suscripción real en la API (así funciona siempre).
 */

beforeEach(function () {
    if (getenv('STRIPE_LIVE_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in: STRIPE_LIVE_TESTS=1 (usa el entorno de pruebas de Stripe).');
    }

    $clave = (string) config('talentcloud.pagos.stripe.clave_secreta');
    if (! str_starts_with($clave, 'sk_test_')) {
        throw new RuntimeException('Estas pruebas solo se ejecutan con una clave sk_test_. No se usan claves de producción.');
    }

    foreach (['secreto_webhook', 'precios.starter.mensual'] as $necesaria) {
        if (blank(config("talentcloud.pagos.stripe.{$necesaria}"))) {
            $this->markTestSkipped("Falta configurar {$necesaria} de Stripe en .env.");
        }
    }

    config(['talentcloud.pagos.pasarela' => 'stripe']);
    $this->app->forgetInstance(PasarelaDePago::class);

    $this->stripe = new StripeClient($clave);

    $this->empresa = Empresa::factory()->create(['nombre' => 'Auditoría Stripe SAC', 'plan' => PlanEmpresa::Gratis, 'prueba_hasta' => null]);
    $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create(['email' => 'cuenta.'.uniqid().'@example.com']);
});

afterEach(function () {
    // Limpieza: cada cliente de prueba creado se borra con sus suscripciones.
    foreach (clientesDePrueba() as $clienteId) {
        try {
            foreach ($this->stripe->subscriptions->all(['customer' => $clienteId, 'status' => 'all', 'limit' => 20])->data as $s) {
                if (! in_array($s->status, ['canceled', 'incomplete_expired'], true)) {
                    $this->stripe->subscriptions->cancel($s->id);
                }
            }
            $this->stripe->customers->delete($clienteId);
        } catch (Throwable) {
            // si ya no existe, no importa
        }
    }

    clientesDePrueba(reiniciar: true);
});

/**
 * Los clientes creados en la cuenta de pruebas de Stripe, para borrarlos al terminar. Lista estática: se anota cada cliente
 * en cuanto Stripe lo devuelve, antes de cualquier otra operación que pudiera fallar (así nunca quedan huérfanos).
 *
 * @return list<string>
 */
function clientesDePrueba(?string $nuevo = null, bool $reiniciar = false): array
{
    static $lista = [];

    if ($reiniciar) {
        $lista = [];
    }
    if ($nuevo !== null) {
        $lista[] = $nuevo;
    }

    return $lista;
}

/** Un cliente de Stripe de prueba con esa tarjeta de prueba, vinculado a la empresa como lo hace la aplicación. */
function clienteConTarjeta(string $metodoDePrueba): string
{
    $cliente = test()->stripe->customers->create([
        'email' => 'auditoria.'.uniqid().'@example.com',
        'name' => 'Cliente sintético de auditoría',
        'metadata' => ['origen' => 'auditoria-talentcloud'],
        'payment_method' => $metodoDePrueba,
        'invoice_settings' => ['default_payment_method' => $metodoDePrueba],
    ]);

    clientesDePrueba($cliente->id);
    test()->empresa->forceFill(['pasarela_cliente_id' => $cliente->id])->save();

    return $cliente->id;
}

function suscribir(string $clienteId, string $periodicidad = 'mensual'): string
{
    $precio = (string) config("talentcloud.pagos.stripe.precios.starter.{$periodicidad}");

    return test()->stripe->subscriptions->create(['customer' => $clienteId, 'items' => [['price' => $precio]]])->id;
}

/** Envía a la ruta real un aviso firmado como lo firma Stripe (HMAC-SHA256 de «marca.cuerpo» con el secreto del webhook). */
function enviarAviso(string $tipo, string $clienteId, array $objeto, ?string $secreto = null): TestResponse
{
    $cuerpo = json_encode([
        'id' => 'evt_auditoria_'.uniqid(), 'object' => 'event', 'api_version' => '2025-03-31.basil', 'created' => time(),
        'type' => $tipo, 'livemode' => false, 'data' => ['object' => ['customer' => $clienteId] + $objeto],
    ], JSON_THROW_ON_ERROR);

    $marca = time();
    $firma = hash_hmac('sha256', $marca.'.'.$cuerpo, $secreto ?? (string) config('talentcloud.pagos.stripe.secreto_webhook'));

    return test()->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$marca},v1={$firma}", 'CONTENT_TYPE' => 'application/json'], $cuerpo);
}

it('crea el cliente y la sesión de pago reales de Checkout para el plan', function () {
    $pasarela = PasarelaStripe::desdeConfig();

    $clienteId = $pasarela->crearCliente($this->empresa, $this->cuenta);
    clientesDePrueba($clienteId);

    $url = $pasarela->urlDePago($clienteId, $this->empresa, PlanEmpresa::Starter, Periodicidad::Mensual, 'https://app.example/ok', 'https://app.example/cancelado');

    expect($clienteId)->toStartWith('cus_')
        ->and($url)->toStartWith('https://checkout.stripe.com/');
});

it('un pago satisfactorio: la suscripción real se lee como Starter vigente y el aviso firmado aplica el plan', function () {
    $clienteId = clienteConTarjeta('pm_card_visa');
    $suscripcionId = suscribir($clienteId);

    $datos = PasarelaStripe::desdeConfig()->suscripcion($suscripcionId);

    expect($datos)->not->toBeNull()
        ->and($datos->plan)->toBe(PlanEmpresa::Starter)
        ->and($datos->periodicidad)->toBe(Periodicidad::Mensual)
        ->and($datos->estado)->toBe(EstadoSuscripcion::Activa)
        ->and($datos->estado->esVigente())->toBeTrue();

    expect(PasarelaStripe::desdeConfig()->suscripcionVigenteDe($clienteId)?->id)->toBe($suscripcionId);

    enviarAviso('customer.subscription.created', $clienteId, ['id' => $suscripcionId, 'object' => 'subscription'])->assertOk();

    $this->empresa->refresh();
    expect($this->empresa->plan)->toBe(PlanEmpresa::Starter)
        ->and($this->empresa->suscripcion_estado)->toBe(EstadoSuscripcion::Activa);
});

it('un pago fallido (tarjeta que rechaza el cobro) no activa el plan', function () {
    $clienteId = clienteConTarjeta('pm_card_chargeCustomerFail');
    $suscripcionId = suscribir($clienteId);

    $datos = PasarelaStripe::desdeConfig()->suscripcion($suscripcionId);

    // Stripe deja la suscripción sin pagar: no es vigente.
    expect($datos->estado->esVigente())->toBeFalse();

    enviarAviso('customer.subscription.created', $clienteId, ['id' => $suscripcionId, 'object' => 'subscription'])->assertOk();
    enviarAviso('invoice.payment_failed', $clienteId, ['object' => 'invoice', 'subscription' => $suscripcionId])->assertOk();

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);
});

it('cancelar la suscripción en Stripe devuelve a la empresa al plan Gratis sin borrar nada', function () {
    $clienteId = clienteConTarjeta('pm_card_visa');
    $suscripcionId = suscribir($clienteId);
    enviarAviso('customer.subscription.created', $clienteId, ['id' => $suscripcionId, 'object' => 'subscription'])->assertOk();
    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Starter);

    $this->stripe->subscriptions->cancel($suscripcionId);
    enviarAviso('customer.subscription.deleted', $clienteId, ['id' => $suscripcionId, 'object' => 'subscription'])->assertOk();

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis)
        ->and(Empresa::query()->whereKey($this->empresa->id)->exists())->toBeTrue();
});

it('el mismo aviso entregado dos veces no cambia nada la segunda vez', function () {
    $clienteId = clienteConTarjeta('pm_card_visa');
    $suscripcionId = suscribir($clienteId);

    $cuerpo = ['id' => $suscripcionId, 'object' => 'subscription'];
    enviarAviso('customer.subscription.created', $clienteId, $cuerpo)->assertOk();
    $actualizada = $this->empresa->fresh()->updated_at;

    enviarAviso('customer.subscription.updated', $clienteId, $cuerpo)->assertOk();

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Starter)
        ->and($this->empresa->fresh()->updated_at->equalTo($actualizada))->toBeTrue();
});

it('rechaza con 400 un aviso firmado con otro secreto y no toca nada', function () {
    $clienteId = clienteConTarjeta('pm_card_visa');
    $suscripcionId = suscribir($clienteId);

    enviarAviso('customer.subscription.created', $clienteId, ['id' => $suscripcionId, 'object' => 'subscription'], 'whsec_otro_secreto_distinto')
        ->assertStatus(400);

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);
});
