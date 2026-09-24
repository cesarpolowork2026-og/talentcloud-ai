<?php

namespace App\Events;

use App\Models\Cv;
use Illuminate\Foundation\Events\Dispatchable;

/** El servicio de IA analizó el CV: hay datos estructurados, fragmentos con embedding y competencias extraídas. */
class CvAnalizado
{
    use Dispatchable;

    /**
     * @param  bool  $primeraVez  false al reanalizarlo (p. ej. talentcloud:reindexar-embeddings): no se vuelve a avisar.
     */
    public function __construct(public readonly Cv $cv, public readonly bool $primeraVez = true) {}
}
