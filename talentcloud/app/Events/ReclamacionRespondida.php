<?php

namespace App\Events;

use App\Models\Reclamacion;
use Illuminate\Foundation\Events\Dispatchable;

/** El admin SaaS respondió una hoja: la respuesta se envía al consumidor. */
class ReclamacionRespondida
{
    use Dispatchable;

    public function __construct(public readonly Reclamacion $reclamacion) {}
}
