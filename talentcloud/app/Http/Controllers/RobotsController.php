<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * /robots.txt: las reglas de `resources/robots.txt` más la dirección del sitemap, que depende del dominio y por eso no
 * puede ir en un archivo estático.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $reglas = rtrim((string) file_get_contents(resource_path('robots.txt')))."\n\nSitemap: ".route('sitemap')."\n";

        return response($reglas, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
