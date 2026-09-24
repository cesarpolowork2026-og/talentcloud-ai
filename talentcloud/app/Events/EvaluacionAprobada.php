<?php

namespace App\Events;

use App\Models\Evaluacion;
use Illuminate\Foundation\Events\Dispatchable;

/** Una evaluación pasó a "Aprobado" (actividad reciente del día 17: "Marta Solís aprobó la evaluación de…"). */
class EvaluacionAprobada
{
    use Dispatchable;

    public function __construct(public readonly Evaluacion $evaluacion) {}
}
