<?php

namespace App\Support;

/**
 * Enlaces escritos por usuarios (sitio web de la empresa, enlace de una
 * videollamada) que se muestran a otras personas: solo http(s). Cualquier otro
 * esquema (javascript:, data:…) no se pinta como enlace.
 */
final class EnlaceExterno
{
    public static function seguro(?string $url): ?string
    {
        $url = trim((string) $url);
        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($esquema, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /** "nimbus.com" para mostrar en lugar de la URL completa. */
    public static function dominio(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?? $url);
    }
}
