<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Models\Candidato;
use App\Models\User;
use App\Support\LimiteDeRegistros;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register')
        ->assertSee('Crear empresa');
});

test('crear empresa registra la empresa y su cuenta con rol empresa', function () {
    $component = Volt::test('pages.auth.register')
        ->set('empresa', 'Nimbus Corp')
        ->set('name', 'Ana Rojas')
        ->set('email', 'ana@nimbus.com')
        ->set('password', 'clave-segura-1')
        ->set('password_confirmation', 'clave-segura-1');

    $component->call('register');

    $component->assertHasNoErrors()->assertRedirect(route('onboarding', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'ana@nimbus.com')->first();

    expect($user->rol())->toBe(Rol::Empresa)
        ->and($user->empresa->nombre)->toBe('Nimbus Corp')
        ->and($user->empresa->plan)->toBe(PlanEmpresa::Starter)
        ->and($user->empresa->estado)->toBe(EstadoEmpresa::Activa)
        ->and($user->empresa->onboarding)->toEqualCanonicalizing(['perfil' => false, 'vacante' => false, 'invitar' => false]);
});

test('crear empresa exige el nombre de la empresa', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Ana Rojas')
        ->set('email', 'ana@nimbus.com')
        ->set('password', 'clave-segura-1')
        ->set('password_confirmation', 'clave-segura-1')
        ->call('register')
        ->assertHasErrors(['empresa' => 'required']);

    $this->assertGuest();
});

test('el registro de postulante se muestra', function () {
    $this->get('/registro/postulante')
        ->assertOk()
        ->assertSeeVolt('pages.auth.register-postulante')
        ->assertSee('Regístrate como postulante');
});

test('el postulante se registra con su consentimiento y perfil de candidato', function () {
    Volt::test('pages.auth.register-postulante')
        ->set('name', 'Marco Díaz')
        ->set('email', 'marco@correo.com')
        ->set('password', 'clave-segura-1')
        ->set('password_confirmation', 'clave-segura-1')
        ->set('consentimiento', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('postulante.postulaciones'));

    $user = User::where('email', 'marco@correo.com')->first();
    $candidato = Candidato::where('user_id', $user->id)->first();

    expect($user->rol())->toBe(Rol::Postulante)
        ->and($user->empresa_id)->toBeNull()
        ->and($candidato->nombre)->toBe('Marco Díaz')
        ->and($candidato->consentimiento_at)->not->toBeNull()
        ->and($candidato->empresa_origen_id)->toBeNull();
});

test('el postulante no puede registrarse sin aceptar el tratamiento de datos', function () {
    Volt::test('pages.auth.register-postulante')
        ->set('name', 'Marco Díaz')
        ->set('email', 'marco@correo.com')
        ->set('password', 'clave-segura-1')
        ->set('password_confirmation', 'clave-segura-1')
        ->call('register')
        ->assertHasErrors(['consentimiento' => 'accepted']);

    $this->assertGuest();
    expect(User::where('email', 'marco@correo.com')->exists())->toBeFalse();
});

test('limita las cuentas nuevas por conexión aunque el envío llegue por Livewire', function () {
    foreach (range(1, LimiteDeRegistros::MAXIMO_POR_HORA + 1) as $i) {
        auth()->logout();
        $componente = Volt::test('pages.auth.register-postulante')
            ->set('name', 'Persona '.$i)
            ->set('email', "persona{$i}@correo.com")
            ->set('password', 'clave-segura-1')
            ->set('password_confirmation', 'clave-segura-1')
            ->set('consentimiento', true)
            ->call('register');
    }

    $componente->assertHasErrors('email');
    expect(User::where('email', 'like', 'persona%')->count())->toBe(LimiteDeRegistros::MAXIMO_POR_HORA);
});
