<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Candidato;
use App\Models\User;
use App\Repositories\Contracts\CandidatoRepository;

/**
 * Candidatos: el admin ve todos; una empresa (cuenta o reclutador), los que
 * postularon a sus vacantes o que ella registró; el postulante, solo el suyo.
 */
class CandidatoPolicy
{
    public function __construct(private readonly CandidatoRepository $candidatos) {}

    public function viewAny(User $user): bool
    {
        return $user->rol()?->esPersonalDeSeleccion() ?? false;
    }

    public function view(User $user, Candidato $candidato): bool
    {
        if ($user->tieneRol(Rol::Admin)) {
            return true;
        }

        if ($user->tieneRol(Rol::Postulante)) {
            return $candidato->user_id === $user->id;
        }

        return $this->viewAny($user)
            && $user->empresa_id !== null
            && $this->candidatos->esVisibleParaEmpresa($candidato, $user->empresa_id);
    }

    /** Registrar candidatos: el personal de una empresa (el admin no recluta). */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->empresa_id !== null;
    }

    /** Competencias manuales y nuevos CV. */
    public function update(User $user, Candidato $candidato): bool
    {
        return $this->view($user, $candidato);
    }
}
