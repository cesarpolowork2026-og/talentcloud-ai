<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Models\Vacante;
use App\Support\BolsaPublica;

beforeEach(fn () => BolsaPublica::olvidar());

it('lista las páginas públicas y las vacantes abiertas de empresas activas, con su última modificación', function () {
    $activa = Empresa::factory()->create();
    $abierta = Vacante::factory()->deEmpresa($activa)->create();
    $borrador = Vacante::factory()->deEmpresa($activa)->enEstado(EstadoVacante::Borrador)->create();
    $cerrada = Vacante::factory()->deEmpresa($activa)->enEstado(EstadoVacante::Cerrada)->create();
    $deSuspendida = Vacante::factory()->deEmpresa(Empresa::factory()->create(['estado' => EstadoEmpresa::Suspendida]))->create();

    $respuesta = $this->get('/sitemap.xml')->assertOk();
    $xml = $respuesta->getContent();

    expect($respuesta->headers->get('Content-Type'))->toContain('application/xml')
        ->and($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($xml)->toContain('<loc>'.route('inicio').'</loc>')
        ->and($xml)->toContain('<loc>'.route('empleos.index').'</loc>')
        ->and($xml)->toContain('<loc>'.route('planes').'</loc>')
        ->and($xml)->toContain('<loc>'.route('privacidad').'</loc>')
        ->and($xml)->toContain('<loc>'.route('terminos').'</loc>')
        ->and($xml)->toContain('<loc>'.route('empleos.show', $abierta->id).'</loc><lastmod>')
        ->and($xml)->not->toContain(route('empleos.show', $borrador->id))
        ->and($xml)->not->toContain(route('empleos.show', $cerrada->id))
        ->and($xml)->not->toContain(route('empleos.show', $deSuspendida->id))
        ->and($xml)->not->toContain('/login');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('olvida el sitemap cuando una vacante cambia', function () {
    $empresa = Empresa::factory()->create();
    $vacante = Vacante::factory()->deEmpresa($empresa)->create();
    expect($this->get('/sitemap.xml')->getContent())->toContain(route('empleos.show', $vacante->id));

    $vacante->update(['estado' => EstadoVacante::Cerrada]);

    expect($this->get('/sitemap.xml')->getContent())->not->toContain(route('empleos.show', $vacante->id));
});

it('no abre sesión ni deja cookies al servir el sitemap y robots.txt', function () {
    expect($this->get('/sitemap.xml')->headers->getCookies())->toBe([])
        ->and($this->get('/robots.txt')->headers->getCookies())->toBe([]);
});

it('robots.txt declara el sitemap con el dominio actual y bloquea el enlace de postular', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Sitemap: '.route('sitemap'))
        ->and($robots)->toContain('Disallow: /empleos/*/postular')
        ->and($robots)->not->toContain("Disallow: /empleos\n");
});
