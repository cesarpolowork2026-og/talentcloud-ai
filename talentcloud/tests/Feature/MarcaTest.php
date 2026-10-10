<?php

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/*
 * Logo de TalentCloud AI (<x-marca>): la variante según el fondo, su texto
 * alternativo y los iconos de la pestaña.
 */

it('usa la versión para fondo oscuro del logo en la barra y en el pie, que comparten color', function () {
    $html = $this->get('/empleos')->assertOk()->assertSee('alt="TalentCloud AI"', false)->getContent();

    // Barra y pie: el mismo azul petróleo (.barra) y el mismo logo.
    expect(substr_count($html, 'marca/talentcloud-ai-oscuro.svg'))->toBe(2)
        ->and($html)->not->toContain('marca/talentcloud-ai.svg')
        ->and($html)->toContain('<footer class="barra">');
});

it('muestra el lema en las páginas del menú público y no en los formularios', function () {
    $this->get('/')->assertOk()->assertSee('Inteligencia que conecta talento');
    $this->get('/empleos')->assertOk()->assertSee('Inteligencia que conecta talento');
    $this->get('/register')->assertOk()->assertDontSee('Inteligencia que conecta talento');
});

it('pinta el logo en la barra de la app y declara los iconos de la marca', function () {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('marca/talentcloud-ai-oscuro.svg', false)
        ->assertSee('favicon.svg', false)
        ->assertSee('apple-touch-icon.png', false);

    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'marca/talentcloud-ai-correo.png'] as $archivo) {
        expect(public_path($archivo))->toBeFile();
    }
});

it('pone el logo en PNG en la cabecera de los correos', function () {
    $correo = (string) (new MailMessage)->subject('Prueba')->line('Hola')->render();

    expect($correo)->toContain(asset('marca/talentcloud-ai-correo.png'))
        ->toContain('alt="TalentCloud AI"')
        ->not->toContain('laravel.com');
});
