<?php

namespace App\Listeners;

use App\Events\InvitacionEnviada;
use App\Events\PerfilEmpresaActualizado;
use App\Events\VacanteCreada;
use App\Models\Empresa;
use App\Services\Empresas\ProgresoOnboarding;

/** Marca los pasos del checklist de bienvenida cuando ocurren. */
class AvanzarOnboarding
{
    public function __construct(private readonly ProgresoOnboarding $progreso) {}

    public function handlePerfil(PerfilEmpresaActualizado $evento): void
    {
        $this->progreso->marcar($evento->empresa, 'perfil');
    }

    public function handleInvitacion(InvitacionEnviada $evento): void
    {
        // La invitación del admin SaaS a su administrador no cuenta como "invitar a tu equipo".
        if ($evento->invitadaPorLaEmpresa()) {
            $this->progreso->marcar($evento->empresa, 'invitar');
        }
    }

    public function handleVacante(VacanteCreada $evento): void
    {
        $empresa = Empresa::find($evento->vacante->empresa_id);

        if ($empresa !== null) {
            $this->progreso->marcar($empresa, 'vacante');
        }
    }
}
