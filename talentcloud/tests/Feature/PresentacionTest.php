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

it('usa la misma cabecera y pie en todas las pantallas públicas', function (string $ruta, int $estado) {
    $this->get($ruta)
        ->assertStatus($estado)
        ->assertSee('aria-label="TalentCloud AI, inicio"', escape: false)
        ->assertSee(route('inicio').'#caracteristicas', escape: false)
        ->assertSee('aria-label="Pie de página"', escape: false);
})->with([
    'presentación' => ['/', 200],
    'crear empresa' => ['/register', 200],
    'registro de postulante' => ['/registro/postulante', 200],
    'recuperar contraseña' => ['/forgot-password', 200],
    'invitación' => ['/invitacion/token-inexistente', 200],
    'página no encontrada' => ['/no-existe', 404],
]);

it('dirige el mensaje del registro de postulante al candidato', function () {
    $this->get('/registro/postulante')->assertSee('Postula con tu CV y sigue cada proceso.');
    $this->get('/register')->assertSee('Encuentra el talento que necesitas.');
});
