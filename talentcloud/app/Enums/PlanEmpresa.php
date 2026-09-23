<?php

namespace App\Enums;

/**
 * Plan de suscripción de la empresa. Por ahora es informativo: el cobro
 * (Stripe + Laravel Cashier) está fuera del alcance actual.
 */
enum PlanEmpresa: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Enterprise => 'Enterprise',
        };
    }
}
