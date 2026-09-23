<?php

use App\Enums\Rol;
use App\Livewire\Auth\AceptarInvitacion;
use App\Livewire\MiEmpresa\Panel;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Notifications\InvitacionRecibida;
use App\Tenancy\EmpresaScope;
use App\Tenancy\VerificarAcceso;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => 'enterprise']);
    $this->cuenta = User::factory()->deEmpresa($this->nimbus)->empresa()->create(['name' => 'Nimbus Corp — Admin']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);

    actuarComo($this->cuenta);
});

function invitar(User $cuenta, string $email, string $rol = 'reclutador'): void
{
    actuarComo($cuenta);

    Livewire::test(Panel::class)
        ->call('abrirInvitacion')
        ->set('invitadoNombre', 'Ana López')
        ->set('invitadoEmail', $email)
        ->set('invitadoRol', $rol)
        ->call('invitar')
        ->assertHasNoErrors();
}

it('muestra Mi empresa como en el prototipo', function () {
    $this->actingAs($this->cuenta)
        ->get('/mi-empresa')
        ->assertOk()
        ->assertSee('Nimbus Corp — plan Enterprise.')
        ->assertSee('Usuarios internos')
        ->assertSee('+ Invitar usuario')
        ->assertSee('Marta Solís')
        ->assertSee('Reclutador')
        ->assertSee('Activo');
});

it('niega Mi empresa a los demás roles', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/mi-empresa')->assertForbidden();
})->with(['admin', 'reclutador', 'postulante']);

it('invita a un reclutador por correo y lo muestra como Invitado', function () {
    Notification::fake();

    invitar($this->cuenta, 'ana@nimbus.com');

    $invitacion = Invitacion::query()->withoutGlobalScope(EmpresaScope::class)->where('email', 'ana@nimbus.com')->firstOrFail();

    expect($invitacion->empresa_id)->toBe($this->nimbus->id)
        ->and($invitacion->rol)->toBe(Rol::Reclutador)
        ->and($invitacion->invitada_por)->toBe($this->cuenta->id);

    Notification::assertSentOnDemand(InvitacionRecibida::class);

    Livewire::test(Panel::class)->assertSee('Ana López')->assertSee('Invitado');
});

it('no invita a un correo que ya tiene cuenta', function () {
    Livewire::test(Panel::class)
        ->set('invitadoNombre', 'Marta')
        ->set('invitadoEmail', $this->marta->email)
        ->call('invitar')
        ->assertHasErrors(['invitadoEmail']);
});

it('no permite invitar como admin SaaS ni como postulante', function (string $rol) {
    Livewire::test(Panel::class)
        ->set('invitadoNombre', 'X')
        ->set('invitadoEmail', 'x@nimbus.com')
        ->set('invitadoRol', $rol)
        ->call('invitar')
        ->assertHasErrors(['invitadoRol']);
})->with(['admin', 'postulante']);

it('acepta la invitación y crea la cuenta en la empresa con su rol', function () {
    Notification::fake();
    invitar($this->cuenta, 'ana@nimbus.com', 'empresa');

    $token = null;
    Notification::assertSentOnDemand(InvitacionRecibida::class, function (InvitacionRecibida $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    auth()->logout();

    $this->get(route('invitacion.aceptar', $token))->assertOk()->assertSee('Únete a Nimbus Corp');

    Livewire::test(AceptarInvitacion::class, ['token' => $token])
        ->assertSet('valida', true)
        ->assertSet('name', 'Ana López')
        ->set('password', 'secreto-seguro-1')
        ->set('password_confirmation', 'secreto-seguro-1')
        ->call('aceptar')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $ana = User::where('email', 'ana@nimbus.com')->firstOrFail();

    expect($ana->empresa_id)->toBe($this->nimbus->id)
        ->and($ana->rol())->toBe(Rol::Empresa)
        ->and($ana->hasVerifiedEmail())->toBeTrue();

    // El enlace es de un solo uso.
    Livewire::test(AceptarInvitacion::class, ['token' => $token])->assertSet('valida', false)->assertSee('Invitación no válida');
});

it('rechaza invitaciones vencidas o con token inventado', function () {
    Notification::fake();
    invitar($this->cuenta, 'ana@nimbus.com');

    Invitacion::query()->withoutGlobalScope(EmpresaScope::class)->update(['expira_at' => now()->subMinute()]);

    $token = null;
    Notification::assertSentOnDemand(InvitacionRecibida::class, function (InvitacionRecibida $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    Livewire::test(AceptarInvitacion::class, ['token' => $token])->assertSet('valida', false);
    Livewire::test(AceptarInvitacion::class, ['token' => 'inventado'])->assertSet('valida', false);
});

it('revoca una invitación pendiente', function () {
    Notification::fake();
    invitar($this->cuenta, 'ana@nimbus.com');
    $invitacion = Invitacion::query()->withoutGlobalScope(EmpresaScope::class)->firstOrFail();

    Livewire::test(Panel::class)
        ->call('confirmarRevocacion', $invitacion->id)
        ->call('revocarInvitacion');

    expect(Invitacion::query()->withoutGlobalScope(EmpresaScope::class)->count())->toBe(0);
});

it('desactiva a un reclutador: pierde la sesión y no puede volver a ingresar', function () {
    Livewire::test(Panel::class)
        ->call('confirmarCambioUsuario', $this->marta->id)
        ->assertSee('Desactivar a Marta Solís')
        ->call('cambiarEstadoUsuario');

    expect($this->marta->fresh()->estaActivo())->toBeFalse();

    $this->actingAs($this->marta->fresh())
        ->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', VerificarAcceso::USUARIO_DESACTIVADO);

    Volt::test('pages.auth.login')
        ->set('form.email', $this->marta->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email']);

    $this->assertGuest();
});

it('no permite a la cuenta de empresa desactivarse a sí misma', function () {
    Livewire::test(Panel::class)
        ->call('confirmarCambioUsuario', $this->cuenta->id)
        ->assertForbidden();
});

it('no permite gestionar usuarios de otra empresa', function () {
    $ajeno = User::factory()->reclutador()->create();

    Livewire::test(Panel::class)
        ->call('confirmarCambioUsuario', $ajeno->id)
        ->assertNotFound();

    expect($ajeno->fresh()->estaActivo())->toBeTrue();
});

it('guarda los datos de la empresa', function () {
    Livewire::test(Panel::class)
        ->call('abrirDatos')
        ->set('sector', 'Tecnología')
        ->set('sitioWeb', 'https://nimbus.example.com')
        ->call('guardarDatos')
        ->assertHasNoErrors();

    expect($this->nimbus->fresh()->sector)->toBe('Tecnología');
});

it('no deja iniciar sesión a usuarios de una empresa suspendida', function () {
    $this->nimbus->update(['estado' => 'suspendida']);

    Volt::test('pages.auth.login')
        ->set('form.email', $this->marta->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email']);

    $this->assertGuest();
});
