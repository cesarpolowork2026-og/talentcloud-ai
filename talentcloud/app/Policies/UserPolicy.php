<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/** Gestión de usuarios internos desde "Mi empresa". */
class UserPolicy
{
    /** «Usuarios» y «Registro de acciones» de la plataforma: solo el admin SaaS. */
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

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

    /**
     * Desde «Usuarios» de la plataforma: el admin SaaS puede desactivar o reactivar al personal de cualquier empresa
     * (soporte), nunca a otro administrador ni a sí mismo.
     */
    public function gestionarDesdeLaPlataforma(User $actor, User $objetivo): bool
    {
        return $actor->tieneRol(Rol::Admin)
            && $objetivo->empresa_id !== null
            && ! $objetivo->tieneRol(Rol::Admin)
            && $actor->id !== $objetivo->id;
    }
}
