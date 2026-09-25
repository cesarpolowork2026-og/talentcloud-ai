<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Postulacion;
use App\Models\User;

/**
 * Postulaciones: las gestiona el personal de la empresa de la vacante (y el
 * admin SaaS). El postulante solo ve las suyas (día 17).
 */
class PostulacionPolicy
{
    public function view(User $user, Postulacion $postulacion): bool
    {
        if ($user->tieneRol(Rol::Postulante)) {
            return $postulacion->candidato?->user_id === $user->id;
        }

        return $this->esDelPersonal($user, $postulacion);
    }

    /** Mover en el Kanban y descartar. */
    public function update(User $user, Postulacion $postulacion): bool
    {
        return $this->esDelPersonal($user, $postulacion);
    }

    /**
     * Ver y escribir notas internas: solo el personal de la empresa (y el admin).
     * No depende de view(): el postulante ve su postulación pero nunca las notas.
     */
    public function gestionarNotas(User $user, Postulacion $postulacion): bool
    {
        return $this->esDelPersonal($user, $postulacion);
    }

    private function esDelPersonal(User $user, Postulacion $postulacion): bool
    {
        if ($user->tieneRol(Rol::Admin)) {
            return true;
        }

        return ($user->rol()?->esPersonalDeSeleccion() ?? false)
            && $user->empresa_id === $postulacion->empresa_id;
    }
}
