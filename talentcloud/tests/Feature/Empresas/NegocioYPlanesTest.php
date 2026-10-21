<?php

use App\DTO\InvitacionData;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoActividad;
use App\Livewire\Auth\AceptarInvitacion as AceptarInvitacionPantalla;
use App\Livewire\Dashboard\Panel as Dashboard;
use App\Livewire\Empresas\Listado as Empresas;
use App\Livewire\MiEmpresa\Panel as MiEmpresa;
use App\Livewire\Vacantes\Detalle as DetalleVacante;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCambioDePlan;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Empresas\CambiarPlanEmpresa;
use App\Services\Equipo\AceptarInvitacion;
use App\Services\Equipo\CambiarEstadoUsuario;
use App\Services\Equipo\InvitacionNoValida;
use App\Services\Equipo\InvitarUsuario;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Vacantes\CambiarEstadoVacante;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * Paquete 4 (ampliación 2): valor de negocio para la empresa y gestión del
 * admin. Historial de etapas, origen de la postulación, embudo de selección,
 * límites por plan y cambio de plan.
 */

beforeEach(function () {
    Notification::fake();

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => PlanEmpresa::Enterprise]);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio', 'plan' => PlanEmpresa::Starter]);
        $this->cuentaNimbus = User::factory()->deEmpresa($this->nimbus)->empresa()->create();
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
        $this->cuentaVela = User::factory()->deEmpresa($this->vela)->empresa()->create(['name' => 'Vela Studio — Admin']);
        $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create(['name' => 'Clara Vidal']);
        $this->admin = User::factory()->admin()->create();

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        $this->designer = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);
    });

    actuarComo($this->marta);
});

/** Postulación de un candidato nuevo a una vacante, creada por el servicio (camino real). */
function postularParaEmbudo(Vacante $vacante): Postulacion
{
    $candidato = app(TenantContext::class)->ejecutarGlobal(fn () => Candidato::factory()->create());

    return app(PostularCandidato::class)->ejecutar($vacante, $candidato);
}

function moverEnEmbudo(Postulacion $postulacion, EtapaPostulacion ...$etapas): Postulacion
{
    foreach ($etapas as $etapa) {
        app(MoverPostulacion::class)->ejecutar($postulacion, $etapa, 0, test()->marta);
    }

    return $postulacion;
}

/** Publicar exige al menos un requisito. */
function conRequisito(Vacante $vacante): Vacante
{
    return app(TenantContext::class)->ejecutarGlobal(function () use ($vacante) {
        $requisito = Requisito::factory()->make(['vacante_id' => $vacante->id]);
        $requisito->empresa_id = $vacante->empresa_id;
        $requisito->save();

        return $vacante;
    });
}

/** Vacante en borrador con requisito, lista para publicar. */
function borradorConRequisito(Empresa $empresa): Vacante
{
    return conRequisito(app(TenantContext::class)->ejecutarGlobal(
        fn () => Vacante::factory()->deEmpresa($empresa)->enEstado(EstadoVacante::Borrador)->create(),
    ));
}

function invitacionConToken(Empresa $empresa, string $token, string $email = 'nueva@vela.studio'): Invitacion
{
    return app(TenantContext::class)->ejecutarGlobal(function () use ($empresa, $token, $email) {
        $invitacion = new Invitacion([
            'nombre' => 'Persona Nueva',
            'email' => $email,
            'rol' => Rol::Reclutador,
            'token_hash' => hash('sha256', $token),
            'expira_at' => now()->addDays(7),
        ]);
        $invitacion->empresa_id = $empresa->id;
        $invitacion->save();

        return $invitacion;
    });
}

// — Historial de etapas —

it('anota cada entrada en una etapa, sea cual sea el camino', function () {
    $postulacion = postularParaEmbudo($this->backend);
    moverEnEmbudo($postulacion, EtapaPostulacion::EnRevision, EtapaPostulacion::EnRevision, EtapaPostulacion::Entrevista);

    // Moverla dentro de la misma columna no es un cambio de etapa.
    expect($postulacion->etapas()->pluck('etapa')->all())->toBe([
        EtapaPostulacion::Postulado,
        EtapaPostulacion::EnRevision,
        EtapaPostulacion::Entrevista,
    ]);

    $deFactory = Postulacion::factory()->paraVacante($this->backend)->enEtapa(EtapaPostulacion::Oferta)->create();
    $historial = $deFactory->etapas()->sole();

    expect($historial->etapa)->toBe(EtapaPostulacion::Oferta)
        ->and($historial->empresa_id)->toBe($this->nimbus->id);
});

it('rellena el historial de las postulaciones anteriores con su etapa actual', function () {
    $postulacion = Postulacion::factory()->paraVacante($this->backend)->enEtapa(EtapaPostulacion::Entrevista)->create();

    Schema::drop('postulacion_etapas');
    (require database_path('migrations/2026_09_23_110000_create_postulacion_etapas_table.php'))->up();

    $fila = app(TenantContext::class)->ejecutarGlobal(fn () => PostulacionEtapa::sole());
    expect($fila->postulacion_id)->toBe($postulacion->id)
        ->and($fila->etapa)->toBe(EtapaPostulacion::Entrevista)
        ->and($fila->empresa_id)->toBe($this->nimbus->id);
});

it('no muestra el historial de otra empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->designer)->create());

    expect(PostulacionEtapa::count())->toBe(0);
});

// — Origen —

it('distingue lo que registra el equipo de lo que postula el candidato', function () {
    expect(postularParaEmbudo($this->backend)->origen)->toBe(OrigenPostulacion::Equipo);

    $usuario = User::factory()->postulante()->create();
    $candidato = app(TenantContext::class)->ejecutarGlobal(fn () => Candidato::factory()->conCuenta($usuario)->create());
    Cv::factory()->procesado()->create(['candidato_id' => $candidato->id]);

    $propia = app(PostularmeAVacante::class)->ejecutar($usuario, $this->backend->id);

    expect($propia->origen)->toBe(OrigenPostulacion::Postulante);
});

it('marca como propias las postulaciones anteriores con actividad de postulación propia', function () {
    $propia = postularParaEmbudo($this->backend);
    $delEquipo = postularParaEmbudo($this->backend);
    app(TenantContext::class)->ejecutarPara($this->nimbus->id, function () use ($propia) {
        $actividad = new Actividad(['tipo' => TipoActividad::CandidatoSePostulo->value, 'descripcion' => 'X postuló a Y.']);
        $actividad->empresa_id = $this->nimbus->id;
        $actividad->sujeto()->associate($propia);
        $actividad->save();
    });

    Schema::table('postulaciones', fn ($t) => $t->dropColumn('origen'));
    (require database_path('migrations/2026_09_23_110001_agregar_origen_a_postulaciones.php'))->up();

    expect($propia->fresh()->origen)->toBe(OrigenPostulacion::Postulante)
        ->and($delEquipo->fresh()->origen)->toBe(OrigenPostulacion::Equipo);
});

// — Embudo de selección —

it('cuenta las etapas alcanzadas, los descartes y el tiempo hasta contratar', function () {
    $this->travelTo(now()->subDays(12));
    $contratada = postularParaEmbudo($this->backend);
    $this->travelBack();

    postularParaEmbudo($this->backend);
    moverEnEmbudo(postularParaEmbudo($this->backend), EtapaPostulacion::EnRevision, EtapaPostulacion::Entrevista);
    moverEnEmbudo(postularParaEmbudo($this->backend), EtapaPostulacion::Entrevista, EtapaPostulacion::Descartado);
    moverEnEmbudo($contratada, EtapaPostulacion::Oferta, EtapaPostulacion::Contratado);

    // De otra empresa: no cuenta.
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->designer)->enEtapa(EtapaPostulacion::Contratado)->create());

    $embudo = app(EmbudoDeSeleccion::class)->calcular();

    expect($embudo->total)->toBe(4)
        ->and(array_map(fn ($f) => [$f['etapa'], $f['total'], $f['porcentaje']], $embudo->etapas))->toBe([
            [EtapaPostulacion::Postulado, 4, 100],
            [EtapaPostulacion::EnRevision, 3, 75],
            [EtapaPostulacion::Entrevista, 3, 75],
            [EtapaPostulacion::Oferta, 1, 25],
            [EtapaPostulacion::Contratado, 1, 25],
        ])
        ->and($embudo->descartadas)->toBe(1)
        ->and($embudo->contrataciones)->toBe(1)
        ->and($embudo->diasHastaContratar)->toEqualWithDelta(12.0, 0.1)
        ->and($embudo->postulacionesDe(OrigenPostulacion::Equipo))->toBe(4)
        ->and($embudo->postulacionesDe(OrigenPostulacion::Postulante))->toBe(0);
});

it('filtra el embudo por vacante y deja fuera lo anterior a 90 días', function () {
    $otra = app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create());
    postularParaEmbudo($this->backend);
    postularParaEmbudo($otra);

    $this->travelTo(now()->subDays(EmbudoDeSeleccion::DIAS + 1));
    postularParaEmbudo($this->backend);
    $this->travelBack();

    $embudo = app(EmbudoDeSeleccion::class);

    expect($embudo->calcular()->total)->toBe(2)
        ->and($embudo->calcular($this->backend->id)->total)->toBe(1)
        ->and($embudo->calcular($this->designer->id)->total)->toBe(0)
        ->and($embudo->calcular()->diasHastaContratar)->toBeNull();
});

it('da al admin SaaS el embudo de toda la plataforma', function () {
    postularParaEmbudo($this->backend);
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->designer)->create());

    actuarComo($this->admin);

    expect(app(EmbudoDeSeleccion::class)->calcular()->total)->toBe(2);
});

it('muestra el embudo en el dashboard y trata una vacante ajena como "todas"', function () {
    // El embudo y el buscador de vacantes son del dashboard de gerencia (la cuenta de empresa); el reclutador ve «Mi día».
    actuarComo($this->cuentaNimbus);

    moverEnEmbudo(postularParaEmbudo($this->backend), EtapaPostulacion::EnRevision);

    Livewire::test(Dashboard::class)
        ->assertSee('Embudo de selección')
        ->assertSee('Sin contrataciones aún')
        ->assertSee('Registradas por el equipo')
        ->assertSee('Todas las vacantes')
        ->assertDontSee('Product Designer')
        ->set('vacanteDelEmbudo', (string) $this->designer->id)
        ->assertSee('En revisión')
        ->assertDontSee('No hay postulaciones en los últimos 90 días.')
        ->set('vacanteDelEmbudo', 'x')
        ->assertOk();
});

it('formatea los días con coma decimal', function () {
    expect(Formato::dias(1))->toBe('1 día')
        ->and(Formato::dias(12))->toBe('12 días')
        ->and(Formato::dias(3.46))->toBe('3,5 días');
});

// — Límites por plan —

it('fija los límites de cada plan', function () {
    expect(PlanEmpresa::Gratis->limiteVacantesActivas())->toBe(1)
        ->and(PlanEmpresa::Gratis->limiteUsuarios())->toBe(2)
        ->and(PlanEmpresa::Starter->limiteVacantesActivas())->toBe(3)
        ->and(PlanEmpresa::Starter->limiteUsuarios())->toBe(3)
        ->and(PlanEmpresa::Pro->limiteVacantesActivas())->toBe(15)
        ->and(PlanEmpresa::Pro->limiteUsuarios())->toBe(10)
        ->and(PlanEmpresa::Enterprise->limiteVacantesActivas())->toBeNull()
        ->and(PlanEmpresa::Gratis->descripcionDeLimites())->toBe('Hasta 1 vacante activa y 2 usuarios.')
        ->and(PlanEmpresa::Pro->descripcionDeLimites())->toBe('Hasta 15 vacantes activas y 10 usuarios.')
        ->and(PlanEmpresa::Enterprise->descripcionDeLimites())->toBe('Vacantes activas sin límite y usuarios sin límite.');
});

it('no deja publicar una vacante por encima del límite, pero sí mover entre activas', function () {
    actuarComo($this->clara);
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->count(2)->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create());
    $borrador = borradorConRequisito($this->vela);
    $cambiar = app(CambiarEstadoVacante::class);

    // Designer + 2 = 3 de 3.
    expect(fn () => $cambiar->ejecutar($borrador, EstadoVacante::Abierta))
        ->toThrow(OperacionNoPermitida::class, 'El plan Starter permite 3 vacantes activas a la vez.');
    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Borrador);

    // Abierta ⇄ En revisión no cambia el número de activas.
    conRequisito($this->designer);
    $cambiar->ejecutar($this->designer, EstadoVacante::EnRevision);
    $cambiar->ejecutar($this->designer->fresh(), EstadoVacante::Abierta);

    // Cerrar una libera el lugar.
    $cambiar->ejecutar($this->designer->fresh(), EstadoVacante::Cerrada);
    $cambiar->ejecutar($borrador, EstadoVacante::Abierta);
    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Abierta);
});

it('muestra el límite de vacantes en la pantalla de la vacante', function () {
    actuarComo($this->clara);
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->count(2)->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create());
    $borrador = borradorConRequisito($this->vela);

    Livewire::test(DetalleVacante::class, ['vacante' => $borrador])
        ->call('cambiarEstado', EstadoVacante::Abierta->value)
        ->assertHasErrors('estado')
        ->assertSee('Cierra una vacante o cambia de plan en Mi empresa.');
});

it('bloquea la fila de la empresa al verificar el límite', function () {
    actuarComo($this->clara);
    $borrador = borradorConRequisito($this->vela);
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = $consulta->sql;
    });

    app(CambiarEstadoVacante::class)->ejecutar($borrador, EstadoVacante::Abierta);

    expect(collect($consultas)->contains(fn (string $sql) => str_contains($sql, 'from "empresas"') && str_contains($sql, 'for update')))->toBeTrue();
});

it('cuenta usuarios activos e invitaciones pendientes al invitar', function () {
    actuarComo($this->cuentaVela);
    $invitar = app(InvitarUsuario::class);

    // Cuenta + Clara = 2 de 3: cabe una invitación.
    $invitar->ejecutar($this->vela, new InvitacionData('Ana', 'ana@vela.studio', Rol::Reclutador), $this->cuentaVela);

    expect(fn () => $invitar->ejecutar($this->vela, new InvitacionData('Luis', 'luis@vela.studio', Rol::Reclutador), $this->cuentaVela))
        ->toThrow(InvitacionNoValida::class, 'El plan Starter permite 3 usuarios, contando las invitaciones pendientes.');

    // Reenviar al mismo correo reemplaza la pendiente: no ocupa otro lugar.
    $invitar->ejecutar($this->vela, new InvitacionData('Ana', 'ana@vela.studio', Rol::Reclutador), $this->cuentaVela);

    Livewire::test(MiEmpresa::class)
        ->call('abrirInvitacion')
        ->set('invitadoNombre', 'Luis')
        ->set('invitadoEmail', 'luis@vela.studio')
        ->call('invitar')
        ->assertHasErrors('invitadoEmail');
});

it('no deja aceptar una invitación si la empresa ya no tiene lugar', function () {
    invitacionConToken($this->vela, 'token-de-prueba');
    User::factory()->deEmpresa($this->vela)->reclutador()->create();

    // 3 activos de 3 (la invitación es anterior a la bajada o a otra alta).
    expect(fn () => app(AceptarInvitacion::class)->ejecutar('token-de-prueba', 'Persona Nueva', 'password-segura'))
        ->toThrow(InvitacionNoValida::class, 'Vela Studio alcanzó el límite de usuarios de su plan.');
    expect(User::where('email', 'nueva@vela.studio')->exists())->toBeFalse();

    app(TenantContext::class)->ejecutarGlobal(fn () => app(CambiarPlanEmpresa::class)->ejecutar($this->vela, PlanEmpresa::Pro, $this->admin));

    expect(app(AceptarInvitacion::class)->ejecutar('token-de-prueba', 'Persona Nueva', 'password-segura')->empresa_id)->toBe($this->vela->id);
});

it('no deja reactivar a un usuario por encima del límite, pero sí desactivar', function () {
    $desactivado = User::factory()->deEmpresa($this->vela)->reclutador()->create(['desactivado_at' => now()]);
    User::factory()->deEmpresa($this->vela)->reclutador()->create();
    actuarComo($this->cuentaVela);

    Livewire::test(MiEmpresa::class)
        ->call('confirmarCambioUsuario', $desactivado->id)
        ->call('cambiarEstadoUsuario')
        ->assertHasErrors('usuario')
        ->assertSee('permite 3 usuarios activos y ya están en uso');
    expect($desactivado->fresh()->estaActivo())->toBeFalse();

    app(CambiarEstadoUsuario::class)->desactivar($this->clara);
    app(CambiarEstadoUsuario::class)->reactivar($desactivado);
    expect($desactivado->fresh()->estaActivo())->toBeTrue();
});

it('no limita a Enterprise', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->count(20)->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create());
    $borrador = borradorConRequisito($this->nimbus);

    app(CambiarEstadoVacante::class)->ejecutar($borrador, EstadoVacante::Abierta);

    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Abierta);
});

it('conserva lo existente tras una bajada de plan y lo indica en Mi empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->count(4)->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create());
    app(TenantContext::class)->ejecutarGlobal(fn () => app(CambiarPlanEmpresa::class)->ejecutar($this->nimbus, PlanEmpresa::Starter, $this->admin));

    expect(Vacante::whereIn('estado', EstadoVacante::activos())->count())->toBe(5);

    actuarComo($this->cuentaNimbus);
    Livewire::test(MiEmpresa::class)
        ->assertSee('5 de 3')
        ->assertSee('Supera el límite del plan: se conserva lo existente, pero no se pueden sumar más.')
        ->assertSee('2 de 3');

    expect(fn () => app(CambiarEstadoVacante::class)->ejecutar(borradorConRequisito($this->nimbus), EstadoVacante::Abierta))
        ->toThrow(OperacionNoPermitida::class);
});

it('muestra el uso del plan en Mi empresa, con las invitaciones pendientes', function () {
    invitacionConToken($this->vela, 'otro-token');
    actuarComo($this->cuentaVela);

    Livewire::test(MiEmpresa::class)
        ->assertSee('Vacantes activas')
        ->assertSee('1 de 3')
        ->assertSee('2 de 3')
        ->assertSee('1 invitación pendiente ocupa lugar hasta que se acepte o se revoque.');
});

// — Cambio de plan por el admin —

it('deja al admin cambiar el plan, avisa a la cuenta de empresa y lo anota en la actividad', function () {
    actuarComo($this->admin);

    Livewire::test(Empresas::class)
        ->call('verDetalle', $this->vela->id)
        ->assertSee('Usuarios de la empresa')
        ->assertSee('Clara Vidal')
        ->assertSee('Uso del plan')
        ->call('abrirCambioDePlan', $this->vela->id)
        ->assertSet('planNuevo', 'starter')
        ->call('cambiarPlan')
        ->assertHasErrors(['planNuevo'])
        ->assertSee('Vela Studio ya tiene el plan Starter.')
        ->set('planNuevo', 'pro')
        ->assertSee('Hasta 15 vacantes activas y 10 usuarios.')
        ->call('cambiarPlan')
        ->assertHasNoErrors()
        ->assertSet('mostrarCambioDePlan', false);

    expect($this->vela->fresh()->plan)->toBe(PlanEmpresa::Pro);
    Notification::assertSentTo($this->cuentaVela, AvisoCambioDePlan::class, fn (AvisoCambioDePlan $n) => $n->texto === 'Vela Studio pasó al plan Pro.');
    Notification::assertNotSentTo($this->clara, AvisoCambioDePlan::class);
    Notification::assertNotSentTo($this->cuentaNimbus, AvisoCambioDePlan::class);

    $actividad = app(TenantContext::class)->ejecutarPara($this->vela->id, fn () => Actividad::sole());
    expect($actividad->descripcion)->toBe('Vela Studio pasó del plan Starter al plan Pro.')
        ->and($actividad->actor_id)->toBe($this->admin->id);
});

it('solo el admin SaaS puede cambiar el plan', function (string $quien) {
    $usuario = match ($quien) {
        'cuenta de empresa' => $this->cuentaVela,
        'reclutador' => $this->clara,
        'postulante' => User::factory()->postulante()->create(),
    };

    expect($usuario->can('cambiarPlan', $this->vela))->toBeFalse()
        ->and($this->admin->can('cambiarPlan', $this->vela))->toBeTrue();

    actuarComo($usuario);
    Livewire::test(Empresas::class)->assertForbidden();
})->with(['cuenta de empresa', 'reclutador', 'postulante']);

// — Auditoría de la ampliación 2 —

it('con el equipo completo, la invitación sigue vigente y el formulario explica el motivo', function () {
    invitacionConToken($this->vela, 'token-completo');
    User::factory()->deEmpresa($this->vela)->reclutador()->create();
    auth()->logout();

    Livewire::test(AceptarInvitacionPantalla::class, ['token' => 'token-completo'])
        ->set('name', 'Persona Nueva')
        ->set('password', 'password-segura-1')
        ->set('password_confirmation', 'password-segura-1')
        ->call('aceptar')
        ->assertSet('valida', true)
        ->assertHasErrors('name')
        ->assertSee('tu invitación sigue vigente')
        ->assertDontSee('Invitación no válida');
});

it('avisa en el diálogo de invitación cuando el plan ya está completo', function () {
    invitacionConToken($this->vela, 'token-pendiente');
    actuarComo($this->cuentaVela);

    Livewire::test(MiEmpresa::class)
        ->call('abrirInvitacion')
        ->assertSee('Tu plan Starter ya tiene sus 3 lugares ocupados');

    actuarComo($this->cuentaNimbus);
    Livewire::test(MiEmpresa::class)->call('abrirInvitacion')->assertDontSee('lugares ocupados');
});
