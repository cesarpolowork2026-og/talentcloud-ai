<?php

namespace App\Events;

use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cambió el plan de una empresa: lo aplicó el admin SaaS (actor) o la pasarela de
 * pago al confirmar un pago o el fin de la suscripción (actor null). Aviso a su
 * cuenta y línea en la actividad.
 */
class PlanEmpresaCambiado
{
    use Dispatchable;

    public function __construct(
        public readonly Empresa $empresa,
        public readonly PlanEmpresa $anterior,
        public readonly ?User $actor,
    ) {}
}
