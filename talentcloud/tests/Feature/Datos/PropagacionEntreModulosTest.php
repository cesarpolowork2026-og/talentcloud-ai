<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Livewire\Dashboard\Panel;
use App\Livewire\Entrevistas\Listado;
use App\Livewire\Postulaciones\Notas;
use App\Livewire\Postulante\MisPostulaciones;
use App\Livewire\Postulante\Vacantes;
use App\Livewire\Seleccion\Central;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoNuevaPostulacion;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Plataforma\PanelDePlataforma;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\NotasDePostulacion;
use App\Support\CacheDeResumenes;
use App\Tenancy\TenantContext;
use Livewire\Volt\Volt;

/*
 * Lo que hace un rol se ve en los módulos de los demás en la siguiente carga, también con la caché de cifras
 * encendida como en producción (los demás tests la apagan con DASHBOARD_CACHE_SEGUNDOS=0).
 */

beforeEach(function () {
    config(['talentcloud.dashboard.cache_segundos' => 60]);

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->cuenta = User::factory()->deEmpresa($this->nimbus)->empresa()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();

    actuarComo($this->marta);
});

function postulacionEnBackend(object $prueba, EtapaPostulacion $etapa = EtapaPostulacion::Postulado): Postulacion
{
    return Postulacion::factory()->paraVacante($prueba->backend)->enEtapa($etapa)
        ->create(['candidato_id' => Candidato::factory()->create()->id]);
}

it('una postulación nueva aparece al instante en el resumen y en el embudo del dashboard', function () {
    $antes = app(DashboardService::class)->resumen();
    $embudo = app(EmbudoDeSeleccion::class)->calcular();

    postulacionEnBackend($this);

    expect(app(DashboardService::class)->resumen()->postulantes)->toBe($antes->postulantes + 1)
        ->and(app(EmbudoDeSeleccion::class)->calcular()->total)->toBe($embudo->total + 1);
});

it('mover de etapa se refleja al instante en el embudo', function () {
    $postulacion = postulacionEnBackend($this);
    $antes = collect(app(EmbudoDeSeleccion::class)->calcular()->etapas);

    app(MoverPostulacion::class)->ejecutar($postulacion, EtapaPostulacion::EnRevision, 0, $this->marta);

    $despues = collect(app(EmbudoDeSeleccion::class)->calcular()->etapas);
    expect($despues)->not->toEqual($antes);
});

it('una entrevista de hoy y el cierre de una vacante se ven al instante en el resumen', function () {
    $postulacion = postulacionEnBackend($this, EtapaPostulacion::Entrevista);
    $antes = app(DashboardService::class)->resumen();

    Entrevista::factory()->create(['postulacion_id' => $postulacion->id, 'inicia_at' => now()->addHour(), 'estado' => EstadoEntrevista::Confirmada]);
    expect(app(DashboardService::class)->resumen()->entrevistasHoy)->toBe($antes->entrevistasHoy + 1);

    $otra = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();
    expect(app(DashboardService::class)->resumen()->vacantesActivas)->toBe($antes->vacantesActivas + 1);

    $otra->update(['estado' => EstadoVacante::Cerrada]);
    expect(app(DashboardService::class)->resumen()->vacantesActivas)->toBe($antes->vacantesActivas);
});

it('invitar a alguien cambia al instante el uso del plan de la cuenta de empresa', function () {
    actuarComo($this->cuenta);
    $uso = fn () => CacheDeResumenes::recordar('uso-del-plan', fn () => app(LimitesDelPlan::class)->uso($this->nimbus->fresh()), 30);
    $antes = $uso();

    Invitacion::create(['nombre' => 'Diego', 'email' => 'diego@nimbus.com', 'rol' => Rol::Reclutador,
        'token_hash' => hash('sha256', 'token'), 'expira_at' => now()->addDays(7)]);

    expect($uso())->not->toEqual($antes);
});

it('el panel del admin SaaS ve al instante una empresa nueva', function () {
    actuarComo(User::factory()->admin()->create());
    $panel = fn () => app(TenantContext::class)->ejecutarGlobal(
        fn () => CacheDeResumenes::recordar('plataforma', fn () => app(PanelDePlataforma::class)->resumen(), 30)
    );
    $antes = $panel();

    Empresa::factory()->create(['nombre' => 'Orbita Labs']);

    expect($panel())->not->toEqual($antes);
});

it('lo que pasa en una empresa no deja viejas ni mezcla las cifras de otra', function () {
    $claraVela = User::factory()->deEmpresa($this->vela)->reclutador()->create();
    actuarComo($claraVela);
    $deVela = app(DashboardService::class)->resumen();

    actuarComo($this->marta);
    postulacionEnBackend($this);

    actuarComo($claraVela);
    expect(app(DashboardService::class)->resumen()->postulantes)->toBe($deVela->postulantes);
});

// — Pantallas abiertas: el sondeo (wire:poll) trae lo que hace otra persona sin recargar —

it('Selección abierta muestra la postulación que llega mientras la mira otra persona', function () {
    $pantalla = Livewire\Livewire::test(Central::class, ['vacante' => $this->backend]);

    Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Rosa Quispe'])->id]);

    $pantalla->call('revisarCambios')->assertSee('Rosa Quispe');
});

it('sin cambios, el sondeo no vuelve a pintar la pantalla', function () {
    $this->travel(2)->seconds(); // los datos son anteriores a la carga de la página
    $pantalla = Livewire\Livewire::test(Central::class, ['vacante' => $this->backend]);

    $pantalla->call('revisarCambios');
    expect($pantalla->effects)->not->toHaveKey('html');

    $pantalla->call('revisarCambios');
    expect($pantalla->effects)->not->toHaveKey('html');
});

it('el postulante ve en Mis postulaciones el cambio de etapa que hizo el reclutador', function () {
    $postulante = User::factory()->postulante()->create();
    $candidato = Candidato::factory()->create(['user_id' => $postulante->id]);
    $postulacion = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $candidato->id]);

    actuarComo($postulante);
    $pantalla = Livewire\Livewire::test(MisPostulaciones::class)->assertSee('Postulado');
    $this->travel(2)->seconds();

    actuarComo($this->marta);
    app(MoverPostulacion::class)->ejecutar($postulacion->fresh(), EtapaPostulacion::EnRevision, 0, $this->marta);

    actuarComo($postulante);
    $pantalla->call('revisarCambios')->assertSee('En revisión');
});

it('Entrevistas abierta muestra la entrevista que programó otra persona del equipo', function () {
    $pantalla = Livewire\Livewire::test(Listado::class);
    $this->travel(2)->seconds();

    $postulacion = Postulacion::factory()->paraVacante($this->backend)->enEtapa(EtapaPostulacion::Entrevista)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Teo Huamán'])->id]);
    Entrevista::factory()->create(['postulacion_id' => $postulacion->id, 'inicia_at' => now()->addDay(), 'estado' => EstadoEntrevista::Pendiente]);

    $pantalla->call('revisarCambios')->assertSee('Teo Huamán');
});

it('el dashboard abierto se vuelve a pintar cuando llega una postulación', function () {
    $this->travel(2)->seconds();
    $pantalla = Livewire\Livewire::test(Panel::class);

    $pantalla->call('revisarCambios');
    expect($pantalla->effects)->not->toHaveKey('html');

    $this->travel(2)->seconds();
    postulacionEnBackend($this);
    $pantalla->call('revisarCambios');
    expect($pantalla->effects)->toHaveKey('html');
});

it('la campana muestra un aviso nuevo sin recargar la página', function () {
    $barra = Volt::test('layout.navigation');
    $this->travel(2)->seconds();

    $barra->call('revisarNotificaciones');
    expect($barra->effects)->not->toHaveKey('html');

    $this->marta->notifyNow(AvisoNuevaPostulacion::de('Rosa Quispe', 'Desarrollador Backend', url('/seleccion')), ['database']);
    $barra->call('revisarNotificaciones')->assertSee('Tienes notificaciones sin leer');
});

// — El resto de pantallas también se ponen al día solas —

it('Vacantes abierta muestra la vacante que creó otra persona del equipo', function () {
    $this->travel(2)->seconds();
    $pantalla = Livewire\Livewire::test(App\Livewire\Vacantes\Listado::class);
    $this->travel(2)->seconds();

    Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Analista de Datos Senior']);

    $pantalla->call('revisarCambios')->assertSee('Analista de Datos Senior');
});

it('las notas abiertas de una postulación muestran la que escribió otra persona del equipo', function () {
    $postulacion = postulacionEnBackend($this);
    $this->travel(2)->seconds();
    $pantalla = Livewire\Livewire::test(Notas::class, ['postulacionId' => $postulacion->id]);
    $this->travel(2)->seconds();

    $diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    app(NotasDePostulacion::class)->agregar($postulacion, $diego, 'Disponible desde noviembre');

    $pantalla->call('revisarCambios')->assertSee('Disponible desde noviembre');
});

it('el postulante ve en Vacantes la que se acaba de publicar, sin recargar', function () {
    $postulante = User::factory()->postulante()->create();
    Candidato::factory()->create(['user_id' => $postulante->id]);
    actuarComo($postulante);
    $this->travel(2)->seconds();
    $pantalla = Livewire\Livewire::test(Vacantes::class)->assertDontSee('Arquitecto Cloud');
    $this->travel(2)->seconds();

    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->nimbus)
        ->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Arquitecto Cloud']));

    $pantalla->call('revisarCambios')->assertSee('Arquitecto Cloud');
});

it('la cuenta de empresa ve en Mi empresa el plan que le asignó el admin SaaS, sin recargar', function () {
    actuarComo($this->cuenta);
    $this->travel(2)->seconds();
    $pantalla = Livewire\Livewire::test(App\Livewire\MiEmpresa\Panel::class);
    $this->travel(2)->seconds();

    app(TenantContext::class)->ejecutarGlobal(fn () => $this->nimbus->forceFill(['plan' => PlanEmpresa::Pro, 'prueba_hasta' => null])->save());

    $pantalla->call('revisarCambios');
    expect($pantalla->effects)->toHaveKey('html');
});

it('el admin SaaS ve en Empresas la que se acaba de registrar, sin recargar', function () {
    actuarComo(User::factory()->admin()->create());
    $this->travel(2)->seconds();
    $pantalla = app(TenantContext::class)->ejecutarGlobal(fn () => Livewire\Livewire::test(App\Livewire\Empresas\Listado::class));
    $this->travel(2)->seconds();

    Empresa::factory()->create(['nombre' => 'Cumbre Andina']);

    app(TenantContext::class)->ejecutarGlobal(fn () => $pantalla->call('revisarCambios')->assertSee('Cumbre Andina'));
});
