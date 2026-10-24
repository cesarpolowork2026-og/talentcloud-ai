<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('el enlace de verificación marca el correo y avisa que la cuenta quedó activa', function () {
    $user = User::factory()->postulante()->unverified()->create();

    $enlace = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $respuesta = $this->actingAs($user)->get($enlace);

    $respuesta->assertRedirect()->assertSessionHas('correo_verificado', true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->followRedirects($respuesta)->assertOk()->assertSee('Tu correo quedó verificado');
});

test('la pantalla de verificación recuerda revisar la carpeta de spam', function () {
    $user = User::factory()->postulante()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('carpeta de spam');
});

test('el sitemap responde un XML válido', function () {
    $respuesta = $this->get('/sitemap.xml');

    $respuesta->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    expect(str_starts_with($respuesta->getContent(), '<?xml version="1.0" encoding="UTF-8"?>'))->toBeTrue()
        ->and(simplexml_load_string($respuesta->getContent()))->not->toBeFalse();
});
