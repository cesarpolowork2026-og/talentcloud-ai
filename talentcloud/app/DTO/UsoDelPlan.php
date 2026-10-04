<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;

/** Uso actual de los límites del plan de una empresa ("2 de 3 vacantes activas"). */
final readonly class UsoDelPlan
{
    public const UMBRAL_CERCA_DEL_LIMITE = 0.8;

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

    /**
     * Usa ya el 80 % o más de algún límite (vacantes, o usuarios con sus
     * invitaciones pendientes): el admin SaaS lo ve en su panel para ofrecer
     * un cambio de plan antes de que la empresa se quede sin lugar.
     */
    public function cercaDelLimite(): bool
    {
        $proporcion = fn (int $usado, ?int $limite) => $limite !== null && $limite > 0 && $usado / $limite >= self::UMBRAL_CERCA_DEL_LIMITE;

        return $proporcion($this->vacantesActivas, $this->limiteVacantes())
            || $proporcion($this->usuariosActivos + $this->invitacionesPendientes, $this->limiteUsuarios());
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
