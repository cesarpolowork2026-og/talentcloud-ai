<?php

namespace App\Repositories\Contracts;

use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface EntrevistaRepository
{
    /**
     * Consulta base del listado "Entrevistas", con candidato, vacante y entrevistador.
     *
     * @return Builder<Entrevista>
     */
    public function consultaListado(): Builder;

    public function buscar(int $id): ?Entrevista;

    /**
     * Postulaciones a las que se les puede programar una entrevista: no descartadas y
     * de vacantes abiertas o en revisión.
     *
     * @return Collection<int, Postulacion>
     */
    public function postulacionesAgendables(): Collection;

    /**
     * Entrevistas de un candidato en todas las empresas, para su portal. Única
     * lectura de entrevistas entre empresas: siempre a través de sus propias
     * postulaciones y solo con columnas públicas (nunca las notas internas).
     *
     * @return Collection<int, Entrevista>
     */
    public function delCandidato(Candidato $candidato): Collection;

    /**
     * El entrevistador ya tiene otra entrevista (no cancelada) que se cruza con
     * ese intervalo. $salvoId: la entrevista que se reprograma no choca consigo misma.
     */
    public function haySolapamiento(int $entrevistadorId, CarbonInterface $inicio, CarbonInterface $fin, ?int $salvoId = null): bool;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Postulacion $postulacion, array $datos): Entrevista;
}
