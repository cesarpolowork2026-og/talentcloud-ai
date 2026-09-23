<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/** Gestión de usuarios internos desde "Mi empresa". */
class UserPolicy
{
    /**
     * Desactivar o reactivar: la cuenta de empresa, sobre otro usuario de su
     * misma empresa (nunca sobre sí misma, para no quedarse sin administrador).
     */
    public function gestionar(User $actor, User $objetivo): bool
    {
        return $actor->tieneRol(Rol::Empresa)
            && $actor->empresa_id !== null
            && $actor->empresa_id === $objetivo->empresa_id
            && $actor->id !== $objetivo->id;
    }
}
