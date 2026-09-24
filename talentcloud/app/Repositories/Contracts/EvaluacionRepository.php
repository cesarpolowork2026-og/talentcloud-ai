<?php

namespace App\Repositories\Contracts;

use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface EvaluacionRepository
{
    /**
     * Consulta base del listado "Evaluaciones", con candidato, vacante y evaluador.
     *
     * @return Builder<Evaluacion>
     */
    public function consultaListado(): Builder;

    public function buscar(int $id): ?Evaluacion;

    /**
     * Postulaciones que el usuario aún no ha evaluado.
     *
     * @return Collection<int, Postulacion>
     */
    public function postulacionesPorEvaluar(User $evaluador): Collection;

    public function existe(int $postulacionId, int $evaluadorId): bool;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Postulacion $postulacion, User $evaluador, array $datos): Evaluacion;
}
