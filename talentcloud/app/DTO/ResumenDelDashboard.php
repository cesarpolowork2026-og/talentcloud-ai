<?php

namespace App\DTO;

use App\Models\Actividad;
use App\Models\Vacante;
use Illuminate\Support\Collection;

/** Indicadores y listas del dashboard, ya calculados para el contexto de empresa vigente. */
final readonly class ResumenDelDashboard
{
    /**
     * @param  Collection<int, Vacante>  $vacantesRecientes
     * @param  Collection<int, Actividad>  $actividad
     */
    public function __construct(
        public int $vacantesActivas,
        public int $candidatosEvaluados,
        public ?int $matchPromedio,
        public int $postulacionesConMatch,
        public int $entrevistasHoy,
        public Collection $vacantesRecientes,
        public Collection $actividad,
    ) {}
}
