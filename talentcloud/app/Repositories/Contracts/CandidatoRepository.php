<?php

namespace App\Repositories\Contracts;

use App\Models\Candidato;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Candidatos (globales). Visibilidad según el contexto de empresa:
 * - admin SaaS (modo global): todos;
 * - una empresa: los que postularon a sus vacantes o que ella registró;
 * - sin contexto (postulante, Jobs sin empresa): ninguno por esta vía.
 */
interface CandidatoRepository
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Candidato;

    public function deUsuario(User $user): ?Candidato;

    /**
     * Candidatos visibles en el contexto actual.
     *
     * @return Builder<Candidato>
     */
    public function visibles(): Builder;

    /**
     * Consulta base de la pantalla "Candidatos": visibles, con su última
     * postulación (en la empresa del contexto), CV actual y competencias, y
     * el alias ultima_postulacion_at para ordenar por la postulación más
     * reciente (en la vista global se cargan además todos sus procesos para
     * agruparlos por empresa).
     *
     * @return Builder<Candidato>
     */
    public function consultaListado(bool $conOrdenReciente = true): Builder;

    public function buscarVisible(int $id): ?Candidato;

    /** ¿Lo puede ver una empresa? (postuló a sus vacantes o ella lo registró) */
    public function esVisibleParaEmpresa(Candidato $candidato, int $empresaId): bool;
}
