<?php

namespace App\Exports;

use App\Enums\CriterioEvaluacion;
use App\Models\Evaluacion;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

    protected function titulo(): string
    {
        return 'Evaluaciones';
    }

    /** Comentario del evaluador. */
    protected function columnasDeTexto(): array
    {
        return [count(CriterioEvaluacion::cases()) + 5];
    }

    /**
     * Semáforo en cada criterio y en el puntaje, y el promedio general arriba (una fórmula
     * que escribe esta clase sobre números, nunca sobre texto de usuarios).
     */
    protected function alTerminar(Worksheet $hoja, int $primera, int $ultima): void
    {
        $criterios = count(CriterioEvaluacion::cases());
        $primeraNota = Coordinate::stringFromColumnIndex(4);
        $puntaje = Coordinate::stringFromColumnIndex($criterios + 4);

        $this->semaforoDePuntajes($hoja, "{$primeraNota}{$primera}:{$puntaje}{$ultima}");

        $hoja->setCellValueExplicit('A4', "=IFERROR(\"Promedio general de puntaje: \"&TEXT(AVERAGE({$puntaje}{$primera}:{$puntaje}{$ultima}),\"0.0\"),\"\")", DataType::TYPE_FORMULA);
        $hoja->getStyle('A4')->getFont()->setBold(true);
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
