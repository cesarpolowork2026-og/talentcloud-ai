<?php

namespace App\Events;

use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** La cuenta de empresa pidió otro plan: aviso al admin SaaS y línea en la actividad. */
class PlanSolicitado
{
    use Dispatchable;

    public function __construct(
        public readonly Empresa $empresa,
        public readonly PlanEmpresa $plan,
        public readonly User $solicitante,
    ) {}
}
