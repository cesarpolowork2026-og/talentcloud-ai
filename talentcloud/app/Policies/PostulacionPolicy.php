<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Postulacion;
use App\Models\User;

/**
 * Postulaciones: las gestiona el personal de la empresa de la vacante (y el
 * admin SaaS, solo para verlas). El postulante solo ve las suyas.
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

    /** Mover en el tablero y descartar: solo el personal de la empresa (el admin SaaS supervisa, solo lectura). */
    public function update(User $user, Postulacion $postulacion): bool
    {
        return $this->delaEmpresa($user, $postulacion);
    }

    /**
     * Retirar la postulación: solo el propio postulante. Si puede hacerlo ahora
     * (etapa, vacante) lo decide RetirarPostulacion.
     */
    public function retirar(User $user, Postulacion $postulacion): bool
    {
        // Por id: la postulación suele venir de un listado y no tiene cargado su candidato.
        return $user->tieneRol(Rol::Postulante)
            && $user->candidato !== null
            && $postulacion->candidato_id === $user->candidato->id;
    }

    /**
     * Ver y escribir notas internas: solo el personal de la empresa. Son de la empresa: ni el postulante ni el
     * admin SaaS las ven. No depende de view().
     */
    public function gestionarNotas(User $user, Postulacion $postulacion): bool
    {
        return $this->delaEmpresa($user, $postulacion);
    }

    /** Ver: el admin SaaS (supervisión) y el personal de la empresa. */
    private function esDelPersonal(User $user, Postulacion $postulacion): bool
    {
        return $user->tieneRol(Rol::Admin) || $this->delaEmpresa($user, $postulacion);
    }

    /** Escribir: solo el personal de la empresa de la postulación. */
    private function delaEmpresa(User $user, Postulacion $postulacion): bool
    {
        return ($user->rol()?->esPersonalDeSeleccion() ?? false)
            && ! $user->tieneRol(Rol::Admin)
            && $user->empresa_id === $postulacion->empresa_id;
    }
}
