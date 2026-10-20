<?php

use App\Models\Empresa;
use App\Models\User;
use App\Tenancy\TenantContext;

/*
 * Las pantallas con sesión son privadas: llevan noindex en el HTML y en la cabecera, y robots.txt bloquea sus rutas.
 */

it('marca como noindex las pantallas del admin SaaS, en el HTML y en la cabecera', function (string $ruta) {
    $respuesta = $this->actingAs(User::factory()->admin()->create())->get($ruta)->assertOk();

    $respuesta->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
})->with(['/dashboard', '/empresas', '/usuarios', '/competencias', '/auditoria', '/vacantes', '/seleccion', '/candidatos', '/entrevistas', '/evaluaciones']);

it('marca como noindex las pantallas del personal de una empresa', function () {
    $empresa = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create());

    $this->actingAs(User::factory()->deEmpresa($empresa)->reclutador()->create())
        ->get('/vacantes')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('no marca como noindex las páginas públicas para quien no tiene sesión', function () {
    foreach (['/', '/empleos', '/planes', '/privacidad', '/terminos'] as $ruta) {
        $respuesta = $this->get($ruta)->assertOk();

        expect($respuesta->headers->has('X-Robots-Tag'))->toBeFalse();
        $respuesta->assertDontSee('noindex', false);
    }
});

it('bloquea en robots.txt las rutas privadas y deja abiertas las públicas', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    foreach (['/dashboard', '/empresas', '/usuarios', '/competencias', '/auditoria', '/vacantes', '/seleccion'] as $ruta) {
        expect($robots)->toContain("Disallow: {$ruta}\n");
    }

    foreach (['/empleos', '/planes', '/privacidad', '/terminos'] as $publica) {
        expect($robots)->not->toContain("Disallow: {$publica}");
    }
});
