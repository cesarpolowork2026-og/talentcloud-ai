<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Reclamacion;
use App\Models\User;

/**
 * Libro de Reclamaciones: cualquiera presenta una hoja (página pública, sin
 * Policy); solo el admin SaaS las ve y las responde. Las hojas no se editan ni
 * se eliminan: se conservan tal como se presentaron.
 */
class ReclamacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function view(User $user, Reclamacion $reclamacion): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function responder(User $user, Reclamacion $reclamacion): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function update(User $user, Reclamacion $reclamacion): bool
    {
        return false;
    }

    public function delete(User $user, Reclamacion $reclamacion): bool
    {
        return false;
    }
}
