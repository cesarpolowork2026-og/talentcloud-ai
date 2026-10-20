<?php

namespace App\DTO;

use App\Models\Vacante;
use Illuminate\Support\Collection;

/** Indicadores y listas del dashboard, ya calculados para el contexto de empresa vigente. */
final readonly class ResumenDelDashboard
{
    /**
     * @param  Collection<int, Vacante>  $vacantesConMovimiento  Activas, con `postulaciones_count` y `por_revisar`.
     * @param  list<array{icono: string, texto: string, ruta: string, parametros: array<string, mixed>, urgente: bool}>  $atencion  Lo que pide una acción hoy, de lo más urgente a lo menos.
     * @param  array<int, int>  $estancadas  Días sin movimiento de cada vacante estancada, por id.
     */
    public function __construct(
        public int $vacantesActivas,
        public int $postulantes,
        public int $postulacionesNuevas,
        public int $porRevisar,
        public int $entrevistasHoy,
        public int $entrevistasProximas,
        public array $atencion,
        public Collection $vacantesConMovimiento,
        public array $estancadas = [],
    ) {}
}
