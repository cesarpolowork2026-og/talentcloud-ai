<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Guarda unos segundos las cifras agregadas del dashboard para no recalcularlas
 * al recargar. La clave incluye la empresa (o «global» para el admin SaaS), así
 * que una empresa nunca lee las cifras de otra. Con
 * `talentcloud.dashboard.cache_segundos` en 0 (los tests) se calcula siempre.
 */
final class CacheDeResumenes
{
    /**
     * @template T
     *
     * @param  Closure(): T  $calcular
     * @param  int|null  $segundos  Vigencia propia (más corta que la general, p. ej. para pantallas con sondeo); null = la general.
     * @return T
     */
    public static function recordar(string $clave, Closure $calcular, ?int $segundos = null): mixed
    {
        $general = (int) config('talentcloud.dashboard.cache_segundos', 0);

        if ($general <= 0) {
            return $calcular();
        }

        $segundos ??= $general;

        $contexto = app(TenantContext::class);
        $alcance = $contexto->esGlobal() ? 'global' : 'empresa-'.($contexto->id() ?? 'ninguna');

        return Cache::remember("dashboard:{$alcance}:{$clave}", $segundos, $calcular);
    }
}
