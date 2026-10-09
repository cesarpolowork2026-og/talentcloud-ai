<?php

namespace App\Listeners;

use App\Enums\EtapaPostulacion;
use App\Events\CvProcesado;
use App\Events\PlanEmpresaCambiado;
use App\Events\PostulacionCreada;
use App\Events\RequisitosActualizados;
use App\Jobs\CalcularMatch;
use App\Models\Postulacion;

/**
 * Recalcula el matching cuando cambia algo que lo afecta: una postulación
 * nueva, un CV procesado (en todas las postulaciones del candidato, sean de
 * la empresa que sean), los requisitos de una vacante o el plan de la empresa
 * (cupo de IA).
 */
class ProgramarMatching
{
    public function handlePostulacion(PostulacionCreada $evento): void
    {
        CalcularMatch::para($evento->postulacion);
    }

    public function handleCv(CvProcesado $evento): void
    {
        // El CV es global: sus postulaciones pueden ser de varias empresas.
        Postulacion::sinFiltroDeEmpresa()
            ->where('candidato_id', $evento->cv->candidato_id)
            ->get(['id', 'empresa_id'])
            ->each(fn (Postulacion $p) => CalcularMatch::para($p));
    }

    /**
     * Con otro plan puede haber cupo de IA: se rehacen las postulaciones en curso que
     * se evaluaron con el análisis básico por cupo agotado (cada una pide su lugar).
     */
    public function handlePlan(PlanEmpresaCambiado $evento): void
    {
        Postulacion::sinFiltroDeEmpresa()
            ->where('empresa_id', $evento->empresa->id)
            ->where('ia_sin_cupo', true)
            ->whereNull('retirada_at')
            ->whereNotIn('etapa', [EtapaPostulacion::Descartado, EtapaPostulacion::Contratado])
            ->orderBy('id')
            ->get(['id', 'empresa_id'])
            ->each(fn (Postulacion $p) => CalcularMatch::para($p));
    }

    public function handleRequisitos(RequisitosActualizados $evento): void
    {
        Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $evento->vacante->id)
            ->get(['id', 'empresa_id'])
            ->each(fn (Postulacion $p) => CalcularMatch::para($p));
    }
}
