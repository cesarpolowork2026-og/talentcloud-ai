<?php

namespace App\Policies;

use App\Models\Cv;
use App\Models\User;

/** Un CV se ve, descarga o reprocesa si se puede ver a su candidato. */
class CvPolicy
{
    public function __construct(private readonly CandidatoPolicy $candidatos) {}

    public function view(User $user, Cv $cv): bool
    {
        return $cv->candidato !== null && $this->candidatos->view($user, $cv->candidato);
    }

    public function reintentar(User $user, Cv $cv): bool
    {
        return $cv->candidato !== null && $this->candidatos->update($user, $cv->candidato);
    }
}
