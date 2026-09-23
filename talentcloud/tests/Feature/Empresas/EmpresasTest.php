<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoVacante;
use App\Enums\Rol;
use App\Livewire\Empresas\Listado;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\InvitacionRecibida;
use App\Tenancy\EmpresaScope;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => 'enterprise']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio', 'plan' => 'pro']);
    $this->orbita = Empresa::factory()->suspendida()->create(['nombre' => 'Orbita Labs', 'plan' => 'starter']);

    User::factory()->count(3)->deEmpresa($this->nimbus)->reclutador()->create();
    User::factory()->deEmpresa($this->vela)->reclutador()->create();
    Vacante::factory()->count(2)->deEmpresa($this->nimbus)->create();
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create();

    actuarComo($this->admin);
});

it('muestra la pantalla Empresas del prototipo al admin', function () {
    $this->actingAs($this->admin)
        ->get('/empresas')
        ->assertOk()
        ->assertSee('Empresas registradas en TalentCloud AI.')
        ->assertSee('+ Nueva empresa')
        ->assertSee('Buscar empresa…')
        ->assertSeeInOrder(['Todas', 'Activa', 'Suspendida'])
        ->assertSeeInOrder(['Empresa', 'Plan', 'Usuarios', 'Vacantes activas', 'Estado', 'Acciones'])
        ->assertSeeInOrder(['Nimbus Corp', 'Orbita Labs', 'Vela Studio']);
});

it('niega la pantalla Empresas a los demás roles', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/empresas')->assertForbidden();
})->with(['empresa', 'reclutador', 'postulante']);

it('cuenta usuarios y solo las vacantes abiertas', function () {
    Livewire::test(Listado::class)
        ->assertViewHas('filas', function ($filas) {
            $nimbus = $filas->firstWhere('nombre', 'Nimbus Corp');

            return $nimbus->usuarios_count === 3 && $nimbus->vacantes_activas_count === 2;
        });
});

it('busca, filtra por estado y ordena', function () {
    Livewire::test(Listado::class)
        ->set('busqueda', 'vela')
        ->assertSee('Vela Studio')
        ->assertDontSee('Nimbus Corp')
        ->set('busqueda', '')
        ->call('filtrarEstado', 'suspendida')
        ->assertSee('Orbita Labs')
        ->assertDontSee('Vela Studio')
        ->call('filtrarEstado', '')
        ->set('orden', 'usuarios')
        ->call('alternarDireccion')
        ->assertSeeInOrder(['Nimbus Corp', 'Vela Studio', 'Orbita Labs']);
});

it('ignora órdenes y estados manipulados en la URL', function () {
    $this->actingAs($this->admin)
        ->get('/empresas?orden=password&estado=hackeado&direccion=nada')
        ->assertOk()
        ->assertSee('Nimbus Corp')
        ->assertSee('Orbita Labs');
});

it('pagina con el formato del prototipo', function () {
    Empresa::factory()->count(10)->create();

    Livewire::test(Listado::class)
        ->assertSee('Página 1 de 2')
        ->call('nextPage')
        ->assertSee('Página 2 de 2');
});

it('suspende y reactiva una empresa con confirmación', function () {
    $componente = Livewire::test(Listado::class)
        ->call('confirmarCambioEstado', $this->nimbus->id)
        ->assertSet('mostrarConfirmacion', true)
        ->assertSee('Suspender Nimbus Corp')
        ->assertSee('La empresa y sus usuarios perderán acceso a la plataforma de inmediato.')
        ->call('cambiarEstado');

    expect($this->nimbus->fresh()->estado)->toBe(EstadoEmpresa::Suspendida);

    $componente->call('confirmarCambioEstado', $this->nimbus->id)
        ->assertSee('La empresa recuperará acceso a la plataforma.')
        ->call('cambiarEstado');

    expect($this->nimbus->fresh()->estado)->toBe(EstadoEmpresa::Activa);
});

it('crea una empresa e invita a su administrador', function () {
    Notification::fake();

    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('nombre', 'Acme S.A.')
        ->set('plan', 'pro')
        ->set('emailAdministrador', 'admin@acme.com')
        ->call('crear')
        ->assertHasNoErrors()
        ->assertSet('mostrarFormulario', false);

    $acme = Empresa::where('nombre', 'Acme S.A.')->firstOrFail();
    $invitacion = Invitacion::query()->withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $acme->id)->firstOrFail();

    expect($invitacion->email)->toBe('admin@acme.com')
        ->and($invitacion->rol)->toBe(Rol::Empresa);

    Notification::assertSentOnDemand(InvitacionRecibida::class, fn ($n, $canales, AnonymousNotifiable $destino) => $destino->routes['mail'] === 'admin@acme.com');
});

it('no crea la empresa si el correo del administrador ya tiene cuenta', function () {
    Notification::fake();
    User::factory()->create(['email' => 'ocupado@correo.com']);

    Livewire::test(Listado::class)
        ->set('nombre', 'Otra S.A.')
        ->set('emailAdministrador', 'ocupado@correo.com')
        ->call('crear')
        ->assertHasErrors(['emailAdministrador']);

    expect(Empresa::where('nombre', 'Otra S.A.')->exists())->toBeFalse();
});

it('muestra el detalle de una empresa', function () {
    Livewire::test(Listado::class)
        ->call('verDetalle', $this->nimbus->id)
        ->assertSet('mostrarDetalle', true)
        ->assertSee('Plan: Enterprise')
        ->assertSee('Vacantes activas: 2');
});
