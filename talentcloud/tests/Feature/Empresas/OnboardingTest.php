<?php

use App\Livewire\Empresas\Listado as ListadoEmpresas;
use App\Livewire\MiEmpresa\Panel;
use App\Livewire\Onboarding\Checklist;
use App\Livewire\Vacantes\Listado as ListadoVacantes;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();

    $this->empresa = Empresa::factory()->create([
        'nombre' => 'Acme S.A.',
        'onboarding' => ['perfil' => false, 'vacante' => false, 'invitar' => false],
    ]);
    $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create();

    actuarComo($this->cuenta);
});

it('muestra el checklist del prototipo', function () {
    $this->get('/bienvenida')
        ->assertOk()
        ->assertSee('Bienvenido a TalentCloud')
        ->assertSee('Configuremos Acme S.A. en 3 pasos.')
        ->assertSee('0 de 3 completadas')
        ->assertSeeInOrder(['Completa el perfil de tu empresa', 'Publica tu primera vacante', 'Invita a tu equipo de reclutamiento'])
        ->assertSeeInOrder(['Completar', 'Crear vacante', 'Invitar'])
        ->assertSee('Saltar por ahora →');
});

it('cierra el checklist completado con un aviso y la salida al dashboard', function () {
    $this->empresa->update(['onboarding' => ['perfil' => true, 'vacante' => true, 'invitar' => true]]);

    $this->get('/bienvenida')
        ->assertOk()
        ->assertSee('3 de 3 completadas')
        ->assertSee('¡Listo! Acme S.A. ya está configurada.')
        ->assertSee('Ir al dashboard →')
        ->assertDontSee('Saltar por ahora');
});

it('lleva a la empresa recién registrada al checklist', function () {
    auth()->logout();

    Volt::test('pages.auth.register')
        ->set('empresa', 'Nueva S.A.')
        ->set('name', 'Ana')
        ->set('email', 'ana@nueva.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('onboarding', absolute: false));
});

it('marca cada paso cuando ocurre de verdad', function () {
    Livewire::test(Panel::class)
        ->call('abrirDatos')
        ->set('sector', 'Tecnología')
        ->call('guardarDatos');

    expect($this->empresa->fresh()->onboarding['perfil'])->toBeTrue();

    Livewire::test(ListadoVacantes::class)
        ->call('abrirFormulario')
        ->set('titulo', 'Backend')
        ->set('competencias', 'Java')
        ->call('crear');

    expect($this->empresa->fresh()->onboarding['vacante'])->toBeTrue();

    Livewire::test(Panel::class)
        ->call('abrirInvitacion')
        ->set('invitadoNombre', 'Rosa')
        ->set('invitadoEmail', 'rosa@acme.com')
        ->call('invitar');

    expect($this->empresa->fresh()->onboarding)->toEqualCanonicalizing(['perfil' => true, 'vacante' => true, 'invitar' => true]);

    Livewire::test(Checklist::class)->assertSee('3 de 3 completadas')->assertSee('Ver');
});

it('no marca "invitar a tu equipo" cuando el admin SaaS crea la empresa', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(ListadoEmpresas::class)
        ->set('nombre', 'Otra S.A.')
        ->set('plan', 'starter')
        ->set('emailAdministrador', 'admin@otra.com')
        ->call('crear')
        ->assertHasNoErrors();

    expect(Empresa::where('nombre', 'Otra S.A.')->first()->onboarding['invitar'])->toBeFalse();
});

it('abre los formularios desde los accesos directos del checklist', function () {
    Livewire::withQueryParams(['abrir' => 'invitar'])->test(Panel::class)->assertSet('mostrarInvitacion', true);
    Livewire::withQueryParams(['abrir' => 'datos'])->test(Panel::class)->assertSet('mostrarDatos', true);
    Livewire::withQueryParams(['nueva' => 1])->test(ListadoVacantes::class)->assertSet('mostrarFormulario', true);
});

it('niega el checklist a los demás roles', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/bienvenida')->assertForbidden();
})->with(['admin', 'reclutador', 'postulante']);
