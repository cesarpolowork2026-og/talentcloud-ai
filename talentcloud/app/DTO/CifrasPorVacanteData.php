<?php

namespace App\DTO;

/**
 * Lo que el dashboard necesita saber de las vacantes activas, ya reducido a unas pocas filas (es lo que se
 * guarda en la caché: nunca la lista completa de vacantes).
 */
final readonly class CifrasPorVacanteData
{
    /**
     * @param  list<array{id: int, total: int, por_revisar: int, mejor_match: float|null}>  $movimiento  Las que más postulaciones por revisar tienen, de más a menos.
     * @param  list<array{id: int, n: int}>  $esperando  Las que más postulaciones llevan días sin revisar.
     * @param  array<int, int>  $estancadas  Días sin movimiento por id de vacante, de más a menos días; solo las que se muestran.
     */
    public function __construct(
        public array $movimiento,
        public array $esperando,
        public array $estancadas,
    ) {}
}
