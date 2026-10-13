<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Guarda unos segundos las cifras agregadas del dashboard (recorren miles de postulaciones) para que
 * recargar la página no las recalcule cada vez. La clave incluye la empresa (o «global» para el admin
 * SaaS): una empresa nunca lee las cifras de otra. Con `talentcloud.dashboard.cache_segundos` en 0
 * (así están los tests) se calcula siempre.
 */
final class CacheDeResumenes
{
    /**
     * @template T
     *
     * @param  Closure(): T  $calcular
     * @return T
     */
    public static function recordar(string $clave, Closure $calcular): mixed
    {
        $segundos = (int) config('talentcloud.dashboard.cache_segundos', 0);

        if ($segundos <= 0) {
            return $calcular();
        }

        $contexto = app(TenantContext::class);
        $alcance = $contexto->esGlobal() ? 'global' : 'empresa-'.($contexto->id() ?? 'ninguna');

        return Cache::remember("dashboard:{$alcance}:{$clave}", $segundos, $calcular);
    }
}
