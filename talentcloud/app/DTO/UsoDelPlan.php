<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;

/** Uso actual de los límites del plan de una empresa ("2 de 3 vacantes activas"). */
final readonly class UsoDelPlan
{
    public function __construct(
        public PlanEmpresa $plan,
        public int $vacantesActivas,
        public int $usuariosActivos,
        public int $invitacionesPendientes,
    ) {}

    public function limiteVacantes(): ?int
    {
        return $this->plan->limiteVacantesActivas();
    }

    public function limiteUsuarios(): ?int
    {
        return $this->plan->limiteUsuarios();
    }

    /** Tras una bajada de plan puede quedar por encima: se conserva lo existente. */
    public function excedeVacantes(): bool
    {
        return $this->limiteVacantes() !== null && $this->vacantesActivas > $this->limiteVacantes();
    }

    public function excedeUsuarios(): bool
    {
        return $this->limiteUsuarios() !== null && $this->usuariosActivos > $this->limiteUsuarios();
    }

    public function vacantesAlLimite(): bool
    {
        return $this->limiteVacantes() !== null && $this->vacantesActivas >= $this->limiteVacantes();
    }

    public function usuariosAlLimite(): bool
    {
        return $this->limiteUsuarios() !== null && $this->usuariosActivos + $this->invitacionesPendientes >= $this->limiteUsuarios();
    }
}
