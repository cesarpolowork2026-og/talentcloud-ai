<?php

namespace App\Listeners;

use App\Events\CvProcesado;
use App\Events\PostulacionCreada;
use App\Events\RequisitosActualizados;
use App\Jobs\CalcularMatch;
use App\Models\Postulacion;

/**
 * Recalcula el matching cuando cambia algo que lo afecta: una postulación
 * nueva, un CV procesado (en todas las postulaciones del candidato, sean de
 * la empresa que sean) o los requisitos de una vacante.
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

    public function handleRequisitos(RequisitosActualizados $evento): void
    {
        Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $evento->vacante->id)
            ->get(['id', 'empresa_id'])
            ->each(fn (Postulacion $p) => CalcularMatch::para($p));
    }
}
