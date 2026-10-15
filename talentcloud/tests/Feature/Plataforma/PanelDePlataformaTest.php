<?php

use App\DTO\UsoDelPlan;
use App\Enums\EstadoVacante;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Livewire\Dashboard\Panel;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Plataforma\CalidadDeLaIa;
use App\Services\Plataforma\PanelDePlataforma;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * Panel de la plataforma del admin SaaS: empresas cliente (planes y uso) y
 * salud de la IA, antes de la actividad de selección.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        // Starter con 3 de 3 vacantes activas: cerca (en) el límite.
        $this->orbita = Empresa::factory()->create(['nombre' => 'Orbita Labs', 'plan' => PlanEmpresa::Starter]);
        Vacante::factory()->count(3)->deEmpresa($this->orbita)->enEstado(EstadoVacante::Abierta)->create();

        // Pro con poco uso: lejos del límite.
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio', 'plan' => PlanEmpresa::Pro]);
        User::factory()->deEmpresa($this->vela)->reclutador()->create(['name' => 'Clara Vidal']);

        // Enterprise: nunca está cerca de un límite.
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'plan' => PlanEmpresa::Enterprise]);
        Vacante::factory()->count(30)->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();

        Empresa::factory()->suspendida()->create(['nombre' => 'Suspendida SA', 'plan' => PlanEmpresa::Starter]);
    });

    $this->admin = User::factory()->admin()->create();
    actuarComo($this->admin);
});

it('resume las empresas por estado y por plan', function () {
    $resumen = app(PanelDePlataforma::class)->resumen();

    expect($resumen->empresasActivas)->toBe(3)
        ->and($resumen->empresasSuspendidas)->toBe(1)
        ->and($resumen->empresasConPlan(PlanEmpresa::Starter))->toBe(1)
        ->and($resumen->empresasConPlan(PlanEmpresa::Pro))->toBe(1)
        ->and($resumen->empresasConPlan(PlanEmpresa::Enterprise))->toBe(1);
});

it('señala solo las empresas activas que usan el 80 % o más de su plan', function () {
    $cerca = app(PanelDePlataforma::class)->resumen()->cercaDelLimite;

    expect($cerca->map(fn (array $f) => $f['empresa']->nombre)->all())->toBe(['Orbita Labs'])
        ->and($cerca->first()['uso']->vacantesActivas)->toBe(3);
});

it('cuenta las invitaciones pendientes para el límite de usuarios', function () {
    $uso = new UsoDelPlan(PlanEmpresa::Starter, vacantesActivas: 0, usuariosActivos: 2, invitacionesPendientes: 0);
    expect($uso->cercaDelLimite())->toBeFalse();

    $uso = new UsoDelPlan(PlanEmpresa::Starter, vacantesActivas: 0, usuariosActivos: 2, invitacionesPendientes: 1);
    expect($uso->cercaDelLimite())->toBeTrue()
        ->and((new UsoDelPlan(PlanEmpresa::Enterprise, 500, 500, 0))->cercaDelLimite())->toBeFalse();
});

it('incluye en el panel la invitación pendiente de una empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach (['uno@vela.studio', 'dos@vela.studio', 'tres@vela.studio', 'cuatro@vela.studio', 'cinco@vela.studio', 'seis@vela.studio', 'siete@vela.studio'] as $email) {
            $invitacion = new Invitacion(['nombre' => 'X', 'email' => $email, 'rol' => Rol::Reclutador, 'token_hash' => hash('sha256', $email), 'expira_at' => now()->addDays(7)]);
            $invitacion->empresa_id = $this->vela->id;
            $invitacion->save();
        }
    });

    // Pro: 1 usuario + 7 invitaciones = 8 de 10 → 80 %.
    expect(app(PanelDePlataforma::class)->resumen()->cercaDelLimite->map(fn (array $f) => $f['empresa']->nombre)->all())
        ->toBe(['Orbita Labs', 'Vela Studio']);
});

it('muestra al admin las tres secciones numeradas con sus accesos', function () {
    Livewire::test(Panel::class)
        ->assertSee('1 · Empresas cliente')
        ->assertSee('Gestionar empresas')
        ->assertSee('Cerca del límite de su plan')
        ->assertSeeHtml('<td>Orbita Labs</td>')
        ->assertSee('3 de 3')
        ->assertSee('2 · Salud de la IA')
        ->assertSee('Ver calidad de la IA')
        ->assertSee('3 · Actividad de selección');
});

it('no muestra las secciones de la plataforma al personal de una empresa', function () {
    actuarComo(User::factory()->deEmpresa($this->vela)->reclutador()->create());

    Livewire::test(Panel::class)
        ->assertSee('Dashboard')
        ->assertDontSee('Empresas cliente')
        ->assertDontSee('Salud de la IA')
        ->assertDontSee('Orbita Labs');
});

it('usa solo los indicadores de la IA, sin cargar listas que el panel no muestra', function () {
    $calidad = app(CalidadDeLaIa::class);
    $completo = $calidad->resumen();
    $indicadores = $calidad->indicadores();

    expect($indicadores->marcas)->toBeEmpty()
        ->and($indicadores->ultimosTrabajosFallidos)->toBeEmpty()
        ->and([$indicadores->calculadas, $indicadores->conIa, $indicadores->basicasPendientes, $indicadores->cvsConError, $indicadores->marcasDelPeriodo])
        ->toBe([$completo->calculadas, $completo->conIa, $completo->basicasPendientes, $completo->cvsConError, $completo->marcasDelPeriodo]);
});

it('muestra la distribución de empresas por plan como una barra apilada con su leyenda', function () {
    $html = Livewire::test(Panel::class)->html();

    // Gratis 0, Starter 1, Pro 1, Enterprise 1: tres segmentos en la barra y los cuatro planes en la leyenda.
    expect($html)->toContain('Empresas activas por plan contratado')
        ->and(substr_count($html, 'class="apilada-segmento"'))->toBe(3)
        ->and(substr_count($html, 'apilada-muestra'))->toBe(count(PlanEmpresa::cases()))
        ->and($html)->toContain('33 %')
        // Las cifras por plan ya no son tarjetas sueltas: están en la leyenda.
        ->and($html)->not->toContain('Plan Starter')
        // Y la leyenda dice quiénes son, no solo cuántos (con enlace al listado de Empresas).
        ->and($html)->toContain('Empresas con el plan Starter')->toContain('Orbita Labs')
        ->and($html)->toContain('Empresas con el plan Pro')->toContain('Vela Studio')
        ->and($html)->toContain('Empresas con el plan Enterprise')->toContain('Nimbus Corp')
        ->and($html)->toContain('q=Orbita');
    // La empresa suspendida no figura en ningún plan.
    expect($html)->not->toContain('Suspendida SA');
});
