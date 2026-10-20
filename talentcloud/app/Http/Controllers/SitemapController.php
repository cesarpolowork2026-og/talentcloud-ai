<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\VacanteRepository;
use App\Support\BolsaPublica;
use Illuminate\Http\Response;

/**
 * /sitemap.xml: las páginas públicas fijas y una entrada por cada vacante abierta de una empresa activa (la misma lectura
 * que la bolsa de empleos), con su última modificación real. Sin sesión; se guarda 10 minutos y se olvida al cambiar una
 * vacante, un requisito o una empresa.
 */
class SitemapController extends Controller
{
    /** Páginas públicas que se indexan. «/login» no: es la misma presentación con el diálogo abierto. */
    private const FIJAS = ['inicio', 'empleos.index', 'planes', 'privacidad', 'terminos'];

    public function __invoke(VacanteRepository $vacantes): Response
    {
        $xml = BolsaPublica::sitemap(fn (): string => view('sitemap', [
            'fijas' => array_map(fn (string $ruta) => route($ruta), self::FIJAS),
            'vacantes' => $vacantes->publicadasParaSitemap()->get(),
        ])->render());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
