<?php

namespace App\Support;

use App\Models\Vacante;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Guarda 30 s la sección «Vacantes abiertas» de la portada, que se pinta en cada
 * visita y lee datos que casi no cambian. Las vacantes, sus requisitos y las
 * empresas la olvidan al guardarse (ver `booted()` de esos modelos); un cambio
 * masivo tarda como máximo 30 s en verse.
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
