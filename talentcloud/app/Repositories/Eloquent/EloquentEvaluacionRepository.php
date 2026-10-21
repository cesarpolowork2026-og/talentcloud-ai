<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoEntrevista;
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
        return Evaluacion::query()->with(['postulacion.candidato', 'postulacion.vacante.empresa:id,nombre', 'evaluador']);
    }

    public function buscar(int $id): ?Evaluacion
    {
        return $this->consultaListado()->find($id);
    }

    public function postulacionesPorEvaluar(User $evaluador, string $texto = '', int $limite = 20): Collection
    {
        return $this->consultaPorEvaluar($evaluador)
            ->select(['id', 'empresa_id', 'vacante_id', 'candidato_id', 'etapa', 'retirada_at'])
            ->with(['candidato:id,nombre', 'vacante:id,titulo,empresa_id', 'vacante.empresa:id,nombre'])
            ->when(trim($texto) !== '', fn (Builder $q) => $q->buscar($texto))
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function contarPorEvaluar(User $evaluador): int
    {
        return $this->consultaPorEvaluar($evaluador)->count();
    }

    /**
     * La misma regla que GuardarEvaluacion::verificarEvaluable(): una entrevista realizada, sin retirar y sin
     * evaluación del usuario.
     *
     * @return Builder<Postulacion>
     */
    private function consultaPorEvaluar(User $evaluador): Builder
    {
        return Postulacion::query()
            ->whereNull('retirada_at')
            ->whereHas('entrevistas', fn (Builder $q) => $q->where('estado', EstadoEntrevista::Completada))
            ->whereDoesntHave('evaluaciones', fn (Builder $q) => $q->where('evaluador_id', $evaluador->id));
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
