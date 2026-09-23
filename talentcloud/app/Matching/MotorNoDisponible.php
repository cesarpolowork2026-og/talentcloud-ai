<?php

namespace App\Matching;

use RuntimeException;
use Throwable;

/**
 * El motor de matching no pudo evaluar. `transitorio`: no respondió (vale la
 * pena volver a intentarlo más tarde); si no, respondió algo inválido (un
 * error de configuración o de contrato que hay que revisar).
 */
class MotorNoDisponible extends RuntimeException
{
    public function __construct(string $mensaje, public readonly bool $transitorio, ?Throwable $causa = null)
    {
        parent::__construct($mensaje, previous: $causa);
    }
}
