<?php

use App\Models\User;

/*
 * Logo de TalentCloud AI (<x-marca>): la variante según el fondo, su texto
 * alternativo y los iconos de la pestaña.
 */

it('usa la versión clara del logo en la barra y la de colores en el pie', function () {
    $this->get('/empleos')
        ->assertOk()
        ->assertSee('marca/talentcloud-ai-oscuro.svg', false)
        ->assertSee('marca/talentcloud-ai.svg', false)
        ->assertSee('alt="TalentCloud AI"', false);
});

it('muestra el lema solo en la página de inicio', function () {
    $this->get('/')->assertOk()->assertSee('marca/talentcloud-ai-lema-oscuro.svg', false);
    $this->get('/empleos')->assertOk()->assertDontSee('-lema', false);
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
