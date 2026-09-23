<?php

use App\Models\Empresa;
use App\Models\User;
use App\Policies\EmpresaPolicy;
use App\Tenancy\VerificarEmpresaActiva;

/*
 * Matriz de permisos por rol (plan §3) y empresas suspendidas.
 */

it('lleva a los invitados al login', function (string $ruta) {
    $this->get($ruta)->assertRedirect(route('login'));
})->with(['/dashboard', '/postulante/postulaciones', '/profile']);

it('permite el dashboard al personal de selección', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/dashboard')->assertOk();
})->with(['admin', 'empresa', 'reclutador']);

it('niega el dashboard al postulante con la página 403', function () {
    $this->actingAs(User::factory()->postulante()->create())
        ->get('/dashboard')
        ->assertForbidden()
        ->assertSee('Acceso restringido');
});

it('permite al postulante ver sus postulaciones', function () {
    $this->actingAs(User::factory()->postulante()->create())
        ->get('/postulante/postulaciones')
        ->assertOk()
        ->assertSee('Mis postulaciones');
});

it('niega las postulaciones del postulante al personal de selección', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/postulante/postulaciones')->assertForbidden();
})->with(['admin', 'empresa', 'reclutador']);

it('niega el acceso a una cuenta sin rol', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
});

it('lleva a cada rol a su pantalla de inicio desde la raíz', function (string $rol, string $destino) {
    $this->actingAs(User::factory()->{$rol}()->create())->get('/')->assertRedirect(route($destino));
})->with([
    ['admin', 'dashboard'],
    ['empresa', 'dashboard'],
    ['reclutador', 'dashboard'],
    ['postulante', 'postulante.postulaciones'],
]);

it('cierra la sesión de los usuarios de una empresa suspendida', function () {
    $empresa = Empresa::factory()->suspendida()->create();
    $reclutador = User::factory()->deEmpresa($empresa)->reclutador()->create();

    $this->actingAs($reclutador)
        ->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', VerificarEmpresaActiva::MENSAJE);

    $this->assertGuest();
});

it('aplica la Policy de empresas según el rol', function () {
    $policy = new EmpresaPolicy;
    $nimbus = Empresa::factory()->create();
    $vela = Empresa::factory()->create();

    $admin = User::factory()->admin()->create();
    $cuentaNimbus = User::factory()->deEmpresa($nimbus)->empresa()->create();
    $reclutadorNimbus = User::factory()->deEmpresa($nimbus)->reclutador()->create();

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->viewAny($cuentaNimbus))->toBeFalse()
        ->and($policy->cambiarEstado($admin, $nimbus))->toBeTrue()
        ->and($policy->cambiarEstado($cuentaNimbus, $nimbus))->toBeFalse()
        ->and($policy->view($cuentaNimbus, $nimbus))->toBeTrue()
        ->and($policy->view($cuentaNimbus, $vela))->toBeFalse()
        ->and($policy->view($reclutadorNimbus, $nimbus))->toBeTrue()
        ->and($policy->update($cuentaNimbus, $nimbus))->toBeTrue()
        ->and($policy->update($reclutadorNimbus, $nimbus))->toBeFalse()
        ->and($policy->update($cuentaNimbus, $vela))->toBeFalse()
        ->and($policy->invitar($cuentaNimbus, $nimbus))->toBeTrue()
        ->and($policy->invitar($reclutadorNimbus, $nimbus))->toBeFalse()
        ->and($policy->delete($admin, $nimbus))->toBeFalse();
});
