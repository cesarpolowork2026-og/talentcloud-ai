<?php

namespace App\Events;

use App\Models\Nota;
use Illuminate\Foundation\Events\Dispatchable;

/** Alguien del equipo dejó una nota interna sobre una postulación (línea en la actividad). */
class NotaAgregada
{
    use Dispatchable;

    public function __construct(public readonly Nota $nota) {}
}
