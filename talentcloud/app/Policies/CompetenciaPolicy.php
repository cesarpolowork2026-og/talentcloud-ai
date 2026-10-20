<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Competencia;
use App\Models\User;

/**
 * El catálogo de competencias es compartido por todas las empresas: lo administra el admin SaaS. Las empresas lo
 * usan (elegir o dar de alta una competencia al definir un requisito), pero no lo editan.
 */
class CompetenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function create(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function update(User $user, Competencia $competencia): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function delete(User $user, Competencia $competencia): bool
    {
        return $user->tieneRol(Rol::Admin);
    }
}
