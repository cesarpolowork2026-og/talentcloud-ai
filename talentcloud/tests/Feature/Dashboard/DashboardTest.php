<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Livewire\Dashboard\ActividadReciente;
use App\Livewire\Dashboard\Panel;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
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
    $this->analyst = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::EnRevision)->create(['titulo' => 'Data Analyst']);
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'DevOps Engineer']);

    $this->elena = Postulacion::factory()->paraVacante($this->backend)->create([
        'candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id,
    ]);
    $this->sofia = Postulacion::factory()->paraVacante($this->backend)->create();
    Postulacion::factory()->paraVacante($this->backend)->create();

    // Hoy: una confirmada y una cancelada (no cuenta); mañana: una pendiente.
    Entrevista::factory()->create(['postulacion_id' => $this->elena->id, 'inicia_at' => '2026-09-24 15:00', 'estado' => EstadoEntrevista::Confirmada]);
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => '2026-09-24 11:00', 'estado' => EstadoEntrevista::Cancelada]);
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => '2026-09-25 11:00']);

    // Otra empresa: no debe contar para Nimbus.
    app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $vacante = Vacante::factory()->deEmpresa($vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);
        Postulacion::factory()->paraVacante($vacante)->create();
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
        ->and($resumen->postulantes)->toBe(3)
        ->and($resumen->postulacionesNuevas)->toBe(3)
        ->and($resumen->porRevisar)->toBe(3)
        ->and($resumen->entrevistasHoy)->toBe(1)
        ->and($resumen->entrevistasProximas)->toBe(2) // la confirmada de hoy a las 15:00 y la pendiente de mañana
        ->and($resumen->vacantesConMovimiento->pluck('titulo')->all())->not->toContain('Product Designer', 'DevOps Engineer');
});

it('muestra el dashboard con lo que pide atención primero', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Resumen de la actividad de selección de Nimbus Corp.')
        ->assertSeeInOrder(['Vacantes activas', '2', 'Postulantes', '3', 'Por revisar', '3', 'Entrevistas hoy', '1'])
        ->assertSeeInOrder(['Requiere tu atención', 'Tienes 1 entrevista hoy.', '1 entrevista por confirmar.'])
        ->assertSeeInOrder(['Vacantes en curso', 'Actividad reciente'])
        ->assertDontSee('Tiempo ahorrado')
        ->assertDontSee('con IA')
        ->assertSee('Sofía Nieto avanzó a la etapa de entrevista.')
        ->assertDontSee('Vela Studio creó la vacante')
        // Se retiraron los indicadores que informaban pero no ayudaban a decidir.
        ->assertDontSee('Match promedio')
        ->assertDontSee('Candidatos evaluados');
});

it('avisa de las postulaciones que llevan días sin revisarse y de las vacantes sin postulaciones', function () {
    $this->elena->forceFill(['etapa_cambiada_at' => now()->subDays(5)])->save();
    $this->sofia->forceFill(['etapa_cambiada_at' => now()->subDays(1)])->save();

    // Una abierta hace dos semanas sin ninguna postulación.
    $vacia = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Soporte TI', 'publicada_at' => now()->subDays(14)]);

    $textos = collect(app(DashboardService::class)->resumen()->atencion)->pluck('texto')->all();

    // La tercera postulación nunca cambió de etapa y llegó ahora: tampoco cuenta como atrasada.
    expect($textos)->toContain('Desarrollador Backend: 1 postulación lleva más de 3 días sin revisar.')
        ->and(collect($textos)->first(fn ($t) => str_contains($t, 'Soporte TI')))->toContain('más de 7 días abierta sin postulaciones');

    Livewire::test(Panel::class)
        ->assertSee('Desarrollador Backend: 1 postulación lleva más de 3 días sin revisar.')
        ->assertSeeHtml(route('seleccion.show', $this->backend))
        ->assertSeeHtml(route('vacantes.show', $vacia));
});

it('no cuenta como por revisar a quien retiró su postulación ni a las vacantes cerradas', function () {
    $this->elena->forceFill(['retirada_at' => now(), 'etapa' => 'descartado'])->save();
    $cerrada = Vacante::sinFiltroDeEmpresa()->where('titulo', 'DevOps Engineer')->firstOrFail();
    Postulacion::factory()->paraVacante($cerrada)->create();

    expect(app(DashboardService::class)->resumen()->porRevisar)->toBe(2);
});

it('dice que todo está al día cuando no hay nada pendiente', function () {
    Entrevista::query()->delete();
    Postulacion::query()->update(['etapa' => EtapaPostulacion::EnRevision]);

    Livewire::test(Panel::class)->assertSee('Todo al día');
});

it('ordena las vacantes en curso por lo que tienen por revisar', function () {
    Postulacion::factory()->paraVacante($this->analyst)->enEtapa(EtapaPostulacion::EnRevision)->create();

    $filas = app(DashboardService::class)->resumen()->vacantesConMovimiento;

    expect($filas->pluck('titulo')->all())->toBe(['Desarrollador Backend', 'Data Analyst'])
        ->and((int) $filas[0]->por_revisar)->toBe(3)
        ->and((int) $filas[1]->por_revisar)->toBe(0);

    Livewire::test(Panel::class)->assertSeeInOrder(['Vacante', 'Postulantes', 'Por revisar', 'Estado'])->assertDontSee('Mejor match');
});

it('la actividad reciente es un componente que se refresca solo y muestra solo lo de la empresa', function () {
    $html = Livewire::test(ActividadReciente::class)
        ->assertSee('Sofía Nieto avanzó a la etapa de entrevista.')
        ->assertDontSee('Vela Studio creó la vacante')
        ->html();

    expect($html)->toContain('wire:poll.30s.visible');
});

it('muestra el plan de la empresa con su uso y, en la prueba, los días que quedan', function () {
    $this->nimbus->forceFill(['plan' => 'gratis', 'prueba_hasta' => now()->addDays(5)])->save();

    Livewire::test(Panel::class)
        ->assertSee('Tu plan · Pro (prueba)')
        ->assertSee('quedan 5 días de prueba del plan Pro')
        ->assertSee('Vacantes activas')
        ->assertSee('Ver planes y precios'); // un reclutador no gestiona el plan: ve la tabla pública
});

it('abre la vista previa de una vacante de la lista', function () {
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
        ->assertDontSee('Tu plan ·')
        // El embudo y la actividad de selección son de cada empresa: el admin los supervisa desde «Panorama».
        ->assertDontSee('Embudo de selección')
        ->assertDontSee('Requiere tu atención')
        ->html();

    $orden = array_map(fn (string $texto) => mb_strpos($html, $texto), ['1 · Empresas cliente', '2 · Uso y crecimiento', '3 · Estado del sistema']);
    expect($orden)->not->toContain(false)->and($orden)->toBe(collect($orden)->sort()->values()->all());
});

it('no deja entrar al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/dashboard')->assertForbidden();
});
