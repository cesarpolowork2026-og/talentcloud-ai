<?php

namespace App\DTO;

use Carbon\CarbonInterface;

/**
 * Postulaciones recibidas en un periodo y en el anterior de la misma duración, por día (o por bloques de
 * varios días cuando el periodo es largo), alineadas por posición para compararlas: el bloque 5 de este
 * periodo con el bloque 5 del anterior. Cuenta postulaciones (filas), no personas: una persona que
 * postula a tres vacantes suma tres.
 */
final readonly class TendenciaDePostulacionesData
{
    /**
     * @param  list<array{desde: CarbonInterface, hasta: CarbonInterface, actual: int, anterior: int}>  $cubetas
     */
    public function __construct(
        public int $dias,
        public int $diasPorCubeta,
        public CarbonInterface $desde,
        public CarbonInterface $hasta,
        public CarbonInterface $desdeAnterior,
        public CarbonInterface $hastaAnterior,
        public array $cubetas,
    ) {}

    public function totalActual(): int
    {
        return array_sum(array_column($this->cubetas, 'actual'));
    }

    public function totalAnterior(): int
    {
        return array_sum(array_column($this->cubetas, 'anterior'));
    }

    /** ¿Hubo alguna postulación en alguno de los dos periodos? Sin ninguna no hay nada que dibujar. */
    public function hayDatos(): bool
    {
        return $this->totalActual() + $this->totalAnterior() > 0;
    }

    /** Variación porcentual frente al periodo anterior; null si el anterior no tuvo ninguna (no hay base de comparación). */
    public function variacion(): ?float
    {
        $anterior = $this->totalAnterior();

        return $anterior === 0 ? null : round(($this->totalActual() - $anterior) * 100 / $anterior, 1);
    }
}
