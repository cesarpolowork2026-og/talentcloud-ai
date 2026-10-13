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
    case EntrevistaReprogramada = 'entrevista.reprogramada';
    case NotaAgregada = 'postulacion.nota';
    case VacanteCreada = 'vacante.creada';
    case CandidatoSePostulo = 'postulacion.propia';
    case PostulacionRegistrada = 'postulacion.registrada';
    case PlanCambiado = 'empresa.plan';
    case PlanSolicitado = 'empresa.plan_solicitado';
    case PostulacionRetirada = 'postulacion.retirada';

    /** Icono Phosphor del tipo de actividad (para reconocerla de un vistazo). */
    public function icono(): string
    {
        return match ($this) {
            self::EtapaCambiada => 'arrows-left-right',
            self::CvAnalizado => 'file-text',
            self::EvaluacionAprobada => 'seal-check',
            self::EntrevistaProgramada, self::EntrevistaCancelada, self::EntrevistaReprogramada => 'calendar-blank',
            self::NotaAgregada => 'note',
            self::VacanteCreada => 'briefcase',
            self::CandidatoSePostulo, self::PostulacionRegistrada, self::PostulacionRetirada => 'user-plus',
            self::PlanCambiado, self::PlanSolicitado => 'credit-card',
        };
    }
}
