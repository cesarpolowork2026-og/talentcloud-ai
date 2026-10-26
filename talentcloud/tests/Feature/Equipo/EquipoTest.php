<?php

use App\DTO\InvitacionData;
use App\Enums\EstadoEntrevista;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\InvitacionRecibida;
use App\Services\Equipo\AceptarInvitacion;
use App\Services\Equipo\CambiarEstadoUsuario;
use App\Services\Equipo\InvitacionNoValida;
use App\Services\Equipo\InvitarUsuario;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\Notification;

/*
 * Equipo de la empresa (HU05): invitar por correo, aceptar la invitación, desactivar y reactivar,
 * siempre dentro del límite de usuarios del plan.
 */

beforeEach(function () {
    $this->empresa = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => PlanEmpresa::Pro]);
    $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create();

    actuarComo($this->cuenta);
});

function invitarAlEquipo(Empresa $empresa, User $invitador, string $email): Invitacion
{
    return app(InvitarUsuario::class)->ejecutar($empresa, new InvitacionData('Diego', $email, Rol::Reclutador), $invitador);
}

it('invita por correo con un enlace de un solo uso que vence en 7 días', function () {
    Notification::fake();

    $invitacion = invitarAlEquipo($this->empresa, $this->cuenta, 'diego@nimbus.com');

    Notification::assertSentOnDemand(InvitacionRecibida::class, function (InvitacionRecibida $aviso, array $canales, object $destino) use ($invitacion) {
        return $destino->routes['mail'] === 'diego@nimbus.com'
            && $aviso->invitacion->is($invitacion)
            && hash('sha256', $aviso->token) === $invitacion->token_hash;
    });
    expect($invitacion->empresa_id)->toBe($this->empresa->id)
        ->and($invitacion->expira_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)))->toBeTrue()
        ->and($invitacion->token_hash)->toHaveLength(64);
});

it('al aceptar, la persona crea su contraseña y entra como reclutador de esa empresa; el enlace no se reutiliza', function () {
    Notification::fake();
    invitarAlEquipo($this->empresa, $this->cuenta, 'diego@nimbus.com');
    $token = null;
    Notification::assertSentOnDemand(InvitacionRecibida::class, function (InvitacionRecibida $aviso) use (&$token) {
        $token = $aviso->token;

        return true;
    });

    $usuario = app(AceptarInvitacion::class)->ejecutar($token, 'Diego Torres', 'clave-segura-123');

    expect($usuario->empresa_id)->toBe($this->empresa->id)
        ->and($usuario->hasRole(Rol::Reclutador->value))->toBeTrue()
        ->and($usuario->email_verified_at)->not->toBeNull();
    expect(fn () => app(AceptarInvitacion::class)->ejecutar($token, 'Otra persona', 'clave-segura-123'))
        ->toThrow(InvitacionNoValida::class, 'La invitación no existe, ya se usó o venció.');
});

it('una invitación vencida no se puede aceptar', function () {
    $invitacion = Invitacion::create(['nombre' => 'Diego', 'email' => 'diego@nimbus.com', 'rol' => Rol::Reclutador,
        'token_hash' => hash('sha256', 'token-vencido'), 'expira_at' => now()->subMinute()]);

    expect(fn () => app(AceptarInvitacion::class)->ejecutar('token-vencido', 'Diego', 'clave-segura-123'))
        ->toThrow(InvitacionNoValida::class);
    expect(User::where('email', $invitacion->email)->exists())->toBeFalse();
});

it('no desactiva a quien tiene entrevistas por realizar; sí después de cancelarlas, y luego lo reactiva', function () {
    $marta = User::factory()->deEmpresa($this->empresa)->reclutador()->create(['name' => 'Marta']);
    $vacante = Vacante::factory()->deEmpresa($this->empresa)->create();
    $postulacion = Postulacion::factory()->paraVacante($vacante)->create();
    $entrevista = Entrevista::factory()->create(['postulacion_id' => $postulacion->id, 'entrevistador_id' => $marta->id]);

    expect(fn () => app(CambiarEstadoUsuario::class)->desactivar($marta))
        ->toThrow(OperacionNoPermitida::class, 'Marta tiene 1 entrevista por realizar.');
    expect($marta->fresh()->estaActivo())->toBeTrue();

    $entrevista->update(['estado' => EstadoEntrevista::Cancelada]);

    expect(app(CambiarEstadoUsuario::class)->desactivar($marta)->estaActivo())->toBeFalse()
        ->and(app(CambiarEstadoUsuario::class)->reactivar($marta)->estaActivo())->toBeTrue();
});

it('no supera los usuarios que permite el plan: ni al invitar ni al reactivar', function () {
    $this->empresa->update(['plan' => PlanEmpresa::Gratis, 'prueba_hasta' => null]);
    $marta = User::factory()->deEmpresa($this->empresa)->reclutador()->create();

    expect(fn () => invitarAlEquipo($this->empresa->fresh(), $this->cuenta, 'diego@nimbus.com'))
        ->toThrow(InvitacionNoValida::class, 'permite 2 usuarios');

    app(CambiarEstadoUsuario::class)->desactivar($marta);
    User::factory()->deEmpresa($this->empresa)->reclutador()->create();

    expect(fn () => app(CambiarEstadoUsuario::class)->reactivar($marta))
        ->toThrow(OperacionNoPermitida::class, 'permite 2 usuarios activos');
});
