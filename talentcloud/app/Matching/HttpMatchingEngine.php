<?php

namespace App\Matching;

use App\Ia\RespuestaIaNoValida;
use App\Ia\ServicioIa;
use App\Ia\ServicioIaNoDisponible;

/**
 * Motor de matching del servicio de IA (coincidencia exacta + similitud
 * semántica con explicación). Adapta el puerto ServicioIa y traduce sus
 * errores a MotorNoDisponible, para que el módulo de matching no dependa de
 * cómo se habla con el servicio.
 */
class HttpMatchingEngine implements MatchingEngineInterface
{
    public function __construct(private readonly ServicioIa $ia) {}

    public function evaluar(MatchInput $entrada): MatchResult
    {
        try {
            return $this->ia->evaluarMatching($entrada);
        } catch (ServicioIaNoDisponible $e) {
            throw new MotorNoDisponible($e->getMessage(), transitorio: true, causa: $e);
        } catch (RespuestaIaNoValida $e) {
            throw new MotorNoDisponible($e->getMessage(), transitorio: false, causa: $e);
        }
    }
}
