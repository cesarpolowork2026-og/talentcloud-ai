<?php

namespace App\Support;

use App\Models\Vacante;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * La sección «Vacantes abiertas» de la portada se pinta en cada visita, también la de bots y personas sin
 * sesión, y lee datos que casi no cambian: se recuerda 30 s. Las vacantes, sus requisitos y las empresas
 * la olvidan al guardarse (ver `booted()` de esos modelos), así que publicar o cerrar una vacante se ve en
 * la visita siguiente; lo que cambie sin pasar por un modelo (una sentencia masiva) tarda como mucho 30 s.
 */
final class BolsaPublica
{
    private const CLAVE_PORTADA = 'bolsa-publica:portada';

    /**
     * @param  Closure(): Collection<int, Vacante>  $leer
     * @return Collection<int, Vacante>
     */
    public static function portada(Closure $leer): Collection
    {
        return Cache::remember(self::CLAVE_PORTADA, 30, $leer);
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CLAVE_PORTADA);
    }
}
