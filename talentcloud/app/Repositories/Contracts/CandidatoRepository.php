<?php

namespace App\Repositories\Contracts;

use App\Models\Candidato;
use App\Models\User;

interface CandidatoRepository
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Candidato;

    public function deUsuario(User $user): ?Candidato;
}
