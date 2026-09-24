<?php

namespace App\Events;

use App\Models\Entrevista;
use Illuminate\Foundation\Events\Dispatchable;

/** Se programó una entrevista (actividad y notificaciones al candidato y al entrevistador, día 17). */
class EntrevistaProgramada
{
    use Dispatchable;

    public function __construct(public readonly Entrevista $entrevista) {}
}
