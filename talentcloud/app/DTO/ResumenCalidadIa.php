<?php

namespace App\DTO;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Calidad de la IA" del admin SaaS: cuánto se analiza con el servicio de IA,
 * qué falla y qué resultados cuestionan los reclutadores, en toda la plataforma.
 */
final readonly class ResumenCalidadIa
{
    /**
     * @param  Collection<int, array{nombre: string, cola: string, fecha: Carbon}>  $ultimosTrabajosFallidos
     * @param  Collection<int, MarcaDeResultadoIncorrecto>  $marcas
     * @param  int  $calculadas  del periodo, sin las de cupo de IA agotado
     * @param  int  $sinCupo  del periodo, con el análisis básico por cupo de IA agotado del plan
     */
    public function __construct(
        public int $calculadas,
        public int $conIa,
        public int $basicas,
        public int $basicasPendientes,
        public int $cvsConError,
        public int $trabajosFallidos,
        public Collection $ultimosTrabajosFallidos,
        public int $marcasDelPeriodo,
        public Collection $marcas,
        public int $sinCupo = 0,
    ) {}

    /** Porcentaje de postulaciones del periodo analizadas por el servicio de IA (null sin cálculos). */
    public function porcentajeConIa(): ?int
    {
        return $this->calculadas === 0 ? null : (int) round($this->conIa * 100 / $this->calculadas);
    }
}
