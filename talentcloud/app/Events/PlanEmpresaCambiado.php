<?php

namespace App\Events;

use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** El admin SaaS cambió el plan de una empresa: aviso a su cuenta y línea en la actividad. */
class PlanEmpresaCambiado
{
    use Dispatchable;

    public function __construct(
        public readonly Empresa $empresa,
        public readonly PlanEmpresa $anterior,
        public readonly ?User $actor,
    ) {}
}
