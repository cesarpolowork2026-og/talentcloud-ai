<?php

namespace App\DTO;

use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;

/**
 * Métricas del embudo de un periodo, ya acotadas al contexto de empresa
 * vigente (o a toda la plataforma para el admin SaaS).
 */
final readonly class EmbudoDeSeleccionData
{
    /**
     * @param  list<array{etapa: EtapaPostulacion, total: int, porcentaje: int}>  $etapas  Postulaciones que alcanzaron cada etapa (sin Descartado).
     * @param  array<string, int>  $porOrigen  valor de OrigenPostulacion => postulaciones
     */
    public function __construct(
        public int $dias,
        public int $total,
        public array $etapas,
        public int $descartadas,
        public ?float $diasHastaContratar,
        public int $contrataciones,
        public array $porOrigen,
    ) {}

    public function postulacionesDe(OrigenPostulacion $origen): int
    {
        return $this->porOrigen[$origen->value] ?? 0;
    }
}
