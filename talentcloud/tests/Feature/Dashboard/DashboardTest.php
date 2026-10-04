<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Livewire\Dashboard\Panel;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Dashboard\DashboardService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);

    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::EnRevision)->create(['titulo' => 'Data Analyst']);
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'DevOps Engineer']);

    // Elena: evaluada dos veces (cuenta una) y con matching reciente; Marco, con matching de hace dos meses.
    $this->elena = Postulacion::factory()->paraVacante($this->backend)->create([
        'candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id,
        'match_score' => 80, 'match_calculado_at' => now()->subDays(2),
    ]);
    $this->sofia = Postulacion::factory()->paraVacante($this->backend)->create([
        'match_score' => 91, 'match_calculado_at' => now()->subDays(10),
    ]);
    Postulacion::factory()->paraVacante($this->backend)->create([
        'match_score' => 10, 'match_calculado_at' => now()->subDays(60),
    ]);
    Evaluacion::factory()->count(2)->sequence(fn () => [])->create(['postulacion_id' => $this->elena->id]);

    // Hoy: una confirmada y una cancelada (no cuenta); mañana: no cuenta.
    Entrevista::factory()->create(['postulacion_id' => $this->elena->id, 'inicia_at' => '2026-09-24 15:00', 'estado' => EstadoEntrevista::Confirmada]);
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => '2026-09-24 11:00', 'estado' => EstadoEntrevista::Cancelada]);
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => '2026-09-25 11:00']);

    // Otra empresa: no debe contar para Nimbus.
    app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $vacante = Vacante::factory()->deEmpresa($vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);
        Postulacion::factory()->paraVacante($vacante)->create(['match_score' => 20, 'match_calculado_at' => now()]);
        $actividad = new Actividad(['tipo' => 'vacante.creada', 'descripcion' => 'Vela Studio creó la vacante Product Designer.']);
        $actividad->empresa_id = $vela->id;
        $actividad->save();
    });

    $actividad = new Actividad(['tipo' => 'postulacion.etapa', 'descripcion' => 'Sofía Nieto avanzó a la etapa de entrevista.']);
    $actividad->empresa_id = $this->nimbus->id;
    $actividad->save();

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

it('calcula los indicadores con el alcance de la empresa', function () {
    $resumen = app(DashboardService::class)->resumen();

    expect($resumen->vacantesActivas)->toBe(2)
        ->and($resumen->candidatosEvaluados)->toBe(1)
        ->and($resumen->matchPromedio)->toBe(86) // (80 + 91) / 2; el de hace 60 días no cuenta
        ->and($resumen->postulacionesConMatch)->toBe(2)
        ->and($resumen->entrevistasHoy)->toBe(1)
        ->and($resumen->vacantesRecientes->pluck('titulo')->all())->not->toContain('Product Designer')
        ->and($resumen->actividad->pluck('descripcion')->all())->toBe(['Sofía Nieto avanzó a la etapa de entrevista.']);
});

it('muestra el dashboard del prototipo', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Resumen de la actividad de selección de Nimbus Corp.')
        ->assertSeeInOrder(['Vacantes activas', '2', 'Candidatos evaluados', '1', 'Match promedio', '86%', 'Entrevistas hoy', '1'])
        ->assertSee('Vacantes recientes')
        ->assertSeeInOrder(['Vacante', 'Empresa', 'Candidatos', 'Estado'])
        ->assertSee('Actividad reciente')
        ->assertSee('Sofía Nieto avanzó a la etapa de entrevista.')
        ->assertDontSee('Vela Studio creó la vacante');
});

it('explica el match promedio como en el prototipo', function () {
    Livewire::test(Panel::class)
        ->set('mostrarDetalleDelMatch', true)
        ->assertSee('Match promedio · 86%')
        ->assertSee('Calculado sobre 2 postulaciones con matching en los últimos 30 días.')
        ->assertSee('La IA pondera competencias técnicas, experiencia y evidencia textual del CV.');
});

it('abre la vista previa de una vacante reciente', function () {
    Livewire::test(Panel::class)
        ->call('verVistaPrevia', $this->backend->id)
        ->assertSet('mostrarVistaPrevia', true)
        ->assertSee('Ver vacante');

    $ajena = app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::where('titulo', 'Product Designer')->value('id'));
    Livewire::test(Panel::class)->call('verVistaPrevia', $ajena)->assertNotFound();
});

it('muestra al admin SaaS el panel de la plataforma, de lo general a lo particular', function () {
    actuarComo(User::factory()->admin()->create());

    $html = Livewire::test(Panel::class)
        ->assertSee('Panel de la plataforma')
        ->assertSee('Vela Studio creó la vacante Product Designer.')
        ->html();

    $orden = array_map(fn (string $texto) => mb_strpos($html, $texto), ['1 · Empresas cliente', '2 · Salud de la IA', '3 · Actividad de selección', 'Embudo de selección']);
    expect($orden)->not->toContain(false)->and($orden)->toBe(collect($orden)->sort()->values()->all());
    expect(app(DashboardService::class)->resumen()->vacantesActivas)->toBe(3);
});

it('muestra un guion cuando no hay matching reciente', function () {
    Postulacion::query()->update(['match_score' => null]);

    Livewire::test(Panel::class)->set('mostrarDetalleDelMatch', true)
        ->assertSee('Match promedio · —')
        ->assertSee('Todavía no hay postulaciones con matching en los últimos 30 días.');
});

it('no deja entrar al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/dashboard')->assertForbidden();
});
