<?php

namespace App\Events;

use App\Models\Entrevista;
use Illuminate\Foundation\Events\Dispatchable;

/** El equipo marcó una entrevista como realizada. */
class EntrevistaRealizada
{
    use Dispatchable;

    public function __construct(public readonly Entrevista $entrevista) {}
}
