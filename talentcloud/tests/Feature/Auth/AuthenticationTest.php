<?php

use App\Models\User;
use App\Support\Navegacion;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login')
        ->assertSee('Bienvenido a TalentCloud');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->reclutador()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('cada rol ingresa sin elegirlo y llega a su pantalla de inicio', function (string $rol) {
    $user = User::factory()->{$rol}()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(Navegacion::rutaInicio($user));

    $this->assertAuthenticatedAs($user);
})->with(['admin', 'empresa', 'reclutador', 'postulante']);

test('el login no pide elegir el rol', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Ingresar como')
        ->assertDontSee('Admin SaaS');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->reclutador()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('navigation menu can be rendered', function () {
    $user = User::factory()->reclutador()->create();

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});

test('users can logout', function () {
    $user = User::factory()->reclutador()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
