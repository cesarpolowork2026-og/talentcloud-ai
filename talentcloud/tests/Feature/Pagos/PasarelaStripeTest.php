<?php

use App\Enums\EstadoSuscripcion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Pagos\AvisoNoValido;
use App\Pagos\PasarelaStripe;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;
use Stripe\Subscription;

/*
 * Adaptador de Stripe sin salir a internet: el cliente HTTP de stripe-php se
 * sustituye por uno que guarda las peticiones, y los avisos se firman como lo
 * hace Stripe (HMAC-SHA256 del instante y el cuerpo con el secreto del webhook).
 */

const SECRETO_WEBHOOK_DE_PRUEBA = 'whsec_prueba';

/** Precios de prueba: plan => periodicidad => id. */
function preciosDeStripeDePrueba(): array
{
    return [
        'starter' => ['mensual' => 'price_starter_mes', 'anual' => 'price_starter_anio'],
        'pro' => ['mensual' => 'price_pro_mes', 'anual' => 'price_pro_anio'],
    ];
}

function pasarelaStripeDePrueba(): PasarelaStripe
{
    return new PasarelaStripe(new StripeClient('sk_test_prueba'), preciosDeStripeDePrueba(), 'txr_igv', SECRETO_WEBHOOK_DE_PRUEBA);
}

/** Firma de Stripe para $cuerpo: "t=<instante>,v1=<hmac>". */
function firmaDeStripe(string $cuerpo, ?int $instante = null, string $secreto = SECRETO_WEBHOOK_DE_PRUEBA): string
{
    $instante ??= time();

    return "t={$instante},v1=".hash_hmac('sha256', "{$instante}.{$cuerpo}", $secreto);
}

/** Cuerpo JSON de un evento de Stripe con $objeto en data.object. */
function eventoDeStripe(string $tipo, array $objeto, string $id = 'evt_1'): string
{
    return (string) json_encode(['id' => $id, 'object' => 'event', 'type' => $tipo, 'data' => ['object' => $objeto]]);
}

/** Cliente HTTP falso de stripe-php: guarda cada petición y responde con $respuesta. */
function clienteHttpDeStripeFalso(array $respuesta): ClientInterface
{
    return new class($respuesta) implements ClientInterface
    {
        /** @var list<array{metodo: string, url: string, params: array}> */
        public array $peticiones = [];

        public function __construct(private readonly array $respuesta) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->peticiones[] = ['metodo' => $method, 'url' => $absUrl, 'params' => $params];

            return [(string) json_encode($this->respuesta), 200, []];
        }
    };
}

afterEach(fn () => ApiRequestor::setHttpClient(null));

it('crea la sesión de Stripe Checkout con el precio, el IGV y la empresa', function () {
    ApiRequestor::setHttpClient($http = clienteHttpDeStripeFalso(['id' => 'cs_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']));
    $empresa = Empresa::factory()->create();

    $url = pasarelaStripeDePrueba()->urlDePago('cus_1', $empresa, PlanEmpresa::Pro, Periodicidad::Anual, 'https://app.test/ok', 'https://app.test/no');

    expect($url)->toBe('https://checkout.stripe.com/c/pay/cs_1')
        ->and($http->peticiones)->toHaveCount(1)
        ->and($http->peticiones[0]['url'])->toEndWith('/v1/checkout/sessions')
        ->and($http->peticiones[0]['params'])->toMatchArray([
            'mode' => 'subscription',
            'customer' => 'cus_1',
            'client_reference_id' => (string) $empresa->id,
            'line_items' => [['price' => 'price_pro_anio', 'quantity' => 1, 'tax_rates' => ['txr_igv']]],
            'subscription_data' => ['metadata' => ['empresa_id' => (string) $empresa->id]],
            'success_url' => 'https://app.test/ok',
            'cancel_url' => 'https://app.test/no',
        ]);
});

it('lee un aviso firmado por Stripe y rechaza uno alterado, vencido o con otro secreto', function () {
    $pasarela = pasarelaStripeDePrueba();
    $cuerpo = eventoDeStripe('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'customer' => 'cus_1', 'subscription' => 'sub_1']);

    $aviso = $pasarela->leerAviso($cuerpo, firmaDeStripe($cuerpo));

    expect($aviso->id)->toBe('evt_1')
        ->and($aviso->suscripcionId)->toBe('sub_1')
        ->and($aviso->clienteId)->toBe('cus_1')
        ->and($aviso->pagoFallido)->toBeFalse();

    $alterado = str_replace('sub_1', 'sub_2', $cuerpo);
    expect(fn () => $pasarela->leerAviso($alterado, firmaDeStripe($cuerpo)))->toThrow(AvisoNoValido::class)
        ->and(fn () => $pasarela->leerAviso($cuerpo, firmaDeStripe($cuerpo, time() - 3600)))->toThrow(AvisoNoValido::class)
        ->and(fn () => $pasarela->leerAviso($cuerpo, firmaDeStripe($cuerpo, secreto: 'whsec_otro')))->toThrow(AvisoNoValido::class)
        ->and(fn () => $pasarela->leerAviso($cuerpo, ''))->toThrow(AvisoNoValido::class);
});

it('del aviso de cobro fallido toma la suscripción de la factura', function () {
    $cuerpo = eventoDeStripe('invoice.payment_failed', [
        'id' => 'in_1', 'object' => 'invoice', 'customer' => 'cus_1',
        'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_1']],
    ]);

    $aviso = pasarelaStripeDePrueba()->leerAviso($cuerpo, firmaDeStripe($cuerpo));

    expect($aviso->pagoFallido)->toBeTrue()
        ->and($aviso->suscripcionId)->toBe('sub_1')
        ->and($aviso->clienteId)->toBe('cus_1');
});

it('ignora los avisos que no cambian la suscripción', function () {
    $cuerpo = eventoDeStripe('customer.updated', ['id' => 'cus_1', 'object' => 'customer']);

    $aviso = pasarelaStripeDePrueba()->leerAviso($cuerpo, firmaDeStripe($cuerpo));

    expect($aviso->suscripcionId)->toBeNull()->and($aviso->pagoFallido)->toBeFalse();
});

it('traduce la suscripción de Stripe: plan, periodicidad, estado y fin del periodo', function () {
    $fin = now()->addMonth()->startOfSecond();
    $suscripcion = fn (array $cambios) => Subscription::constructFrom(array_merge([
        'id' => 'sub_1',
        'object' => 'subscription',
        'customer' => 'cus_1',
        'status' => 'active',
        'cancel_at' => null,
        'cancel_at_period_end' => false,
        'items' => ['object' => 'list', 'data' => [['id' => 'si_1', 'object' => 'subscription_item', 'current_period_end' => $fin->timestamp, 'price' => ['id' => 'price_starter_mes', 'object' => 'price']]]],
    ], $cambios));
    $pasarela = pasarelaStripeDePrueba();

    $datos = $pasarela->datosDe($suscripcion([]));
    expect($datos->plan)->toBe(PlanEmpresa::Starter)
        ->and($datos->periodicidad)->toBe(Periodicidad::Mensual)
        ->and($datos->estado)->toBe(EstadoSuscripcion::Activa)
        ->and($datos->renuevaAt?->timestamp)->toBe($fin->timestamp)
        ->and($datos->cancelaAlFinal)->toBeFalse();

    // El portal programa la cancelación con cancel_at: termina ese día.
    $cancela = $fin->copy()->subDays(3);
    $datos = $pasarela->datosDe($suscripcion(['cancel_at' => $cancela->timestamp]));
    expect($datos->cancelaAlFinal)->toBeTrue()->and($datos->renuevaAt?->timestamp)->toBe($cancela->timestamp);

    expect($pasarela->datosDe($suscripcion(['status' => 'past_due']))->estado)->toBe(EstadoSuscripcion::PagoPendiente)
        ->and($pasarela->datosDe($suscripcion(['status' => 'unpaid']))->estado)->toBe(EstadoSuscripcion::PagoPendiente)
        ->and($pasarela->datosDe($suscripcion(['status' => 'incomplete']))->estado)->toBe(EstadoSuscripcion::Incompleta)
        ->and($pasarela->datosDe($suscripcion(['status' => 'canceled']))->estado)->toBe(EstadoSuscripcion::Terminada)
        ->and($pasarela->datosDe($suscripcion(['status' => 'incomplete_expired']))->estado)->toBe(EstadoSuscripcion::Terminada);
});

it('no adivina el plan de un precio que no está configurado', function () {
    $suscripcion = Subscription::constructFrom([
        'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active',
        'items' => ['object' => 'list', 'data' => [['id' => 'si_1', 'object' => 'subscription_item', 'price' => ['id' => 'price_desconocido', 'object' => 'price']]]],
    ]);

    expect(fn () => pasarelaStripeDePrueba()->datosDe($suscripcion))->toThrow(RuntimeException::class, 'price_desconocido');
});
