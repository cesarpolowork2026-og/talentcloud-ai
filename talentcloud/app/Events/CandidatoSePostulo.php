<?php

namespace App\Events;

use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * El postulante se postuló por su cuenta ("Postular con mi CV"). Se despacha
 * además de PostulacionCreada: este solo sirve para avisar a la empresa, que no
 * creó la postulación.
 */
class CandidatoSePostulo
{
    use Dispatchable;

    public function __construct(
        public readonly Postulacion $postulacion,
        public readonly User $postulante,
    ) {}
}
