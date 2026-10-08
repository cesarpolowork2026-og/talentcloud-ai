<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Notifications\AvisoContrasenaCambiada;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as ReglaDeContrasena;
use Livewire\Volt\Volt;

/*
 * Lo esencial de un acceso seguro: la recuperación no revela qué correos tienen
 * cuenta, contraseñas con un mínimo de solidez, límites de intentos, aviso al
 * cambiar la contraseña y cierre de las demás sesiones.
 */

beforeEach(fn () => RateLimiter::clear('recuperar-contrasena|127.0.0.1'));

// — Recuperar la contraseña —

it('responde lo mismo exista o no el correo, y solo envía el enlace a una cuenta real', function () {
    Notification::fake();
    $usuario = User::factory()->create(['email' => 'marta@nimbus.com']);
    $mensaje = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.';

    foreach (['marta@nimbus.com', 'nadie@ninguna.com'] as $correo) {
        Volt::test('pages.auth.forgot-password')
            ->set('email', $correo)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee($mensaje);
    }

    Notification::assertSentTo($usuario, ResetPassword::class);
    Notification::assertCount(1);
});

it('limita las solicitudes de recuperación desde una misma IP', function () {
    Notification::fake();

    foreach (range(1, 5) as $i) {
        Volt::test('pages.auth.forgot-password')->set('email', "persona{$i}@correo.com")->call('sendPasswordResetLink')->assertHasNoErrors();
    }

    Volt::test('pages.auth.forgot-password')
        ->set('email', 'otra@correo.com')
        ->call('sendPasswordResetLink')
        ->assertHasErrors('email');
});

// — Contraseñas —

it('exige contraseñas de 8 caracteres o más con letras y números', function (string $contrasena, bool $valida) {
    expect(Validator::make(['password' => $contrasena], ['password' => ReglaDeContrasena::defaults()])->passes())->toBe($valida);
})->with([
    'solo letras' => ['password', false],
    'solo números' => ['12345678', false],
    'corta' => ['abc12', false],
    'letras y números' => ['clave-segura-1', true],
]);

// — Intentos de inicio de sesión —

it('bloquea una IP que prueba muchos correos distintos, aunque cada correo falle pocas veces', function () {
    $usuario = User::factory()->reclutador()->create();

    foreach (range(1, LoginForm::INTENTOS_POR_IP) as $i) {
        Volt::test('pages.auth.login')->set('form.email', "intento{$i}@correo.com")->set('form.password', 'incorrecta')->call('login');
    }

    // Con la contraseña correcta de una cuenta real: la IP está bloqueada.
    Volt::test('pages.auth.login')
        ->set('form.email', $usuario->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors('form.email');

    $this->assertGuest();
});

// — Cambio de contraseña —

it('avisa por correo y en la campana cuando la contraseña cambia desde Mi perfil', function () {
    Notification::fake();
    $usuario = User::factory()->reclutador()->create(['notificaciones_email' => false]);
    $this->actingAs($usuario);

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'nueva-clave-1')
        ->set('password_confirmation', 'nueva-clave-1')
        ->call('updatePassword')
        ->assertHasNoErrors();

    // Siempre por correo, aunque la persona haya desactivado los avisos por correo.
    Notification::assertSentTo($usuario, AvisoContrasenaCambiada::class, fn ($aviso, array $canales) => $canales === ['database', 'mail']);
});

it('avisa también cuando la contraseña se restablece con el enlace de recuperación', function () {
    Notification::fake();
    $usuario = User::factory()->create();
    $token = Password::createToken($usuario);

    Volt::test('pages.auth.reset-password', ['token' => $token])
        ->set('email', $usuario->email)
        ->set('password', 'clave-segura-1')
        ->set('password_confirmation', 'clave-segura-1')
        ->call('resetPassword')
        ->assertHasNoErrors();

    Notification::assertSentTo($usuario, AvisoContrasenaCambiada::class);
});

it('cierra las demás sesiones abiertas cuando la contraseña cambia', function () {
    $usuario = User::factory()->reclutador()->create();

    // Una sesión abierta (en otro dispositivo) que guarda la contraseña de entonces.
    $this->actingAs($usuario)->get('/dashboard')->assertOk();

    // La contraseña cambia en otra sesión.
    $usuario->forceFill(['password' => Hash::make('nueva-clave-1')])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

// — Interfaz —

it('deja mostrar u ocultar la contraseña al escribirla', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('aria-label="Mostrar contraseña"', escape: false)
        ->assertSee('aria-pressed="false"', escape: false);
});
