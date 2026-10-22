<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Repositories\Contracts\EntrevistaRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentEntrevistaRepository implements EntrevistaRepository
{
    public function consultaListado(): Builder
    {
        return Entrevista::query()->with(['postulacion.candidato', 'postulacion.vacante.empresa:id,nombre', 'entrevistador']);
    }

    public function buscar(int $id): ?Entrevista
    {
        return $this->consultaListado()->find($id);
    }

    public function delCandidato(Candidato $candidato): Collection
    {
        return $this->consultaDelCandidato($candidato)->orderBy('inicia_at')->get();
    }

    public function delCandidatoPorId(Candidato $candidato, int $id): ?Entrevista
    {
        return $this->consultaDelCandidato($candidato)->whereKey($id)->first();
    }

    /**
     * Entrevistas de todas las postulaciones del candidato, sin notas ni entrevistador: nunca lo interno.
     *
     * @return Builder<Entrevista>
     */
    private function consultaDelCandidato(Candidato $candidato): Builder
    {
        return Entrevista::sinFiltroDeEmpresa()
            ->select(['id', 'empresa_id', 'postulacion_id', 'inicia_at', 'duracion_min', 'modalidad', 'enlace', 'lugar', 'estado'])
            ->whereIn('postulacion_id', $candidato->postulacionesEnTodasLasEmpresas()->select('postulaciones.id'));
    }

    public function postulacionesAgendables(string $texto = '', int $limite = 20): Collection
    {
        return Postulacion::query()
            ->select(['id', 'empresa_id', 'vacante_id', 'candidato_id', 'etapa', 'retirada_at'])
            ->with(['candidato:id,nombre', 'vacante:id,titulo,empresa_id', 'vacante.empresa:id,nombre'])
            ->where('etapa', '!=', EtapaPostulacion::Descartado)
            ->whereHas('vacante', fn (Builder $q) => $q->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision]))
            ->when(trim($texto) !== '', fn (Builder $q) => $q->buscar($texto))
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function haySolapamiento(int $entrevistadorId, CarbonInterface $inicio, CarbonInterface $fin, ?int $salvoId = null): bool
    {
        // Dos intervalos se cruzan si cada uno empieza antes de que termine el otro.
        return Entrevista::query()
            ->when($salvoId !== null, fn ($q) => $q->whereKeyNot($salvoId))
            ->where('entrevistador_id', $entrevistadorId)
            ->where('estado', '!=', EstadoEntrevista::Cancelada)
            ->where('inicia_at', '<', $fin)
            ->whereRaw('inicia_at + make_interval(mins => duracion_min) > ?', [$inicio])
            ->exists();
    }

    public function crear(Postulacion $postulacion, array $datos): Entrevista
    {
        $entrevista = new Entrevista(['postulacion_id' => $postulacion->id, ...$datos]);
        $entrevista->empresa_id = $postulacion->empresa_id;
        $entrevista->save();

        return $entrevista;
    }
}
