<?php

namespace App\Enums;

/**
 * Criterios de una evaluación, cada uno de 0 a 10. El puntaje de la evaluación
 * es su promedio (Evaluacion::promediar). Se guardan por nombre en el JSON
 * "criterios" de la evaluación.
 */
enum CriterioEvaluacion: string
{
    case ConocimientoTecnico = 'conocimiento_tecnico';
    case Comunicacion = 'comunicacion';
    case ResolucionDeProblemas = 'resolucion_de_problemas';
    case AjusteCultural = 'ajuste_cultural';

    public const PUNTAJE_MAXIMO = 10;

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConocimientoTecnico => 'Conocimiento técnico',
            self::Comunicacion => 'Comunicación',
            self::ResolucionDeProblemas => 'Resolución de problemas',
            self::AjusteCultural => 'Ajuste cultural',
        };
    }
}
