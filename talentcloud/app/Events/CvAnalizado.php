<?php

namespace App\Events;

use App\Models\Cv;
use Illuminate\Foundation\Events\Dispatchable;

/** El servicio de IA analizó el CV: hay datos estructurados, fragmentos con embedding y competencias extraídas. */
class CvAnalizado
{
    use Dispatchable;

    public function __construct(public readonly Cv $cv) {}
}
