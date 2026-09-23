<?php

namespace App\Listeners;

use App\Enums\EstadoProcesamientoCv;
use App\Events\CvProcesado;
use App\Events\RequisitosActualizados;
use App\Ia\ServicioIa;
use App\Jobs\AnalizarCvConIa;
use App\Jobs\GenerarEmbeddingsDeRequisitos;

/**
 * Programa el análisis con IA cuando hay algo nuevo que indexar: el texto de un
 * CV recién extraído o los requisitos de una vacante. Sin servicio de IA
 * habilitado no hace nada.
 */
class IndexarConIa
{
    public function __construct(private readonly ServicioIa $ia) {}

    public function handleCv(CvProcesado $evento): void
    {
        if ($this->ia->habilitado() && $evento->cv->estado_procesamiento === EstadoProcesamientoCv::Procesado) {
            AnalizarCvConIa::dispatch($evento->cv->id);
        }
    }

    public function handleRequisitos(RequisitosActualizados $evento): void
    {
        if ($this->ia->habilitado()) {
            GenerarEmbeddingsDeRequisitos::para($evento->vacante);
        }
    }
}
