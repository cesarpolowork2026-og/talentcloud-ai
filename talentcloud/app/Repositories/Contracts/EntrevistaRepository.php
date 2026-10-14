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
    /**
     * Postulaciones en las que se puede agendar una entrevista, las más recientes primero; con `$texto`, las que
     * coinciden por candidato o vacante. Siempre acotado: una empresa puede tener decenas de miles.
     *
     * @return Collection<int, Postulacion>
     */
    public function postulacionesAgendables(string $texto = '', int $limite = 20): Collection;

    /**
     * Entrevistas de un candidato en todas las empresas, para su portal. Única
     * lectura de entrevistas entre empresas: siempre a través de sus propias
     * postulaciones y solo con columnas públicas (nunca las notas internas).
     *
     * @return Collection<int, Entrevista>
     */
    public function delCandidato(Candidato $candidato): Collection;

    /** Una entrevista del candidato (misma lectura, sin notas internas); null si no es suya o no existe. */
    public function delCandidatoPorId(Candidato $candidato, int $id): ?Entrevista;

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
