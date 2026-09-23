<?php

namespace App\Repositories\Contracts;

use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use Illuminate\Support\Collection;

/** Usuarios internos de una empresa y sus invitaciones. */
interface EquipoRepository
{
    /**
     * Usuarios de la empresa (rol empresa o reclutador), con sus roles cargados.
     *
     * @return Collection<int, User>
     */
    public function usuariosDe(Empresa $empresa): Collection;

    /**
     * @return Collection<int, Invitacion>
     */
    public function invitacionesPendientesDe(Empresa $empresa): Collection;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearInvitacion(Empresa $empresa, array $datos): Invitacion;

    /** Retira las invitaciones pendientes previas a ese correo en la empresa. */
    public function retirarInvitacionesPendientes(Empresa $empresa, string $email): void;

    /**
     * Invitación vigente por su token en claro. Se busca sin filtro de empresa:
     * quien la abre aún no tiene cuenta.
     */
    public function invitacionVigentePorToken(string $token): ?Invitacion;

    public function existeUsuarioConEmail(string $email): bool;
}
