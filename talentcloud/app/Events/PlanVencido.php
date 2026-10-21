<?php

namespace App\Events;

use App\Models\Empresa;
use Illuminate\Foundation\Events\Dispatchable;

/** Venció el plan asignado a mano de una empresa y ahora rige el Gratis: aviso a su cuenta. */
class PlanVencido
{
    use Dispatchable;

    public function __construct(public readonly Empresa $empresa) {}
}
