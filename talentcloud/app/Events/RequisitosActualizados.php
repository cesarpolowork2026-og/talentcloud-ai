<?php

namespace App\Events;

use App\Models\Vacante;
use Illuminate\Foundation\Events\Dispatchable;

/** Se agregó, editó o quitó un requisito: el matching de la vacante debe recalcularse. */
class RequisitosActualizados
{
    use Dispatchable;

    public function __construct(public readonly Vacante $vacante) {}
}
