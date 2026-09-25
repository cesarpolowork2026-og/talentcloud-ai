<?php

namespace App\Enums;

/**
 * Plan de suscripción de la empresa. Fija los límites de uso; el cobro
 * (Stripe + Laravel Cashier) está fuera del alcance actual.
 */
enum PlanEmpresa: string
{
    use EsEnumDeEstado;

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

    /** Vacantes abiertas o en revisión a la vez (null = sin límite). */
    public function limiteVacantesActivas(): ?int
    {
        return match ($this) {
            self::Starter => 3,
            self::Pro => 15,
            self::Enterprise => null,
        };
    }

    /** Usuarios activos de la empresa, su cuenta incluida (null = sin límite). */
    public function limiteUsuarios(): ?int
    {
        return match ($this) {
            self::Starter => 3,
            self::Pro => 10,
            self::Enterprise => null,
        };
    }

    /** "Hasta 3 vacantes activas y 3 usuarios." */
    public function descripcionDeLimites(): string
    {
        $vacantes = $this->limiteVacantesActivas();
        $usuarios = $this->limiteUsuarios();

        if ($vacantes === null && $usuarios === null) {
            return 'Vacantes activas y usuarios sin límite.';
        }

        return ucfirst(($vacantes === null ? 'vacantes activas sin límite' : "hasta {$vacantes} vacantes activas")
            .' y '.($usuarios === null ? 'usuarios sin límite' : "{$usuarios} usuarios")).'.';
    }
}
