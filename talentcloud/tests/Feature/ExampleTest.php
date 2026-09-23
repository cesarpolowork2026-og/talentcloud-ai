<?php

use App\Models\User;

it('lleva a los invitados de la raíz al login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('lleva al personal de selección de la raíz al dashboard', function () {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
