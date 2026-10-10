<?php

namespace App\DTO;

/** Libro de Reclamaciones en el panel del admin SaaS: lo que hay que atender. */
final readonly class ResumenDeReclamaciones
{
    public function __construct(
        public int $pendientes,
        public int $vencidas,
        public int $porVencer,
    ) {}
}
