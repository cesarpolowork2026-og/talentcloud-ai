<?php

namespace App\Exports;

use App\Enums\CriterioEvaluacion;
use App\Models\Evaluacion;

/**
 * Evaluaciones, con el puntaje de cada criterio en su columna.
 *
 * @extends ExportacionDeListado<Evaluacion>
 */
class EvaluacionesExport extends ExportacionDeListado
{
    public function headings(): array
    {
        return [
            'Candidato', 'Vacante', 'Evaluador',
            ...array_map(fn (CriterioEvaluacion $c) => $c->etiqueta(), CriterioEvaluacion::cases()),
            'Puntaje', 'Estado', 'Comentario', 'Fecha',
        ];
    }

    /**
     * @param  Evaluacion  $evaluacion
     * @return list<mixed>
     */
    public function map($evaluacion): array
    {
        $puntajes = collect($evaluacion->criterios)->pluck('puntaje', 'nombre');

        return [
            $evaluacion->postulacion?->candidato?->nombre,
            $evaluacion->postulacion?->vacante?->titulo,
            $evaluacion->evaluador?->name,
            ...array_map(fn (CriterioEvaluacion $c) => $puntajes[$c->etiqueta()] ?? null, CriterioEvaluacion::cases()),
            $evaluacion->puntaje === null ? null : (float) $evaluacion->puntaje,
            $evaluacion->estado->etiqueta(),
            $evaluacion->comentario,
            self::fecha($evaluacion->created_at),
        ];
    }
}
