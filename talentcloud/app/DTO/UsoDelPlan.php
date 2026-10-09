<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;
use DateTimeInterface;

/**
 * Uso actual de los límites del plan de una empresa ("2 de 3 vacantes activas").
 * $plan es el plan vigente: durante la prueba, el de prueba ($pruebaHasta indica
 * hasta cuándo).
 */
final readonly class UsoDelPlan
{
    public const UMBRAL_CERCA_DEL_LIMITE = 0.8;

    public function __construct(
        public PlanEmpresa $plan,
        public int $vacantesActivas,
        public int $usuariosActivos,
        public int $invitacionesPendientes,
        public int $postulacionesConIa = 0,
        public ?DateTimeInterface $pruebaHasta = null,
    ) {}

    public function enPrueba(): bool
    {
        return $this->pruebaHasta !== null;
    }

    /** "Pro (prueba)" durante la prueba; si no, el plan. */
    public function etiquetaDelPlan(): string
    {
        return $this->enPrueba() ? $this->plan->etiqueta().' (prueba)' : $this->plan->etiqueta();
    }

    public function limiteVacantes(): ?int
    {
        return $this->plan->limiteVacantesActivas();
    }

    public function limiteUsuarios(): ?int
    {
        return $this->plan->limiteUsuarios();
    }

    public function cupoIa(): ?int
    {
        return $this->plan->cupoIaMensual();
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

    /** Las nuevas postulaciones del mes se evalúan con el análisis básico. */
    public function cupoIaAgotado(): bool
    {
        return $this->cupoIa() !== null && $this->postulacionesConIa >= $this->cupoIa();
    }

    /**
     * Usa ya el 80 % o más de algún límite (vacantes, usuarios con sus
     * invitaciones pendientes o cupo de IA del mes): el admin SaaS lo ve en su
     * panel para ofrecer un cambio de plan antes de que la empresa se quede sin lugar.
     */
    public function cercaDelLimite(): bool
    {
        $proporcion = fn (int $usado, ?int $limite) => $limite !== null && $limite > 0 && $usado / $limite >= self::UMBRAL_CERCA_DEL_LIMITE;

        return $proporcion($this->vacantesActivas, $this->limiteVacantes())
            || $proporcion($this->usuariosActivos + $this->invitacionesPendientes, $this->limiteUsuarios())
            || $proporcion($this->postulacionesConIa, $this->cupoIa());
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
