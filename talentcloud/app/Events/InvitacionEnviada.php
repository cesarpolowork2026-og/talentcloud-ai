<?php

namespace App\Events;

use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Se invitó a una persona al equipo de una empresa. */
class InvitacionEnviada
{
    use Dispatchable;

    public function __construct(
        public readonly Empresa $empresa,
        public readonly Invitacion $invitacion,
        public readonly ?User $invitador,
    ) {}

    /** La invitó alguien del propio equipo (no el admin SaaS al crear la empresa). */
    public function invitadaPorLaEmpresa(): bool
    {
        return $this->invitador !== null && $this->invitador->empresa_id === $this->empresa->id;
    }
}
