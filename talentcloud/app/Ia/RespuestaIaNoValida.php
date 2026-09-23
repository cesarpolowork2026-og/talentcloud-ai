<?php

namespace App\Ia;

use RuntimeException;

/**
 * El servicio de IA rechazó la petición (4xx: clave o contrato) o respondió
 * algo que no cumple el contrato. Reintentar no lo arregla.
 */
class RespuestaIaNoValida extends RuntimeException
{
    public static function porEstado(int $estado): self
    {
        return new self("El servicio de IA rechazó la petición ({$estado}).");
    }

    public static function campo(string $campo): self
    {
        return new self("La respuesta del servicio de IA no cumple el contrato: campo \"{$campo}\".");
    }
}
