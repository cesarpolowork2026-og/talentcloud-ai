<?php

use App\Models\User;

it('muestra a los invitados la presentación con el login en la raíz', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeVolt('pages.auth.login')
        ->assertSee('Todo tu reclutamiento en un solo lugar')
        ->assertSee('Iniciar sesión')
        ->assertSee(['Características', 'Beneficios', 'Seguridad'])
        ->assertSee('aria-roledescription="carrusel"', escape: false)
        ->assertSee('<title>TalentCloud AI</title>', escape: false);
});

it('muestra la misma presentación en /login, con el foco en el correo', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Todo tu reclutamiento en un solo lugar')
        ->assertSee('<title>Iniciar sesión · TalentCloud AI</title>', escape: false)
        ->assertSee('autofocus', escape: false);
});

it('lleva al personal de selección de la raíz y del login al dashboard', function (string $ruta) {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get($ruta)
        ->assertRedirect(route('dashboard'));
})->with(['/', '/login']);

it('lleva al postulante de la raíz y del login a sus postulaciones, no a un dashboard prohibido', function (string $ruta) {
    $this->actingAs(User::factory()->postulante()->create())
        ->get($ruta)
        ->assertRedirect(route('postulante.postulaciones'));
})->with(['/', '/login']);
