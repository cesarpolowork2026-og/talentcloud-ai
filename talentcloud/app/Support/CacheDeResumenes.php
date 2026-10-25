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
 *
 * Cada alcance tiene una versión: cualquier escritura en los modelos que alimentan las cifras (ver
 * AppServiceProvider::invalidarResumenes) la sube y la siguiente lectura ya no encuentra lo guardado. Así una
 * acción se ve en el dashboard en la siguiente carga, no hasta 60 s después.
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
        $version = (int) Cache::get(self::claveDeVersion($alcance), 0);

        return Cache::remember("dashboard:{$alcance}:v{$version}:{$clave}", $segundos, $calcular);
    }

    /**
     * Deja viejas las cifras de una empresa y las de la vista global (el admin SaaS suma todas las empresas).
     * Sin empresa (p. ej. un candidato, que es global) solo cambia la vista global.
     */
    public static function invalidar(?int $empresaId): void
    {
        if ((int) config('talentcloud.dashboard.cache_segundos', 0) <= 0) {
            return;
        }

        self::subir('global');

        if ($empresaId !== null) {
            self::subir('empresa-'.$empresaId);
        }
    }

    private static function subir(string $alcance): void
    {
        // forever + increment: la versión no vence (si venciera, volvería a 0 y podría reutilizar cifras viejas).
        $clave = self::claveDeVersion($alcance);
        Cache::add($clave, 0);
        Cache::increment($clave);
    }

    private static function claveDeVersion(string $alcance): string
    {
        return "dashboard:{$alcance}:version";
    }
}
