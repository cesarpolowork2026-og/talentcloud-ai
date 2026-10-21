<?php

use App\Enums\EstadoSuscripcion;
use App\Enums\EstadoVacante;
use App\Enums\PlanEmpresa;
use App\Livewire\Dashboard\Panel as Dashboard;
use App\Livewire\Empresas\Listado as Empresas;
use App\Livewire\MiEmpresa\Panel as MiEmpresa;
use App\Models\Actividad;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCambioDePlan;
use App\Notifications\AvisoVencimientoDePlan;
use App\Services\Empresas\CambiarPlanEmpresa;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Empresas\SolicitarCambioDePlan;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Vencimiento de un plan de pago asignado a mano por el admin SaaS: al pasar la fecha rige el plan Gratis sin tocar
 * nada (como la prueba); sin fecha no vence; un plan pagado con tarjeta lo gobierna la pasarela.
 */

beforeEach(function () {
    Notification::fake();
    // Las cifras del panel de plataforma se recuerdan 30 s: cada test parte sin ellas.
    Cache::flush();

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->empresa = Empresa::factory()->create(['nombre' => 'Orbita Labs', 'plan' => PlanEmpresa::Gratis]);
        $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create(['name' => 'Olga Ramos']);
        $this->admin = User::factory()->admin()->create();
    });
});

function asignarPlan(Empresa $empresa, PlanEmpresa $plan, ?Carbon $venceAt = null, ?User $actor = null): Empresa
{
    return app(TenantContext::class)->ejecutarGlobal(
        fn () => app(CambiarPlanEmpresa::class)->ejecutar($empresa, $plan, $actor, $venceAt),
    );
}

// — Regla de negocio —

it('rige el plan asignado hasta su vencimiento y después el Gratis, sin tareas programadas', function () {
    $empresa = asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(10)->endOfDay());

    expect($empresa->planVigente())->toBe(PlanEmpresa::Pro)
        ->and($empresa->planVencido())->toBeFalse()
        ->and($empresa->diasParaVencer())->toBe(11)
        ->and($empresa->etiquetaDelPlan())->toBe('Pro');

    $this->travel(12)->days();
    $vencida = $empresa->fresh();

    // El plan guardado no se toca: solo cambia el que rige.
    expect($vencida->plan)->toBe(PlanEmpresa::Pro)
        ->and($vencida->planVencido())->toBeTrue()
        ->and($vencida->planContratado())->toBe(PlanEmpresa::Gratis)
        ->and($vencida->planVigente())->toBe(PlanEmpresa::Gratis)
        ->and($vencida->etiquetaDelPlan())->toBe('Gratis')
        ->and($vencida->diasParaVencer())->toBe(0);
});

it('un plan sin fecha no vence nunca', function () {
    $empresa = asignarPlan($this->empresa, PlanEmpresa::Enterprise);

    $this->travel(5)->years();

    expect($empresa->fresh()->planVigente())->toBe(PlanEmpresa::Enterprise)
        ->and($empresa->fresh()->planVencido())->toBeFalse()
        ->and($empresa->fresh()->tieneVencimientoPropio())->toBeFalse();
});

it('al vencer, los límites pasan a los de Gratis pero lo existente se conserva', function () {
    $empresa = asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(3)->endOfDay());
    app(TenantContext::class)->ejecutarGlobal(function () use ($empresa) {
        Vacante::factory()->deEmpresa($empresa)->count(2)->enEstado(EstadoVacante::Abierta)->create();
    });

    $this->travel(5)->days();
    $empresa = $empresa->fresh();

    // Gratis permite una vacante activa: ya hay dos, se conservan, pero no se puede sumar otra.
    expect(Vacante::sinFiltroDeEmpresa()->where('empresa_id', $empresa->id)->whereIn('estado', EstadoVacante::activos())->count())->toBe(2)
        ->and(fn () => app(LimitesDelPlan::class)->verificarVacanteActiva($empresa->id))->toThrow(OperacionNoPermitida::class);
});

it('el plan de una suscripción en línea no depende de esta fecha', function () {
    $this->empresa->forceFill([
        'plan' => PlanEmpresa::Pro,
        'plan_vence_at' => now()->subDay(),
        'suscripcion_estado' => EstadoSuscripcion::Activa,
    ])->save();

    expect($this->empresa->fresh()->planVigente())->toBe(PlanEmpresa::Pro)
        ->and($this->empresa->fresh()->planVencido())->toBeFalse();
});

it('avisa que el plan vence pronto solo dentro de la ventana de aviso', function () {
    $lejos = asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(Empresa::DIAS_DE_AVISO + 5)->endOfDay());
    expect($lejos->planVencePronto())->toBeFalse();

    $cerca = asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(Empresa::DIAS_DE_AVISO - 5)->endOfDay());
    expect($cerca->planVencePronto())->toBeTrue();

    $this->travel(Empresa::DIAS_DE_AVISO)->days();
    expect($cerca->fresh()->planVencePronto())->toBeFalse()->and($cerca->fresh()->planVencido())->toBeTrue();
});

// — CambiarPlanEmpresa —

it('rechaza un vencimiento pasado o en el plan Gratis', function () {
    expect(fn () => asignarPlan($this->empresa, PlanEmpresa::Pro, now()->subDay()))
        ->toThrow(OperacionNoPermitida::class, 'El vencimiento debe ser una fecha futura.');

    expect(fn () => asignarPlan($this->empresa, PlanEmpresa::Gratis, now()->addDay()))
        ->toThrow(OperacionNoPermitida::class, 'El plan Gratis no vence.');

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis)->and($this->empresa->fresh()->plan_vence_at)->toBeNull();
});

it('pasar a Gratis o pagar en línea borra el vencimiento', function () {
    asignarPlan($this->empresa, PlanEmpresa::Starter, now()->addDays(30));
    $gratis = asignarPlan($this->empresa->fresh(), PlanEmpresa::Gratis);

    expect($gratis->plan_vence_at)->toBeNull();
});

it('renovar el mismo plan con otra fecha es válido y repetir la misma fecha no', function () {
    $vence = now()->addDays(30)->startOfSecond();
    asignarPlan($this->empresa, PlanEmpresa::Pro, $vence);

    expect(fn () => asignarPlan($this->empresa->fresh(), PlanEmpresa::Pro, $vence))
        ->toThrow(OperacionNoPermitida::class, 'Orbita Labs ya tiene el plan Pro con ese vencimiento.');

    $renovada = asignarPlan($this->empresa->fresh(), PlanEmpresa::Pro, $vence->copy()->addYear());

    expect($renovada->plan_vence_at->equalTo($vence->copy()->addYear()))->toBeTrue();
});

it('renueva un plan ya vencido y la empresa recupera sus límites', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(2)->endOfDay());
    $this->travel(4)->days();
    expect($this->empresa->fresh()->planVigente())->toBe(PlanEmpresa::Gratis);

    $renovada = asignarPlan($this->empresa->fresh(), PlanEmpresa::Pro, now()->addDays(30)->endOfDay());

    expect($renovada->planVigente())->toBe(PlanEmpresa::Pro)->and($renovada->planVencido())->toBeFalse();
});

it('el aviso y la actividad dicen que se renovó y hasta cuándo rige', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(30)->endOfDay(), $this->admin);
    asignarPlan($this->empresa->fresh(), PlanEmpresa::Pro, now()->addDays(60)->endOfDay(), $this->admin);

    $textos = Notification::sent($this->cuenta, AvisoCambioDePlan::class)->pluck('texto');
    expect($textos[0])->toStartWith('Orbita Labs pasó al plan Pro. Vigente hasta el ')
        ->and($textos[1])->toStartWith('El plan Pro de Orbita Labs se renovó. Vigente hasta el ');

    $actividad = app(TenantContext::class)->ejecutarPara($this->empresa->id, fn () => Actividad::query()->pluck('descripcion'));
    expect($actividad->contains(fn (string $d) => str_starts_with($d, 'Orbita Labs renovó el plan Pro. Vigente hasta el ')))->toBeTrue();
});

// — Solicitud de renovación —

it('una empresa con el plan vencido puede solicitar su renovación', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDay()->endOfDay());
    $this->travel(3)->days();

    $solicitada = app(SolicitarCambioDePlan::class)->ejecutar($this->empresa->fresh(), PlanEmpresa::Pro, $this->cuenta);

    expect($solicitada->plan_solicitado)->toBe(PlanEmpresa::Pro);
});

// — Pantallas —

it('el admin asigna un plan con vencimiento desde Empresas, y vacío no vence', function () {
    actuarComo($this->admin);
    $fecha = now()->addDays(40)->format('Y-m-d');

    Livewire::test(Empresas::class)
        ->call('abrirCambioDePlan', $this->empresa->id)
        ->set('planNuevo', 'pro')
        ->assertSee('Vence el (opcional)')
        ->set('planVenceEl', $fecha)
        ->call('cambiarPlan')
        ->assertHasNoErrors();

    $empresa = $this->empresa->fresh();
    expect($empresa->plan)->toBe(PlanEmpresa::Pro)
        ->and($empresa->plan_vence_at->timezone(config('app.timezone'))->format('Y-m-d H:i'))->toBe($fecha.' 23:59');

    Livewire::test(Empresas::class)
        ->call('abrirCambioDePlan', $this->empresa->id)
        ->assertSet('planVenceEl', $fecha)
        ->set('planNuevo', 'enterprise')
        ->assertSet('planVenceEl', $fecha)
        ->set('planVenceEl', '')
        ->call('cambiarPlan')
        ->assertHasNoErrors();

    expect($this->empresa->fresh()->plan_vence_at)->toBeNull();
});

it('el formulario rechaza fechas pasadas o mal escritas y oculta el campo en Gratis', function () {
    actuarComo($this->admin);

    Livewire::test(Empresas::class)
        ->call('abrirCambioDePlan', $this->empresa->id)
        ->set('planNuevo', 'pro')
        ->set('planVenceEl', now()->subDay()->format('Y-m-d'))
        ->call('cambiarPlan')
        ->assertHasErrors('planVenceEl')
        ->set('planVenceEl', 'mañana')
        ->call('cambiarPlan')
        ->assertHasErrors('planVenceEl')
        ->set('planNuevo', 'gratis')
        ->assertDontSee('Vence el (opcional)');

    expect($this->empresa->fresh()->plan)->toBe(PlanEmpresa::Gratis);
});

it('la empresa ve cuándo vence su plan y que ya venció', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(5)->endOfDay());
    actuarComo($this->cuenta);

    Livewire::test(MiEmpresa::class)->assertSee('Tu plan Pro vence el')->assertSee('quedan 6 días');
    Livewire::test(Dashboard::class)->assertSee('Tu plan Pro vence el');

    $this->travel(8)->days();

    Livewire::test(MiEmpresa::class)->assertSee('Tu plan Pro venció el')->assertSee('ahora rige el plan Gratis');
    Livewire::test(Dashboard::class)->assertSee('Tu plan · Gratis')->assertSee('Tu plan Pro venció el');
});

it('un plan lejano o sin vencimiento no muestra aviso en el dashboard', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(Empresa::DIAS_DE_AVISO + 30)->endOfDay());
    actuarComo($this->cuenta);

    Livewire::test(Dashboard::class)->assertDontSee('vence el');
});

it('el panel de la plataforma lista los planes por vencer y vencidos, y no cuenta los vencidos como de pago', function () {
    $vencido = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create(['nombre' => 'Vela Studio', 'plan' => PlanEmpresa::Gratis]));
    $lejos = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => PlanEmpresa::Gratis]));
    asignarPlan($vencido, PlanEmpresa::Pro, now()->addDay()->endOfDay());
    asignarPlan($lejos, PlanEmpresa::Pro, now()->addDays(Empresa::DIAS_DE_AVISO + 40)->endOfDay());
    asignarPlan($this->empresa, PlanEmpresa::Starter, now()->addDays(4)->endOfDay());
    $this->travel(3)->days();
    Cache::flush();
    actuarComo($this->admin);

    $html = Livewire::test(Dashboard::class)->assertSee('Planes por vencer')->html();

    expect($html)->toContain('wire:key="por-vencer-'.$vencido->id.'"')
        ->and($html)->toContain('wire:key="por-vencer-'.$this->empresa->id.'"')
        ->and($html)->not->toContain('wire:key="por-vencer-'.$lejos->id.'"');
});

it('el detalle de la empresa muestra su vencimiento al admin', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(9)->endOfDay());
    actuarComo($this->admin);

    Livewire::test(Empresas::class)
        ->call('verDetalle', $this->empresa->id)
        ->assertSee('Vence el')
        ->assertSee('después, Gratis');
});

it('el uso del plan del dashboard cambia al vencer aunque las cifras estén en caché', function () {
    config(['talentcloud.dashboard.cache_segundos' => 60]);
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(2)->endOfDay());
    actuarComo($this->cuenta);

    Livewire::test(Dashboard::class)->assertSee('de 15');

    // Pasa el vencimiento dentro de la vigencia de la caché: los límites ya son los de Gratis.
    $this->travel(3)->days();

    Livewire::test(Dashboard::class)->assertDontSee('de 15')->assertSee('Tu plan · Gratis');
});

// — Avisos antes de vencer —

it('avisa una sola vez por correo cuando el plan está por vencer, aunque la tarea corra cada día', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(10)->endOfDay());

    $this->artisan('talentcloud:avisar-vencimientos')->expectsOutput('Avisos de vencimiento enviados: 1.')->assertSuccessful();
    $this->travel(1)->days();
    $this->artisan('talentcloud:avisar-vencimientos')->expectsOutput('Avisos de vencimiento enviados: 0.')->assertSuccessful();

    Notification::assertSentToTimes($this->cuenta, AvisoVencimientoDePlan::class, 1);
    Notification::assertSentTo($this->cuenta, AvisoVencimientoDePlan::class, fn (AvisoVencimientoDePlan $n) => str_starts_with($n->texto, 'El plan Pro de Orbita Labs vence el ')
        && str_contains($n->texto, 'quedan 11 días')
        && $n->asunto === 'Tu plan de TalentCloud AI vence pronto'
        && in_array('mail', $n->via($this->cuenta), true));
});

it('no avisa si faltan más días que la ventana de aviso ni si el plan no vence', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(Empresa::DIAS_DE_AVISO + 10)->endOfDay());
    $sinFecha = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create(['plan' => PlanEmpresa::Enterprise]));
    $cuentaSinFecha = app(TenantContext::class)->ejecutarGlobal(fn () => User::factory()->deEmpresa($sinFecha)->empresa()->create());

    $this->artisan('talentcloud:avisar-vencimientos')->expectsOutput('Avisos de vencimiento enviados: 0.')->assertSuccessful();

    Notification::assertNotSentTo($this->cuenta, AvisoVencimientoDePlan::class);
    Notification::assertNotSentTo($cuentaSinFecha, AvisoVencimientoDePlan::class);
});

it('avisa que el plan venció una vez, y deja de avisar pasada una semana', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(2)->endOfDay());
    $this->travel(3)->days();

    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();
    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    Notification::assertSentToTimes($this->cuenta, AvisoVencimientoDePlan::class, 1);
    Notification::assertSentTo($this->cuenta, AvisoVencimientoDePlan::class, fn (AvisoVencimientoDePlan $n) => str_contains($n->texto, 'venció el') && $n->asunto === 'Tu plan de TalentCloud AI venció');

    // Un plan vencido hace semanas y nunca avisado no genera un correo viejo.
    $viejo = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create(['plan' => PlanEmpresa::Pro, 'plan_vence_at' => now()->subDays(30)]));
    $cuentaVieja = app(TenantContext::class)->ejecutarGlobal(fn () => User::factory()->deEmpresa($viejo)->empresa()->create());
    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    Notification::assertNotSentTo($cuentaVieja, AvisoVencimientoDePlan::class);
});

it('una renovación con otra fecha vuelve a avisar y una suscripción en línea no recibe este aviso', function () {
    asignarPlan($this->empresa, PlanEmpresa::Pro, now()->addDays(5)->endOfDay());
    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    asignarPlan($this->empresa->fresh(), PlanEmpresa::Pro, now()->addDays(8)->endOfDay());
    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    Notification::assertSentToTimes($this->cuenta, AvisoVencimientoDePlan::class, 2);

    $enLinea = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create([
        'plan' => PlanEmpresa::Pro, 'plan_vence_at' => now()->addDays(3), 'suscripcion_estado' => EstadoSuscripcion::Activa,
    ]));
    $cuentaEnLinea = app(TenantContext::class)->ejecutarGlobal(fn () => User::factory()->deEmpresa($enLinea)->empresa()->create());
    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    Notification::assertNotSentTo($cuentaEnLinea, AvisoVencimientoDePlan::class);
});

it('no avisa a una empresa suspendida', function () {
    $suspendida = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->suspendida()->create(['plan' => PlanEmpresa::Pro, 'plan_vence_at' => now()->addDays(3)]));
    $cuenta = app(TenantContext::class)->ejecutarGlobal(fn () => User::factory()->deEmpresa($suspendida)->empresa()->create());

    $this->artisan('talentcloud:avisar-vencimientos')->assertSuccessful();

    Notification::assertNotSentTo($cuenta, AvisoVencimientoDePlan::class);
});
