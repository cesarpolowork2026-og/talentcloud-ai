<?php

namespace App\Events;

use App\Models\Entrevista;
use Illuminate\Foundation\Events\Dispatchable;

/** Una entrevista cambió de fecha: un solo aviso "reprogramada" al candidato y al entrevistador. */
class EntrevistaReprogramada
{
    use Dispatchable;

    /** @param  string  $fechaAnterior  ya formateada ("28 sep · 11:00") */
    public function __construct(
        public readonly Entrevista $entrevista,
        public readonly string $fechaAnterior,
    ) {}
}
