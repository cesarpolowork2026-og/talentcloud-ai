<?php

namespace App\Events;

use App\Models\Cv;
use Illuminate\Foundation\Events\Dispatchable;

/** Se extrajo el texto de un CV (o falló). Punto de enganche del análisis con IA. */
class CvProcesado
{
    use Dispatchable;

    public function __construct(public readonly Cv $cv) {}
}
