<?php

namespace App\Enums;

/** Resultado de pedir lugar en el cupo mensual de IA para una postulación (CupoDeIa::reservar). */
enum ReservaDeCupoIa
{
    /** Se le acaba de asignar un lugar del cupo de este mes. */
    case Reservada;

    /** Ya tenía su lugar (de este mes o de uno anterior): recalcular no consume más. */
    case YaCubierta;

    /** El cupo del mes está agotado: se evalúa con el análisis básico. */
    case Agotado;

    public function usaIa(): bool
    {
        return $this !== self::Agotado;
    }
}
