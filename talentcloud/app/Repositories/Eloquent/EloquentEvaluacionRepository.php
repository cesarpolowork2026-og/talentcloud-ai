<?php

namespace App\Repositories\Eloquent;

use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EvaluacionRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentEvaluacionRepository implements EvaluacionRepository
{
    public function consultaListado(): Builder
    {
        return Evaluacion::query()->with(['postulacion.candidato', 'postulacion.vacante', 'evaluador']);
    }

    public function buscar(int $id): ?Evaluacion
    {
        return $this->consultaListado()->find($id);
    }

    public function postulacionesPorEvaluar(User $evaluador): Collection
    {
        return Postulacion::query()
            ->with(['candidato', 'vacante'])
            ->whereDoesntHave('evaluaciones', fn (Builder $q) => $q->where('evaluador_id', $evaluador->id))
            ->get()
            ->sortBy(fn (Postulacion $p) => $p->candidato?->nombre)
            ->values();
    }

    public function existe(int $postulacionId, int $evaluadorId): bool
    {
        return Evaluacion::query()->where('postulacion_id', $postulacionId)->where('evaluador_id', $evaluadorId)->exists();
    }

    public function crear(Postulacion $postulacion, User $evaluador, array $datos): Evaluacion
    {
        $evaluacion = new Evaluacion(['postulacion_id' => $postulacion->id, 'evaluador_id' => $evaluador->id, ...$datos]);
        $evaluacion->empresa_id = $postulacion->empresa_id;
        $evaluacion->save();

        return $evaluacion;
    }
}
