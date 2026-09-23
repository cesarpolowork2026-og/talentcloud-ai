<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $user = User::factory()->reclutador()->create();

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response
        ->assertOk()
        ->assertSee('Mi perfil')
        ->assertSee('Configuración de tu cuenta.')
        ->assertSeeVolt('profile.update-profile-information-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertDontSeeVolt('profile.delete-user-form');
});

test('el postulante ve la opción de eliminar su cuenta', function () {
    $this->actingAs(User::factory()->postulante()->create())
        ->get('/profile')
        ->assertOk()
        ->assertSeeVolt('profile.delete-user-form');
});

test('profile information can be updated', function () {
    $user = User::factory()->reclutador()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('notificaciones_email', false)
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertFalse($user->notificaciones_email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->reclutador()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->postulante()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('solo el postulante puede eliminar su cuenta desde Mi perfil', function (string $rol) {
    $user = User::factory()->{$rol}()->create();

    $this->actingAs($user);

    Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertForbidden();

    $this->assertNotNull($user->fresh());
})->with(['admin', 'empresa', 'reclutador']);

test('correct password must be provided to delete account', function () {
    $user = User::factory()->postulante()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $component
        ->assertHasErrors('password')
        ->assertNoRedirect();

    $this->assertNotNull($user->fresh());
});
