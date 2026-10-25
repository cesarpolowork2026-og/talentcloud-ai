<?php

namespace App\Events;

use App\Models\Entrevista;
use Illuminate\Foundation\Events\Dispatchable;

/** El equipo confirmó una entrevista. */
class EntrevistaConfirmada
{
    use Dispatchable;

    public function __construct(public readonly Entrevista $entrevista) {}
}
