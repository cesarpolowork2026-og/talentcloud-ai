<?php

use App\Models\Candidato;
use App\Models\User;
use App\Notifications\AvisoCorreoCambiado;
use Illuminate\Support\Facades\Notification;
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
        ->set('password_actual', 'password')
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

test('el postulante que cambia su nombre o correo actualiza su ficha de candidato', function () {
    $user = User::factory()->postulante()->create();
    $candidato = Candidato::factory()->create(['user_id' => $user->id, 'nombre' => $user->name, 'email' => $user->email]);

    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Marco A. Díaz')
        ->set('email', 'marco.diaz@correo.com')
        ->set('password_actual', 'password')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($candidato->refresh()->only('nombre', 'email'))->toBe(['nombre' => 'Marco A. Díaz', 'email' => 'marco.diaz@correo.com']);
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

// — Cambiar el correo: contraseña actual y aviso al correo anterior —

test('cambiar el correo exige la contraseña actual', function () {
    $user = User::factory()->reclutador()->create(['email' => 'rocio@empresa.com']);
    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('email', 'nuevo@empresa.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['password_actual' => 'required'])
        ->set('password_actual', 'no-es-la-contraseña')
        ->call('updateProfileInformation')
        ->assertHasErrors(['password_actual' => 'current_password']);

    expect($user->fresh()->email)->toBe('rocio@empresa.com');
});

test('con la contraseña correcta cambia el correo y avisa al correo anterior', function () {
    Notification::fake();
    $user = User::factory()->reclutador()->create(['email' => 'rocio@empresa.com']);
    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('email', 'rocio.nueva@gmail.com')
        ->set('password_actual', 'password')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertSet('password_actual', '');

    expect($user->fresh()->email)->toBe('rocio.nueva@gmail.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();

    // El aviso va al correo ANTERIOR y muestra el nuevo a medias.
    Notification::assertSentOnDemand(AvisoCorreoCambiado::class, function (AvisoCorreoCambiado $aviso, array $canales, object $destino) {
        return $destino->routes['mail'] === 'rocio@empresa.com'
            && $canales === ['mail']
            && str_contains($aviso->texto, 'ro***@gmail.com')
            && ! str_contains($aviso->texto, 'rocio.nueva');
    });
});

test('cambiar solo el nombre no pide contraseña ni avisa a nadie', function () {
    Notification::fake();
    $user = User::factory()->reclutador()->create();
    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Otro Nombre')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Otro Nombre');
    Notification::assertNothingSent();
});

test('la pantalla pide la contraseña solo cuando el correo cambia', function () {
    $this->actingAs(User::factory()->reclutador()->create());

    $html = $this->get('/profile')->assertOk()->getContent();

    expect($html)->toContain('id="password_actual"')->and($html)->toContain('x-show="$wire.email.trim().toLowerCase() !== $wire.correo_guardado.toLowerCase()"');
});
