<?php

namespace App\Pagos;

/**
 * ¿Se cobran los planes en línea? Sin pasarela configurada (PAGOS_PASARELA),
 * la empresa solicita el plan y el admin SaaS lo aplica a mano.
 */
final class CobroEnLinea
{
    public static function habilitado(): bool
    {
        return config('talentcloud.pagos.pasarela') !== null;
    }
}
