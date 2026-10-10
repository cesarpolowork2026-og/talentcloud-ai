<?php

namespace App\Repositories\Contracts;

use App\Models\Reclamacion;
use Illuminate\Database\Eloquent\Builder;

interface ReclamacionRepository
{
    public function buscar(int $id): ?Reclamacion;

    /**
     * Consulta base de la pantalla "Reclamaciones" del admin.
     *
     * @return Builder<Reclamacion>
     */
    public function consultaListado(): Builder;

    /** Hojas pendientes de respuesta. */
    public function pendientes(): int;

    /** Pendientes cuya fecha límite ya pasó. */
    public function vencidas(): int;

    /** Pendientes que vencen hasta $limite (inclusive) y aún no vencieron. */
    public function porVencerHasta(\DateTimeInterface $limite): int;
}
