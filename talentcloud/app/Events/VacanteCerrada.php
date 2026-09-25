<?php

namespace App\Events;

use App\Models\Vacante;
use Illuminate\Foundation\Events\Dispatchable;

/** Una vacante pasó a Cerrada: sus candidatos con el proceso abierto reciben aviso. */
class VacanteCerrada
{
    use Dispatchable;

    public function __construct(public readonly Vacante $vacante) {}
}
