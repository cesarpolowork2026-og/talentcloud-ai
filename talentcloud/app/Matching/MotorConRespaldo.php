<?php

namespace App\Matching;

use Illuminate\Support\Facades\Log;

/**
 * Decorador: evalúa con el motor principal y, si no está disponible, con el de
 * respaldo. El resultado lleva el motor que lo produjo (match_motor), así la
 * pantalla muestra "Análisis básico" y `talentcloud:recalcular-matching
 * --solo-basicos` puede rehacerlo cuando el servicio vuelva.
 */
class MotorConRespaldo implements MatchingEngineInterface
{
    public function __construct(
        private readonly MatchingEngineInterface $principal,
        private readonly MatchingEngineInterface $respaldo,
    ) {}

    public function evaluar(MatchInput $entrada): MatchResult
    {
        try {
            return $this->principal->evaluar($entrada);
        } catch (MotorNoDisponible $e) {
            // Caído: aviso. Respuesta inválida: error (clave o contrato), hay que revisarlo.
            Log::log($e->transitorio ? 'warning' : 'error', 'Matching con el motor de respaldo', [
                'postulacion_id' => $entrada->postulacionId,
                'motivo' => $e->getMessage(),
            ]);

            return $this->respaldo->evaluar($entrada);
        }
    }
}
