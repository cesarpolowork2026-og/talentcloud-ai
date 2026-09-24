<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Entrevista;
use App\Models\User;

/** Entrevistas: personal de selección de la empresa; el admin SaaS, todas. */
class EntrevistaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol()?->esPersonalDeSeleccion() ?? false;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Entrevista $entrevista): bool
    {
        return $this->alcanza($user, $entrevista);
    }

    /** Confirmar, completar o cancelar. */
    public function update(User $user, Entrevista $entrevista): bool
    {
        return $this->alcanza($user, $entrevista);
    }

    private function alcanza(User $user, Entrevista $entrevista): bool
    {
        if ($user->tieneRol(Rol::Admin)) {
            return true;
        }

        return $this->viewAny($user) && $user->empresa_id === $entrevista->empresa_id;
    }
}
