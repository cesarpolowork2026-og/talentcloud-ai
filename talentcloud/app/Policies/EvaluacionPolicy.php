<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Evaluacion;
use App\Models\User;

/**
 * Evaluaciones: las ve el personal de selección de la empresa (y el admin SaaS).
 * Evalúa quien pertenece a una empresa; edita su autor o la cuenta de empresa.
 */
class EvaluacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol()?->esPersonalDeSeleccion() ?? false;
    }

    /** El admin SaaS no evalúa: no forma parte del proceso de ninguna empresa. */
    public function create(User $user): bool
    {
        return in_array($user->rol(), [Rol::Empresa, Rol::Reclutador], true);
    }

    public function view(User $user, Evaluacion $evaluacion): bool
    {
        return $user->tieneRol(Rol::Admin)
            || ($this->viewAny($user) && $user->empresa_id === $evaluacion->empresa_id);
    }

    public function update(User $user, Evaluacion $evaluacion): bool
    {
        if ($user->empresa_id !== $evaluacion->empresa_id) {
            return false;
        }

        return $user->id === $evaluacion->evaluador_id || $user->tieneRol(Rol::Empresa);
    }
}
