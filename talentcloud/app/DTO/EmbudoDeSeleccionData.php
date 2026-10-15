<?php

namespace App\DTO;

use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use Carbon\CarbonInterface;

/**
 * Métricas del embudo de un periodo, ya acotadas al contexto de empresa
 * vigente (o a toda la plataforma para el admin SaaS).
 */
final readonly class EmbudoDeSeleccionData
{
    /**
     * @param  list<array{etapa: EtapaPostulacion, total: int, porcentaje: int, conversion: int|null}>  $etapas  Postulaciones que alcanzaron cada etapa (sin Descartado); `conversion` es el % de la etapa anterior que llegó a esta.
     * @param  array<string, int>  $porOrigen  valor de OrigenPostulacion => postulaciones
     * @param  list<array{etapa: EtapaPostulacion, dias: float, muestras: int}>  $tiempoPorEtapa  Días medios en cada etapa ya terminada (ver EmbudoDeSeleccion::tiempoPorEtapa).
     */
    public function __construct(
        public int $dias,
        public CarbonInterface $desde,
        public CarbonInterface $hasta,
        public int $total,
        public array $etapas,
        public int $descartadas,
        public int $retiradas,
        public ?float $diasHastaContratar,
        public int $contrataciones,
        public array $porOrigen,
        public array $tiempoPorEtapa = [],
    ) {}

    /** De cada 100 postulaciones del periodo, cuántas llegaron a la última etapa del embudo (Contratado); null si no hubo ninguna. */
    public function conversionTotal(): ?int
    {
        $ultima = $this->etapas[array_key_last($this->etapas)] ?? null;

        return $this->total === 0 || $ultima === null ? null : (int) round($ultima['total'] * 100 / $this->total);
    }

    public function postulacionesDe(OrigenPostulacion $origen): int
    {
        return $this->porOrigen[$origen->value] ?? 0;
    }
}
