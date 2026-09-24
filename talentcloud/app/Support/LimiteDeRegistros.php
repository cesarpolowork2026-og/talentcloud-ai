<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Límite de cuentas nuevas por IP en los formularios públicos de registro.
 * El middleware throttle de la ruta solo cubre la carga de la página: el
 * envío llega por /livewire/update, así que el límite se aplica en la acción.
 */
final class LimiteDeRegistros
{
    public const MAXIMO_POR_HORA = 5;

    /** Cuenta un registro; si la IP superó el límite, falla la validación en $campo. */
    public static function contar(string $campo): void
    {
        $clave = 'registro|'.request()->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAXIMO_POR_HORA)) {
            throw ValidationException::withMessages([
                $campo => 'Se crearon demasiadas cuentas desde tu conexión. Inténtalo de nuevo en '
                    .(int) ceil(RateLimiter::availableIn($clave) / 60).' minutos.',
            ]);
        }

        RateLimiter::hit($clave, 3600);
    }
}
