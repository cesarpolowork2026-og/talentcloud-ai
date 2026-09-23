<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;
use App\Models\Vacante;

/**
 * Vacantes: el personal de selección de la empresa (empresa y reclutador) y
 * el admin SaaS. El filtro por empresa ya oculta las ajenas; la Policy lo
 * vuelve a comprobar como segunda barrera.
 */
class VacantePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol()?->esPersonalDeSeleccion() ?? false;
    }

    public function view(User $user, Vacante $vacante): bool
    {
        return $this->alcanza($user, $vacante);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Vacante $vacante): bool
    {
        return $this->alcanza($user, $vacante);
    }

    public function cambiarEstado(User $user, Vacante $vacante): bool
    {
        return $this->alcanza($user, $vacante);
    }

    public function delete(User $user, Vacante $vacante): bool
    {
        // Las vacantes se cierran, no se eliminan: conservan su historial de postulaciones.
        return false;
    }

    private function alcanza(User $user, Vacante $vacante): bool
    {
        if ($user->tieneRol(Rol::Admin)) {
            return true;
        }

        return $this->viewAny($user) && $user->empresa_id === $vacante->empresa_id;
    }
}
