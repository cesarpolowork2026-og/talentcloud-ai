<?php

namespace App\Events;

use App\Models\Entrevista;
use Illuminate\Foundation\Events\Dispatchable;

/** Se canceló una entrevista: el prototipo promete avisar al candidato y al entrevistador (día 17). */
class EntrevistaCancelada
{
    use Dispatchable;

    public function __construct(public readonly Entrevista $entrevista) {}
}
