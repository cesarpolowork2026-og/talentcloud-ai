<?php

namespace App\Events;

use App\Models\Empresa;
use Illuminate\Foundation\Events\Dispatchable;

/** Al plan asignado a mano de una empresa le quedan pocos días: aviso a su cuenta para coordinar la renovación. */
class PlanPorVencer
{
    use Dispatchable;

    public function __construct(public readonly Empresa $empresa) {}
}
