<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las respuestas (también las de error y las de Livewire).
 *
 * - Ninguna página se puede incrustar en otro sitio (clickjacking): `X-Frame-Options` y `frame-ancestors`.
 * - El navegador respeta el tipo de contenido declarado (los CV se descargan como adjunto).
 * - La dirección completa no sale hacia otros sitios: hay enlaces con datos en la ruta.
 * - Cámara, micrófono y ubicación desactivados: la aplicación no los usa.
 * - Solo por HTTPS (detrás del balanceador, con `TRUSTED_PROXIES`): el navegador no vuelve a usar HTTP.
 *
 * No se fija `script-src`: Livewire y Alpine necesitan código en línea y `unsafe-eval`, y una política que lo permita
 * no protege. Las directivas que sí se envían no tienen ese problema.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'DENY', false);
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff', false);
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);
        $respuesta->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'", false);

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $respuesta;
    }
}
