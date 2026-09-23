<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login')
        ->assertSee('Bienvenido a TalentCloud')
        ->assertSee('Ingresar como');
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

test('el rol por defecto del login es Reclutador, como en el prototipo', function () {
    Volt::test('pages.auth.login')->assertSet('form.rol', 'reclutador');
});

test('cada rol puede ingresar eligiendo su propio rol', function (string $rol) {
    $user = User::factory()->{$rol}()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->set('form.rol', $rol)
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
})->with(['admin', 'empresa', 'reclutador', 'postulante']);

test('no se puede ingresar con un rol que la cuenta no tiene', function () {
    $user = User::factory()->postulante()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->set('form.rol', 'reclutador')
        ->call('login')
        ->assertHasErrors(['form.rol'])
        ->assertNoRedirect();

    $this->assertGuest();
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
