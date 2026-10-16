<?php

namespace App\Repositories\Contracts;

use App\DTO\ResumenDeReclamaciones;
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

    /** Pendientes, vencidas y por vencer hasta $limite, en una sola lectura. */
    public function resumen(\DateTimeInterface $limite): ResumenDeReclamaciones;

    /** Hojas pendientes de respuesta. */
    public function pendientes(): int;

    /** Pendientes cuya fecha límite ya pasó. */
    public function vencidas(): int;

    /** Pendientes que vencen hasta $limite (inclusive) y aún no vencieron. */
    public function porVencerHasta(\DateTimeInterface $limite): int;
}
