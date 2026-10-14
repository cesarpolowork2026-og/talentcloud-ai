<?php

use App\Models\Empresa;
use App\Models\User;

/*
 * Cabeceras de seguridad en todas las respuestas: páginas públicas, privadas, errores y peticiones de Livewire.
 */

it('envía las cabeceras de seguridad en una página pública', function () {
    $respuesta = $this->get('/planes')->assertOk();

    expect($respuesta->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($respuesta->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($respuesta->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($respuesta->headers->get('Content-Security-Policy'))->toContain("object-src 'none'");
});

it('también en una página con sesión y en las respuestas de error', function () {
    $user = User::factory()->deEmpresa(Empresa::factory()->create())->reclutador()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/esta-pagina-no-existe')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($user)->get('/empresas')->assertForbidden()->assertHeader('X-Frame-Options', 'DENY'); // el rol no lo permite
});

it('solo pide HTTPS al navegador cuando la petición ya es segura', function () {
    expect($this->get('/planes')->headers->has('Strict-Transport-Security'))->toBeFalse();

    $segura = $this->get('https://localhost/planes');

    expect($segura->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

it('el webhook de pagos y los archivos descargados llevan las mismas cabeceras', function () {
    $this->postJson('/stripe/webhook', [])->assertHeader('X-Content-Type-Options', 'nosniff');
});
