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

    /** Anotar competencias y postular al candidato: la empresa o el propio postulante (el admin SaaS solo ve). */
    public function update(User $user, Candidato $candidato): bool
    {
        return ! $user->tieneRol(Rol::Admin) && $this->view($user, $candidato);
    }

    /**
     * Subir un CV nuevo cambia el «CV actual» del candidato, y un candidato es global: lo ven y lo analizan todas las
     * empresas con las que tiene un proceso. Por eso solo lo hace el propio postulante, o la empresa que registró a un
     * candidato sin cuenta; una empresa a la que el postulante solo se postuló no puede reemplazar su CV.
     */
    public function subirCv(User $user, Candidato $candidato): bool
    {
        if ($user->tieneRol(Rol::Postulante)) {
            return $candidato->user_id === $user->id;
        }

        return ! $user->tieneRol(Rol::Admin)
            && $candidato->user_id === null
            && $user->empresa_id !== null
            && $candidato->empresa_origen_id === $user->empresa_id;
    }
}
