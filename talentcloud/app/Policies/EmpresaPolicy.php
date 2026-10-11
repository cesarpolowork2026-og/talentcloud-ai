<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;

/**
 * Matriz de permisos del prototipo: "Empresas" (listar, crear, suspender o
 * activar) es del admin SaaS; "Mi empresa" es de la cuenta de empresa; el
 * reclutador solo ve los datos de la suya.
 */
class EmpresaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function view(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Admin) || $this->esDeLaEmpresa($user, $empresa);
    }

    public function create(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function update(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Admin)
            || ($user->tieneRol(Rol::Empresa) && $this->esDeLaEmpresa($user, $empresa));
    }

    /** Suspender o activar: solo el admin SaaS. */
    public function cambiarEstado(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    /** Cambiar el plan (y con él, sus límites): solo el admin SaaS. */
    public function cambiarPlan(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    /** Pedir otro plan: la cuenta de empresa, para la suya (lo aplica el admin SaaS). */
    public function solicitarPlan(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Empresa) && $this->esDeLaEmpresa($user, $empresa);
    }

    /** Pagar un plan en línea y gestionar la suscripción: la cuenta de empresa, para la suya. */
    public function pagarPlan(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Empresa) && $this->esDeLaEmpresa($user, $empresa);
    }

    /** Invitar reclutadores: la cuenta de empresa, a su propia empresa. */
    public function invitar(User $user, Empresa $empresa): bool
    {
        return $user->tieneRol(Rol::Empresa) && $this->esDeLaEmpresa($user, $empresa);
    }

    public function delete(User $user, Empresa $empresa): bool
    {
        // Las empresas se suspenden, no se eliminan.
        return false;
    }

    private function esDeLaEmpresa(User $user, Empresa $empresa): bool
    {
        return $user->empresa_id !== null && $user->empresa_id === $empresa->id;
    }
}
