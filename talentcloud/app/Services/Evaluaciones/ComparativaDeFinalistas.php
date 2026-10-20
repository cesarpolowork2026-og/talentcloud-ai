<?php

namespace App\Services\Evaluaciones;

use App\Enums\CriterioEvaluacion;
use App\Models\Evaluacion;
use App\Models\Vacante;
use Illuminate\Support\Collection;

/**
 * Las postulaciones evaluadas de una vacante, lado a lado: el promedio de cada criterio entre
 * quienes evaluaron, y el puntaje general. Se ordena por
 * puntaje para empezar la conversación; no decide: quien contrata es la persona.
 */
class ComparativaDeFinalistas
{
    /**
     * @return list<array{postulacion_id: int, candidato: string, etapa: string, evaluaciones: int, criterios: array<string, float|null>, promedio: float|null}>
     */
    public function para(Vacante $vacante): array
    {
        return Evaluacion::query()
            // Solo lo que la fila usa.
            ->with([
                'postulacion' => fn ($q) => $q->select(['id', 'candidato_id', 'vacante_id', 'etapa', 'retirada_at']),
                'postulacion.candidato:id,nombre',
            ])
            ->whereHas('postulacion', fn ($q) => $q->where('vacante_id', $vacante->id))
            ->get()
            ->groupBy('postulacion_id')
            ->map(fn (Collection $grupo) => $this->fila($grupo))
            ->sortByDesc(fn (array $fila) => $fila['promedio'] ?? -1)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Evaluacion>  $evaluaciones  Las de una misma postulación.
     * @return array{postulacion_id: int, candidato: string, etapa: string, evaluaciones: int, criterios: array<string, float|null>, promedio: float|null}
     */
    private function fila(Collection $evaluaciones): array
    {
        $postulacion = $evaluaciones->first()->postulacion;
        $notas = $evaluaciones->flatMap(fn (Evaluacion $e) => $e->criterios ?? []);
        $puntajes = $evaluaciones->pluck('puntaje')->filter(fn ($p) => $p !== null);

        $criterios = [];
        foreach (CriterioEvaluacion::cases() as $criterio) {
            $valores = $notas->where('nombre', $criterio->etiqueta())->pluck('puntaje');
            $criterios[$criterio->value] = $valores->isEmpty() ? null : round((float) $valores->avg(), 1);
        }

        return [
            'postulacion_id' => (int) $postulacion?->id,
            'candidato' => (string) $postulacion?->candidato?->nombre,
            'etapa' => $postulacion?->estaRetirada() ? 'Retirada' : (string) $postulacion?->etapa->etiqueta(),
            'evaluaciones' => $evaluaciones->count(),
            'criterios' => $criterios,
            'promedio' => $puntajes->isEmpty() ? null : round((float) $puntajes->avg(), 1),
        ];
    }
}
