<?php

namespace App\Pagos;

use App\DTO\AvisoDePago;
use App\DTO\SuscripcionData;
use App\Enums\EstadoSuscripcion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Pasarela de pago con Stripe: Stripe Checkout para pagar (precios recurrentes
 * en soles con el IGV como tasa aparte), el portal de clientes de Stripe para
 * gestionar la suscripción y avisos firmados (webhooks) para confirmar cada
 * cambio. Los precios e IGV se crean en el panel de Stripe (config/talentcloud.php).
 */
class PasarelaStripe implements PasarelaDePago
{
    /**
     * @param  array<string, array<string, string|null>>  $precios  plan => periodicidad => id del precio
     */
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly array $precios,
        private readonly ?string $igv,
        private readonly string $secretoWebhook,
    ) {}

    public static function desdeConfig(): self
    {
        /** @var array{clave_secreta: string|null, secreto_webhook: string|null, igv: string|null, precios: array<string, array<string, string|null>>} $config */
        $config = config('talentcloud.pagos.stripe');

        return new self(
            new StripeClient((string) $config['clave_secreta']),
            $config['precios'],
            $config['igv'],
            (string) $config['secreto_webhook'],
        );
    }

    public function crearCliente(Empresa $empresa, User $contacto): string
    {
        return $this->stripe->customers->create([
            'name' => $empresa->nombre,
            'email' => $contacto->email,
            'preferred_locales' => ['es'],
            'metadata' => ['empresa_id' => (string) $empresa->id],
        ])->id;
    }

    public function urlDePago(string $clienteId, Empresa $empresa, PlanEmpresa $plan, Periodicidad $periodicidad, string $volverPagado, string $volverCancelado): string
    {
        $linea = ['price' => $this->precio($plan, $periodicidad), 'quantity' => 1];
        if ($this->igv !== null) {
            $linea['tax_rates'] = [$this->igv];
        }

        $sesion = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $clienteId,
            'client_reference_id' => (string) $empresa->id,
            'line_items' => [$linea],
            'subscription_data' => ['metadata' => ['empresa_id' => (string) $empresa->id]],
            'locale' => 'es',
            'success_url' => $volverPagado,
            'cancel_url' => $volverCancelado,
        ]);

        return (string) $sesion->url;
    }

    public function urlDelPortal(string $clienteId, string $volver): string
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $clienteId,
            'return_url' => $volver,
            'locale' => 'es',
        ])->url;
    }

    public function suscripcion(string $id): ?SuscripcionData
    {
        try {
            return $this->datosDe($this->stripe->subscriptions->retrieve($id));
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }

            throw $e;
        }
    }

    public function leerAviso(string $cuerpo, string $firma): AvisoDePago
    {
        try {
            $evento = Webhook::constructEvent($cuerpo, $firma, $this->secretoWebhook);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new AvisoNoValido($e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $objeto */
        $objeto = $evento->data->object->toArray();
        $cliente = is_string($objeto['customer'] ?? null) ? $objeto['customer'] : null;

        return match ($evento->type) {
            'checkout.session.completed' => new AvisoDePago($evento->id, $evento->type, $this->texto($objeto['subscription'] ?? null), $cliente),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => new AvisoDePago($evento->id, $evento->type, $this->texto($objeto['id'] ?? null), $cliente),
            // Desde la API 2025-03-31, la suscripción de una factura está en parent.subscription_details.
            'invoice.payment_failed' => new AvisoDePago(
                $evento->id,
                $evento->type,
                $this->texto(data_get($objeto, 'parent.subscription_details.subscription') ?? $objeto['subscription'] ?? null),
                $cliente,
                pagoFallido: true,
            ),
            default => new AvisoDePago($evento->id, $evento->type),
        };
    }

    /** La suscripción de Stripe, en los términos de TalentCloud. */
    public function datosDe(Subscription $suscripcion): SuscripcionData
    {
        /** @var array<string, mixed> $s */
        $s = $suscripcion->toArray();
        $item = data_get($s, 'items.data.0');
        $precio = (string) data_get($item, 'price.id');
        [$plan, $periodicidad] = $this->planDelPrecio($precio)
            ?? throw new RuntimeException("La suscripción {$s['id']} usa el precio {$precio}, que no está en config('talentcloud.pagos.stripe.precios').");

        // El portal de Stripe programa la cancelación con cancel_at (API reciente) o cancel_at_period_end.
        $cancelaEn = $s['cancel_at'] ?? null;
        $finDelPeriodo = data_get($item, 'current_period_end') ?? $s['current_period_end'] ?? null;
        $fin = $cancelaEn ?? $finDelPeriodo;

        return new SuscripcionData(
            id: (string) $s['id'],
            clienteId: (string) $s['customer'],
            plan: $plan,
            periodicidad: $periodicidad,
            estado: match ($s['status']) {
                'active', 'trialing' => EstadoSuscripcion::Activa,
                'past_due', 'unpaid' => EstadoSuscripcion::PagoPendiente,
                'incomplete' => EstadoSuscripcion::Incompleta,
                default => EstadoSuscripcion::Terminada,
            },
            renuevaAt: is_int($fin) ? Carbon::createFromTimestamp($fin, config('app.timezone')) : null,
            cancelaAlFinal: ($s['cancel_at_period_end'] ?? false) === true || $cancelaEn !== null,
        );
    }

    private function precio(PlanEmpresa $plan, Periodicidad $periodicidad): string
    {
        return $this->precios[$plan->value][$periodicidad->value]
            ?? throw new RuntimeException("Falta el precio de Stripe del plan {$plan->value} {$periodicidad->value} (STRIPE_PRECIO_*).");
    }

    /** @return array{0: PlanEmpresa, 1: Periodicidad}|null */
    private function planDelPrecio(string $precio): ?array
    {
        foreach ($this->precios as $plan => $porPeriodicidad) {
            foreach ($porPeriodicidad as $periodicidad => $id) {
                if ($id !== null && $id === $precio) {
                    return [PlanEmpresa::from($plan), Periodicidad::from($periodicidad)];
                }
            }
        }

        return null;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
