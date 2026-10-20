<?php

use App\Models\Empresa;
use App\Models\Vacante;

/** Las etiquetas canonical del HTML. */
function canonicalesDe(string $html): array
{
    preg_match_all('#<link rel="canonical" href="([^"]*)">#', $html, $m);

    return $m[1];
}

it('cada página pública declara una sola dirección canónica, la suya', function () {
    $vacante = Vacante::factory()->deEmpresa(Empresa::factory()->create())->create();

    $paginas = [
        '/' => route('inicio'),
        '/empleos' => route('empleos.index'),
        '/empleos/'.$vacante->id => route('empleos.show', $vacante->id),
        '/planes' => route('planes'),
        '/privacidad' => route('privacidad'),
        '/terminos' => route('terminos'),
    ];

    foreach ($paginas as $ruta => $esperada) {
        expect(canonicalesDe($this->get($ruta)->assertOk()->getContent()))->toBe([$esperada], $ruta);
    }
});

it('la canónica de /empleos ignora filtros y búsquedas, pero conserva el número de página', function () {
    Vacante::factory()->count(14)->deEmpresa(Empresa::factory()->create())->create();

    expect(canonicalesDe($this->get('/empleos?page=2')->getContent()))->toBe([route('empleos.index').'?page=2'])
        ->and(canonicalesDe($this->get('/empleos?q=algo&estado=remoto')->getContent()))->toBe([route('empleos.index')])
        ->and(canonicalesDe($this->get('/empleos?page=1')->getContent()))->toBe([route('empleos.index')]);
});

it('/login no se indexa aparte de la portada: noindex y sin canónica', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex, follow">')
        ->and(canonicalesDe($html))->toBe([])
        ->and($this->get('/')->getContent())->not->toContain('noindex');
});
