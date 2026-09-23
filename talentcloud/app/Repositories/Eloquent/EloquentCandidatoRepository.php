<?php

namespace App\Repositories\Eloquent;

use App\Models\Candidato;
use App\Models\User;
use App\Repositories\Contracts\CandidatoRepository;

class EloquentCandidatoRepository implements CandidatoRepository
{
    public function crear(array $datos): Candidato
    {
        return Candidato::create($datos);
    }

    public function deUsuario(User $user): ?Candidato
    {
        return Candidato::where('user_id', $user->id)->first();
    }
}
