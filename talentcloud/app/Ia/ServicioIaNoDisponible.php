<?php

namespace App\Ia;

use RuntimeException;
use Throwable;

/** El servicio de IA no respondió o falló (5xx): error transitorio, se reintenta. */
class ServicioIaNoDisponible extends RuntimeException
{
    public static function porConexion(Throwable $causa): self
    {
        return new self('No se pudo conectar con el servicio de IA.', previous: $causa);
    }

    public static function porEstado(int $estado): self
    {
        return new self("El servicio de IA respondió con un error ({$estado}).");
    }
}
