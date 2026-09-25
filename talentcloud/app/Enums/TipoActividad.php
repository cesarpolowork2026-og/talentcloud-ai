<?php

namespace App\Enums;

/** Tipos de la "Actividad reciente" del dashboard (columna actividades.tipo). */
enum TipoActividad: string
{
    case EtapaCambiada = 'postulacion.etapa';
    case CvAnalizado = 'cv.analizado';
    case EvaluacionAprobada = 'evaluacion.aprobada';
    case EntrevistaProgramada = 'entrevista.programada';
    case EntrevistaCancelada = 'entrevista.cancelada';
    case VacanteCreada = 'vacante.creada';
    case CandidatoSePostulo = 'postulacion.propia';
}
