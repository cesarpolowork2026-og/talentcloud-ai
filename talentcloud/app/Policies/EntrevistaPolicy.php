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

    /** Programar entrevistas es de la empresa: el admin SaaS supervisa (solo lectura). */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && ! $user->tieneRol(Rol::Admin);
    }

    public function view(User $user, Entrevista $entrevista): bool
    {
        return $user->tieneRol(Rol::Admin) || $this->delaEmpresa($user, $entrevista);
    }

    /** Confirmar, completar o cancelar. */
    public function update(User $user, Entrevista $entrevista): bool
    {
        return $this->delaEmpresa($user, $entrevista);
    }

    private function delaEmpresa(User $user, Entrevista $entrevista): bool
    {
        return $this->viewAny($user) && ! $user->tieneRol(Rol::Admin) && $user->empresa_id === $entrevista->empresa_id;
    }
}
